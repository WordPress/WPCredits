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
			'airtable'        => array( 'error', __( 'The program records could not be updated, so nothing was saved. Please try again in a while.', 'wpcredits-program-manager' ) ),
			'refused-now'     => array( 'error', __( 'Nothing was saved. Please correct what is marked below and save again.', 'wpcredits-program-manager' ) ),
			'refused-checked' => array( 'error', __( 'Nothing was sent. Please correct what is marked below and send it again.', 'wpcredits-program-manager' ) ),
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

		printf( '<section class="wpcpm-institution__card wpcpm-details" id="%s">', esc_attr( self::ANCHOR ) );

		// The same disclosure the People card and the roster's groups are drawn in, open by default, so
		// the theme's one set of rules for the fold, the chevron and the inset applies here too.
		printf(
			'<details class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body">',
			esc_html__( 'Your institution\'s details', 'wpcredits-program-manager' )
		);

		printf( '<p class="wpcpm-details__lead">%s</p>', esc_html__( 'What the program has on record.', 'wpcredits-program-manager' ) );

		if ( null !== $flash['message'] ) {
			printf(
				'<p class="wpcpm-details__message is-%1$s" role="status">%2$s</p>',
				esc_attr( $flash['message'][0] ),
				esc_html( $flash['message'][1] )
			);
		}

		foreach ( self::groups() as $group => $heading ) {
			printf( '<h4 class="wpcpm-details__subtitle">%s</h4>', esc_html( $heading ) );

			self::render_rows( $group, $current, $facts, $checker );

			if ( WPCPM_Institution_Details::GROUP_CHECKED === $group && ! $open ) {
				self::render_outcome( $record );
			}

			// One request at a time: while one waits, the second group offers nothing to type into, to the
			// Administrator no more than to the school. An Administrator's own correction would leave the
			// school's request open over a value it was not asked against, so they are told where it is decided.
			if ( WPCPM_Institution_Details::GROUP_CHECKED === $group && $open ) {
				if ( $checker ) {
					self::render_decide();
				}

				continue;
			}

			self::render_form( $record, $group, $current, $kept[ $group ], $group === $flash['group'] ? $flash['problems'] : array(), $checker );
		}

		echo '</div></details></section>';
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

	/**
	 * One group's rows: label and value, in the order of the details table, and under a row that has a
	 * change waiting, the line that says so.
	 *
	 * A definition list, a row to a `div`, so the waiting line is part of the row it is about and a
	 * screen reader reads it with that row.
	 *
	 * @param string $group   `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $current What the program has on record (`WPCPM_Institution_Details::current()`).
	 * @param array  $facts   The open request's facts (`WPCPM_Institution_Request::facts()`), or none.
	 * @param bool   $checker Whether the reader is an Administrator, who reads the waiting lines and is not
	 *                        offered the school's Withdraw it.
	 */
	private static function render_rows( $group, array $current, array $facts, $checker ) {
		$asked = array();

		foreach ( isset( $facts['changes'] ) ? (array) $facts['changes'] : array() as $row ) {
			$asked[ $row['key'] ] = $row['asked'];
		}

		echo '<dl class="wpcpm-details__list">';

		foreach ( WPCPM_Institution_Details::fields() as $key => $spec ) {
			if ( $group !== $spec['group'] ) {
				continue;
			}

			printf(
				'<div class="wpcpm-details__row"><dt>%1$s</dt><dd>%2$s</dd>',
				esc_html( $spec['label'] ),
				esc_html( self::shown( $key, $current[ $key ] ) )
			);

			if ( isset( $asked[ $key ] ) ) {
				self::render_waiting( $asked[ $key ], (int) $facts['at'], (int) $facts['id'], ! $checker );
			}

			echo '</div>';
		}

		echo '</dl>';
	}

	/**
	 * One value as the list reads it: the details class's words for it, and "Not given yet" for none.
	 *
	 * @param string $key   A key of the details table.
	 * @param mixed  $value What the program has on record.
	 * @return string
	 */
	private static function shown( $key, $value ) {
		$empty = is_array( $value ) ? array() === $value : '' === trim( (string) $value );

		return $empty ? __( 'Not given yet', 'wpcredits-program-manager' ) : WPCPM_Institution_Details::show( $key, $value );
	}

	/**
	 * The line under a row whose change waits for an Administrator: what was asked, the day, and Withdraw it.
	 *
	 * Every waiting row of the school's own accounts carries its own Withdraw it, as the mockup draws them,
	 * though they withdraw the one request. The form posts the request's own handler, whose nonce is keyed to
	 * the request and whose policy check is the same fence this card sits behind. An Administrator reads the
	 * line and is not offered the school's button: the request is decided where requests are decided, and
	 * their handler still admits them should a screen of theirs ever post it.
	 *
	 * The line takes the page's warning tone from its `wpcpm-warning` class.
	 *
	 * @param string $asked    The value asked for, as a person reads it.
	 * @param int    $at       When it was asked, as a Unix time.
	 * @param int    $request  The request.
	 * @param bool   $withdraw Whether to offer Withdraw it.
	 */
	private static function render_waiting( $asked, $at, $request, $withdraw ) {
		echo '<dd class="wpcpm-details__waiting wpcpm-warning">';

		printf(
			'<span class="wpcpm-details__waiting-text">%s</span> ',
			esc_html(
				sprintf(
					/* translators: 1: the value an Administrator is being asked to put on record, 2: the day it was asked. */
					__( 'Waiting for an Administrator: %1$s, asked on %2$s.', 'wpcredits-program-manager' ),
					$asked,
					WPCPM_Dates::day( $at )
				)
			)
		);

		if ( ! $withdraw ) {
			echo '</dd>';

			return;
		}

		printf(
			'<form class="wpcpm-details__withdraw" method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Withdrawing', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW . '_' . $request );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW ) );
		printf( '<input type="hidden" name="request" value="%d" />', (int) $request );
		// The card the press was made on: the withdrawal comes back to it and not to the top of the page.
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( WPCPM_Institution_Request::RETURN_FIELD ), esc_attr( self::ANCHOR ) );
		printf( '<button type="submit" class="wpcpm-details__withdraw-button">%s</button>', esc_html__( 'Withdraw it', 'wpcredits-program-manager' ) );
		echo '</form></dd>';
	}

	/**
	 * The line an Administrator reads under the second group while the school's request waits: where it is
	 * decided, linked to the requests card of the Administrator Dashboard while that page is published.
	 */
	private static function render_decide() {
		$page = (string) WPCPM_Administrators_Dashboard::page_url();

		printf(
			'<p class="wpcpm-details__decide">%s</p>',
			wp_kses(
				'' !== $page
					? self::decide_sentence( '<a href="' . esc_url( $page . '#wpcpm-requests' ) . '">', '</a>' )
					: esc_html( self::decide_sentence() ),
				array( 'a' => array( 'href' => array() ) )
			)
		);
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
	 * What the school was last told about a request: the day an approval was made, or an Administrator's
	 * note on a decline, and nothing for a withdrawal.
	 *
	 * Drawn to every account of the school, and only while no request waits: the next request replaces
	 * the outcome, and a withdrawal is an outcome that says nothing.
	 *
	 * @param string $record Institutions record ID.
	 */
	private static function render_outcome( $record ) {
		$outcome = WPCPM_Institution_Request::latest_details_outcome( $record );

		if ( empty( $outcome['state'] ) ) {
			return;
		}

		if ( WPCPM_Institution_Request::STATE_DONE === $outcome['state'] ) {
			$day = WPCPM_Dates::day( (int) $outcome['closed_at'] );

			if ( '' === $day ) {
				return;
			}

			printf(
				'<p class="wpcpm-details__outcome">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the day an Administrator made the change a school asked for. */
						__( 'Updated on %s.', 'wpcredits-program-manager' ),
						$day
					)
				)
			);

			return;
		}

		if ( WPCPM_Institution_Request::STATE_DECLINED !== $outcome['state'] ) {
			return;
		}

		$note = trim( (string) $outcome['note'] );

		printf(
			'<p class="wpcpm-details__outcome">%s</p>',
			esc_html(
				'' === $note
					? __( 'An Administrator did not make the change you asked for.', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the note an Administrator left when they declined a school's request to correct its details. */
						__( 'An Administrator did not make the change you asked for: %s', 'wpcredits-program-manager' ),
						$note
					)
			)
		);
	}

	/**
	 * One group's disclosure, with its form.
	 *
	 * Closed, and open only for a form a refusal has given its typing back to. The form is a column of the
	 * application's own questions, each with the label, the help and the limit the application gives it.
	 *
	 * A form that saves at once reads as one: the first group's for everybody, and the second group's for
	 * an Administrator, who checks it. Only the school's own second form reads as a request.
	 *
	 * `novalidate`, since the handlers hold every rule and say what is wrong in the application's words: a
	 * box the browser would refuse, such as a stored contact email the base holds two addresses in, would
	 * otherwise stop a post of the rest before the rule that a box posted back as drawn is no change applies.
	 *
	 * @param string $record   Institutions record ID.
	 * @param string $group    `GROUP_NOW` or `GROUP_CHECKED`.
	 * @param array  $current  What the program has on record.
	 * @param array  $typed    What a refused post kept, by key, or none.
	 * @param array  $problems What was wrong with a refused post's fields, by key.
	 * @param bool   $checker  Whether the reader is an Administrator, whose second group is saved at once.
	 */
	private static function render_form( $record, $group, array $current, array $typed, array $problems, $checker ) {
		$now   = WPCPM_Institution_Details::GROUP_NOW === $group;
		$saves = $now || $checker;

		printf(
			'<details class="wpcpm-details__more" id="wpcpm-details-%1$s"%2$s><summary class="wpcpm-details__toggle">%3$s <span class="wpcpm-details__when">%4$s</span></summary>',
			esc_attr( $group ),
			array() !== $typed ? ' open' : '',
			$saves ? esc_html__( 'Correct these', 'wpcredits-program-manager' ) : esc_html__( 'Ask to correct these', 'wpcredits-program-manager' ),
			$saves ? esc_html__( '(saved at once)', 'wpcredits-program-manager' ) : esc_html__( '(an Administrator checks first)', 'wpcredits-program-manager' )
		);

		$action = $now ? self::ACTION_SAVE : self::ACTION_ASK;

		printf(
			'<form class="wpcpm-details__form" method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			$saves ? esc_attr__( 'Saving', 'wpcredits-program-manager' ) : esc_attr__( 'Sending', 'wpcredits-program-manager' )
		);
		wp_nonce_field( $action . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record ) );

		if ( ! $now && $checker ) {
			printf(
				'<p class="wpcpm-details__note">%s</p>',
				esc_html__( 'You are an Administrator, so these are saved at once and nobody approves them.', 'wpcredits-program-manager' )
			);
		}

		$questions = WPCPM_Institution_Application::fields();

		foreach ( WPCPM_Institution_Details::fields() as $key => $spec ) {
			if ( $group !== $spec['group'] ) {
				continue;
			}

			self::render_field(
				$key,
				$spec['type'],
				$questions[ WPCPM_Institution_Details::question( $key ) ],
				$current[ $key ],
				isset( $typed[ $key ] ) ? $typed[ $key ] : null,
				isset( $problems[ $key ] ) ? (string) $problems[ $key ] : ''
			);
		}

		printf(
			'<p class="wpcpm-details__actions"><button type="submit" class="wpcpm-button">%s</button></p>',
			$saves ? esc_html__( 'Save the details', 'wpcredits-program-manager' ) : esc_html__( 'Send to an Administrator', 'wpcredits-program-manager' )
		);

		echo '</form></details>';
	}

	/**
	 * One question of a form, drawn as the application draws it.
	 *
	 * The label, the "Required" mark, the help and the limit are the application's; the box is drawn with
	 * the value the program has, or with what a refused post typed, exactly: a typed text is drawn back as
	 * the person typed it (`WPCPM_Typed_Text`), so a box posted back unedited comes back as it was.
	 * A list of languages is boxes in columns and the country is the one type-to-find field.
	 *
	 * @param string            $key      The detail's key.
	 * @param string            $type     Its type: `text`, `url`, `email`, `country` or `choices`.
	 * @param array             $question The application's question for it.
	 * @param string|string[]   $value    What the program has on record.
	 * @param string|array|null $typed    What a refused post typed for it, or null.
	 * @param string            $problem  What was wrong with it, or ''.
	 */
	private static function render_field( $key, $type, array $question, $value, $typed, $problem ) {
		$id       = 'wpcpm-details-' . $key;
		$name     = self::FIELD_PREFIX . $key;
		$required = ! empty( $question['required'] );
		$mark     = $required ? ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>' : '';
		$says     = array();

		if ( ! empty( $question['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';
		$invalid     = '' !== $problem ? ' aria-invalid="true"' : '';
		$wrapper     = in_array( $type, array( 'choices', 'country' ), true ) ? 'div' : 'p';

		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s%4$s">',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			! empty( $question['columns'] ) ? ' wpcpm-field--columns' : '',
			'' !== $problem ? ' has-problem' : ''
		);

		if ( 'choices' === $type ) {
			$picked = is_array( $typed ) ? $typed : (array) $value;

			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $question['label'] ), $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			printf( '<span class="wpcpm-field__choices" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( isset( $question['choices'] ) ? (array) $question['choices'] : array() as $choice ) {
				printf(
					'<label class="wpcpm-field__check"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> <span>%4$s</span></label>',
					esc_attr( $name ),
					esc_attr( $choice ),
					checked( in_array( $choice, $picked, true ), true, false ),
					esc_html( trim( $choice ) )
				);
			}

			echo '</span>';
		} elseif ( 'country' === $type ) {
			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					'options'     => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + WPCPM_Countries::options(),
					'current'     => is_string( $typed ) ? $typed : (string) $value,
					'label'       => $question['label'],
					'mark'        => $required ? __( 'Required', 'wpcredits-program-manager' ) : '',
					'describedby' => implode( ' ', $says ),
					'invalid'     => '' !== $problem,
					/* translators: Shown in the empty country field, which takes typing. */
					'find'        => __( 'Type to find your country', 'wpcredits-program-manager' ),
					'none'        => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'       => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
				)
			);
		} else {
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

			printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $question['label'] ), $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			printf(
				'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s%9$s%10$s />',
				'email' === $type ? 'email' : 'text',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $shown ),
				// A text the program holds longer than its box is drawn with room for all of it.
				(int) ( 'text' === $type ? WPCPM_Typed_Text::drawn_limit( (string) $value, $limit ) : $limit ),
				'url' === $type ? ' inputmode="url"' : '',
				'email' === $type ? ' inputmode="email"' : '',
				isset( $question['auto'] ) ? ' autocomplete="' . esc_attr( $question['auto'] ) . '"' : '',
				$required ? ' required="required"' : '',
				$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
			);
		}

		if ( ! empty( $question['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s-help">%2$s</span>', esc_attr( $id ), esc_html( $question['help'] ) );
		}

		if ( '' !== $problem ) {
			printf( '<span class="wpcpm-field__problem" id="%1$s-problem">%2$s</span>', esc_attr( $id ), esc_html( $problem ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
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
	 * @return array{message: array{0: string, 1: string}|null, group: string, problems: array<string, string>}
	 */
	private static function take_flash( $record ) {
		$out   = array(
			'message'  => null,
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
	 * space, since the box is drawn that way (`render_field()`). The test of whether a box posted back
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
	}

	/**
	 * Flash the outcome and go back to the card.
	 *
	 * **This does not return.** Every call to it ends the request, which is why a refusal above reads as one
	 * line and not as an early return with a branch around it. The destination is rebuilt here and not taken
	 * from the request: the Institution Dashboard, and for an Administrator the school they were viewing,
	 * through the switcher's argument, since the page would otherwise open on the first school with a member.
	 *
	 * @param string $record Institutions record ID the outcome is about.
	 * @param string $status A key of `messages()`, or `said` with the sentence in `$extra['text']`.
	 * @param array  $extra  `group` and `problems` of a refused post, or `text`.
	 */
	private static function finish( $record, $status, array $extra = array() ) {
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

		$url = (string) WPCPM_Institutions_Dashboard::page_url();
		$url = '' !== $url ? $url : home_url( '/' );

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url );
		}

		wp_safe_redirect( $url . '#' . self::ANCHOR );
		exit;
	}
}
