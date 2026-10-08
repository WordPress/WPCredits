<?php
/**
 * The Sponsor Dashboard's interests card: what else a sponsor would like to support.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Six checkboxes, a list of events, a note: written to the base, mailed to the assigned
 * manager, logged, and kept as history in the base's own `Sponsorship interests` column so
 * the grid holds it without the site owning it (design spec of 4 September 2026, section
 * 5.7). Ceiling five a day per account.
 */
final class WPCPM_Sponsor_Interests {

	const ACTION_SAVE   = 'wpcpm_sponsor_interest';
	const CARD          = 'interests';
	const FIELD_SUPPORT = 'How would you like to support WP Credits?';
	const FIELD_LOG     = 'Sponsorship interests';
	const PER_DAY       = 5;
	const CEILING       = 'sponsor-interest';
	const MAIL_CONTEXT  = 'sponsor-interest';
	const LOG_KIND      = 'sponsor_interest';
	const MAX_TEXT      = 4000;
	const MAX_EVENTS    = 10;
	const MAX_EVENT_LEN = 120;

	/** The events box holds ten events of the longest a line may be, and the nine line breaks between them. */
	const MAX_EVENTS_BOX = self::MAX_EVENTS * self::MAX_EVENT_LEN + self::MAX_EVENTS - 1;

	/** The multiple select's choices, as the base spells them. */
	const CHOICES = array(
		'Provide financial support (for program costs)',
		'Sponsor a member of the WP Credits admin team',
		'Sponsor a mentor or multiple mentors',
		'Sponsor a scholarship for students to attend flagship events',
		'Sponsor tools or services',
		'Other (please specify)',
	);

	/**
	 * The handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * This card's outcomes.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			// The sentence after it is the whole message: `WPCPM_Typed_Text::loss_message()` says that
			// nothing was sent, which box, and how to keep every word.
			'interest-loss'    => array( 'error', '' ),
			'interest-sent'    => array( 'success', __( 'Thank you. Your program contact has been told, and your interest is on record.', 'wpcredits-program-manager' ) ),
			'interest-unsent'  => array( 'warning', __( 'Your interest is on record, but nobody at the program could be told right now. Write to your program contact as well.', 'wpcredits-program-manager' ) ),
			'interest-empty'   => array( 'error', __( 'Tick at least one option, name an event or write a note.', 'wpcredits-program-manager' ) ),
			// The sentence after it says how far over the note is (long_sentence()).
			'interest-long'    => array( 'error', __( 'Nothing was sent.', 'wpcredits-program-manager' ) ),
			'interest-ceiling' => array( 'error', __( 'Five a day is the limit. Try again tomorrow.', 'wpcredits-program-manager' ) ),
			'interest-failed'  => array( 'error', __( 'The program records could not be updated right now. Try again later.', 'wpcredits-program-manager' ) ),
			'refused'          => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The words the two typed boxes' labels show, so that a refusal names a box as its form does.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'events' => __( 'Flagship events you would sponsor students to attend', 'wpcredits-program-manager' ),
			'note'   => __( 'Anything else', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The form, as `WPCPM_Typed_Text::keep()` names it: one sponsor's interests card.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	private static function typed_form( $record ) {
		return 'interests:' . $record;
	}

	/**
	 * Keep what a refused message held for the one time the form is drawn again: the two boxes as
	 * typed, each up to its limit (both are drawn empty), and the choices as ticked.
	 *
	 * @param string   $record  Sponsor record ID.
	 * @param array    $typed   `events` and `note`, as posted and not yet cleaned.
	 * @param string[] $choices The ticked choices, matched against `CHOICES`.
	 */
	private static function keep_typing( $record, array $typed, array $choices ) {
		WPCPM_Typed_Text::keep(
			self::typed_form( $record ),
			$typed + array( 'support' => $choices ),
			array(
				'events' => WPCPM_Typed_Text::kept_room( '', self::MAX_EVENTS_BOX ),
				'note'   => WPCPM_Typed_Text::kept_room( '', self::MAX_TEXT ),
			)
		);
	}

	/**
	 * The dated line appended to the base's history.
	 *
	 * @param string[] $choices The ticked choices.
	 * @param string[] $events  The events named.
	 * @param string   $note    The note.
	 * @param string   $who     The display name of the person.
	 * @param string   $date    `Y-m-d`.
	 * @return string
	 */
	public static function line( array $choices, array $events, $note, $who, $date ) {
		$parts = array();

		if ( ! empty( $choices ) ) {
			$parts[] = implode( '; ', $choices );
		}

		if ( ! empty( $events ) ) {
			$parts[] = 'events: ' . implode( ', ', $events );
		}

		if ( '' !== trim( (string) $note ) ) {
			$parts[] = 'note: ' . trim( (string) $note );
		}

		return $date . ' by ' . $who . ': ' . implode( '; ', $parts );
	}

	/**
	 * Save an interest.
	 */
	public static function handle_save() {
		check_admin_referer( self::ACTION_SAVE );

		$claim = WPCPM_Sponsor_Roster::claim( WPCPM_Request::posted_text( 'wpcpm_sponsor' ), WPCPM_Sponsor_Policy::ACT_EXPRESS_INTEREST );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];

		// An index the site has not read cannot be written back to: the PATCH below would be
		// built on nothing (an empty row's blank history), and the append would replace
		// whatever real history the base holds with one line. A member whose sponsor left the
		// index still claims (the stamp is well-formed and ungated), so this is the one place
		// that can catch it.
		if ( ! is_array( $claim['row'] ) ) {
			self::leave( 'interest-failed', $claim['record'] );
		}

		$row    = $claim['row'];
		$viewer = wp_get_current_user();

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( self::typed_form( $record ) );

		$posted  = isset( $_POST['wpcpm_support'] ) && is_array( $_POST['wpcpm_support'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpcpm_support'] ) ) : array();
		$choices = array_values( array_intersect( self::CHOICES, $posted ) );
		$typed   = array(
			'events' => WPCPM_Request::posted_raw( 'wpcpm_events' ),
			'note'   => WPCPM_Request::posted_raw( 'wpcpm_note' ),
		);

		// A typing the cleaner would take words from is refused before anything else is asked of it,
		// and the form gets back what was typed: sent, it would lose the words without a word said.
		// Like every refusal here, it comes before the ceiling is claimed.
		foreach ( $typed as $key => $value ) {
			if ( WPCPM_Typed_Text::cleaner_loses( $value, 'lines' ) ) {
				self::keep_typing( $record, $typed, $choices );
				self::leave( 'interest-loss', $record, WPCPM_Typed_Text::loss_message( self::labels()[ $key ], 'send' ) );
			}
		}

		// Anything else and the events are counted as the person typed them and refused rather
		// than cut: a note cut short reaches the program missing its end, an event cut short names
		// another event, and one past the tenth was dropped without a word. Read and refused before
		// the ceiling is claimed, so a message sent back to be shortened costs none of the day's five,
		// and the form comes back with what was typed.
		$note = WPCPM_Request::posted_lines( 'wpcpm_note' );
		$over = WPCPM_Typed_Text::typed_length( $note ) - self::MAX_TEXT;

		if ( $over > 0 ) {
			self::keep_typing( $record, $typed, $choices );
			self::leave( 'interest-long', $record, self::long_sentence( $over ) );
		}

		$events = array();

		// `posted_lines()` keeps a posted value's line breaks rather than collapsing them, but
		// hands back one string (see its docblock in includes/class-wpcpm-request.php) - every
		// other caller keeps it as one block of text. This is the one field in the plugin that
		// wants a list of individual lines, so the split happens here rather than being assumed.
		// A blank line names no event and counts for none.
		foreach ( preg_split( '/\r\n|\r|\n/', WPCPM_Request::posted_lines( 'wpcpm_events' ) ) as $event ) {
			$event = sanitize_text_field( $event );

			if ( '' === $event ) {
				continue;
			}

			$events[] = $event;
			$over     = WPCPM_Typed_Text::typed_length( $event ) - self::MAX_EVENT_LEN;

			if ( $over > 0 ) {
				self::keep_typing( $record, $typed, $choices );
				self::leave( 'interest-long', $record, self::event_sentence( count( $events ), $over ) );
			}
		}

		if ( count( $events ) > self::MAX_EVENTS ) {
			self::keep_typing( $record, $typed, $choices );
			self::leave( 'interest-long', $record, self::events_sentence( count( $events ) ) );
		}

		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING, (string) $viewer->ID ), self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::leave( 'interest-ceiling', $record );
		}

		if ( empty( $choices ) && empty( $events ) && '' === $note ) {
			self::leave( 'interest-empty', $record );
		}

		// Two forms of the one dated line. The history holds one line per message, and the card
		// lists it line by line, so there the note's white space is folded as the one-line
		// cleaner folds it (each run of line breaks, tabs and spaces one space), and the text is
		// kept as the cleaner stored it, as every line before it was. The mail carries the note as
		// it was typed: its lines, and the characters the cleaner wrote as entities.
		$date = wp_date( 'Y-m-d' );
		$line = self::line( $choices, $events, (string) preg_replace( '/[\r\n\t ]+/', ' ', $note ), $viewer->display_name, $date );
		$said = WPCPM_Typed_Text::mail_text( self::line( $choices, $events, $note, $viewer->display_name, $date ) );

		$table    = (string) WPCPM_Settings::get_value( 'sponsors_table', '' );
		$airtable = new WPCPM_Airtable();
		$live     = $airtable->get_record( $table, $record );

		// A stale index, or a manager's own edit in the Airtable grid since the last sync,
		// would otherwise be overwritten by the append below: the base's current value is read
		// right before the PATCH is built, so the index's copy is no longer the source for the
		// append. A history that cannot even be read is a history that must not be guessed at.
		if ( is_wp_error( $live ) ) {
			self::leave( 'interest-failed', $record );
		}

		$history = ( is_array( $live ) && isset( $live['fields'] ) )
			? trim( (string) WPCPM_Airtable::flatten( isset( $live['fields'][ self::FIELD_LOG ] ) ? $live['fields'][ self::FIELD_LOG ] : '' ) )
			: '';
		$cells   = array( self::FIELD_LOG => '' === $history ? $line : $history . "\n" . $line );

		// The multiple select is written only when something was ticked: unticking everything
		// is not a way to erase what the base already holds.
		if ( ! empty( $choices ) ) {
			$cells[ self::FIELD_SUPPORT ] = $choices;
		}

		$written = $airtable->update_records(
			$table,
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			self::leave( 'interest-failed', $record );
		}

		$patch = array( 'interests' => $cells[ self::FIELD_LOG ] );

		if ( ! empty( $choices ) ) {
			$patch['support'] = $choices;
		}

		WPCPM_Sponsors_Index::patch( $record, $patch );

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function ( $user ) use ( $sponsor, $said ) {
			return array(
				'subject' => sprintf(
					/* translators: %s: company name. */
					__( '[WordPress Credits] %s would like to support the program', 'wpcredits-program-manager' ),
					$sponsor
				),
				'body'    => sprintf(
					/* translators: 1: company name, 2: the dated line. */
					__( "%1\$s said on the Sponsor Dashboard:\n\n%2\$s\n\nThe full history is in the Sponsorship interests column of the Sponsors table.", 'wpcredits-program-manager' ),
					$sponsor,
					$said
				),
			);
		};

		$mailed = self::mail_manager( $record, $build );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_KIND,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => (int) $viewer->ID,
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $line,
				'data'     => array(
					'choices' => $choices,
					'events'  => count( $events ),
					'mailed'  => (int) $mailed,
				),
			)
		);

		// Nobody may be reachable: no manager assigned, an empty sponsor_notify setting, and
		// no account holding the manage capability. The interest is still on record, but the
		// sentence told is the true one - it never claims a mailbox that never fired.
		self::leave( $mailed > 0 ? 'interest-sent' : 'interest-unsent', $record );
	}

	/**
	 * Mail the sponsor's assigned program manager, or every manager when none is assigned.
	 *
	 * @param string   $record  Sponsor record ID.
	 * @param callable $build   The mail builder `WPCPM_Mail::send()` takes.
	 * @param string   $context The mail log's context; this card's own by default. The low-stock
	 *                          and claim-problem mails (WPCPM_Sponsor_Claims) route through here
	 *                          with their own, so every mail to a sponsor's manager takes the one
	 *                          road: the assigned manager, else `sponsor_notify`, else every manager.
	 * @return int How many were sent.
	 */
	public static function mail_manager( $record, $build, $context = self::MAIL_CONTEXT ) {
		$manager = WPCPM_Sponsors_Index::manager_of( $record );

		if ( is_array( $manager ) && is_email( $manager['email'] ) ) {
			$user = get_user_by( 'email', $manager['email'] );

			if ( $user instanceof WP_User && $user->exists() ) {
				return WPCPM_Mail::send( $user, $context, $build ) ? 1 : 0;
			}

			return WPCPM_Mail::send_to( $manager['email'], $context, $build ) ? 1 : 0;
		}

		return (int) WPCPM_Institutions::notify_managers( $context, $build, 'sponsor_notify' );
	}

	/**
	 * The card.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$open   = isset( $context['open'] ) && self::CARD === $context['open'];
		$labels = self::labels();
		// A refused message: its boxes and its choices are drawn as they were left.
		$kept   = WPCPM_Typed_Text::kept( self::typed_form( $record ) );
		$ticked = isset( $kept['support'] ) && is_array( $kept['support'] ) ? $kept['support'] : (array) $row['support'];
		$typed  = static function ( $key ) use ( $kept ) {
			return isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? $kept[ $key ] : '';
		};

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'What else would you like to support?', 'wpcredits-program-manager' )
		);
		echo '<div class="wpcpm-group__body">';

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );

		echo '<fieldset class="wpcpm-sponsor__choices"><legend>' . esc_html__( 'Ways to support the program', 'wpcredits-program-manager' ) . '</legend>';

		foreach ( self::CHOICES as $i => $choice ) {
			printf(
				'<label><input type="checkbox" name="wpcpm_support[]" value="%1$s"%2$s /> %1$s</label>',
				esc_attr( $choice ),
				checked( in_array( $choice, $ticked, true ), true, false )
			);
		}

		echo '</fieldset>';
		// One box of lines, so its maxlength bounds the whole: ten events of the longest a line may
		// be, and the nine line breaks between them, each one character to the browser. Drawn empty,
		// so the room is that, and so it is when it is drawn with what a refused message kept. A line
		// over its limit, or an eleventh, is refused on the way in. The parser drops one line feed right
		// after a text area's opening tag, so a kept typing, which can begin with a line break, is given
		// one of its own first; so is the note's below.
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-interest-events">%1$s</label><textarea id="wpcpm-interest-events" name="wpcpm_events" rows="3" maxlength="%4$d" placeholder="%2$s">%6$s%5$s</textarea><span class="wpcpm-student__note">%3$s</span></p>',
			esc_html( $labels['events'] ),
			esc_attr__( 'WordCamp Europe 2027', 'wpcredits-program-manager' ),
			esc_html(
				sprintf(
					/* translators: 1: the most events one message may name, 2: the longest an event may be, in characters. */
					__( 'One per line: up to %1$s events of up to %2$s characters each.', 'wpcredits-program-manager' ),
					number_format_i18n( self::MAX_EVENTS ),
					number_format_i18n( self::MAX_EVENT_LEN )
				)
			),
			(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_EVENTS_BOX ),
			esc_textarea( $typed( 'events' ) ),
			isset( $kept['events'] ) ? "\n" : ''
		);
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-interest-note">%1$s</label><textarea id="wpcpm-interest-note" name="wpcpm_note" rows="4" maxlength="%2$d">%4$s%3$s</textarea></p>',
			esc_html( $labels['note'] ),
			// Drawn empty, so the room is the limit: nothing in it is held as an entity. A refused
			// message's note is drawn as it was typed, in the same room.
			(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_TEXT ),
			esc_textarea( $typed( 'note' ) ),
			isset( $kept['note'] ) ? "\n" : ''
		);
		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Tell the program', 'wpcredits-program-manager' ) );
		echo '</form>';

		$history = trim( (string) $row['interests'] );

		if ( '' !== $history ) {
			echo '<h4 class="wpcpm-sponsor__subheading">' . esc_html__( 'What you have told us so far', 'wpcredits-program-manager' ) . '</h4>';
			echo '<ul class="wpcpm-sponsor__history">';

			foreach ( array_reverse( preg_split( '/\r\n|\r|\n/', $history ) ) as $entry ) {
				if ( '' !== trim( $entry ) ) {
					echo '<li>' . esc_html( trim( $entry ) ) . '</li>';
				}
			}

			echo '</ul>';
		}

		echo '</div></details></section>';
	}

	/**
	 * The sentence after `interest-long`: how far over its limit the note is, so the sponsor knows
	 * how much to take out.
	 *
	 * @param int $over How many characters, as typed, the note is over `MAX_TEXT`.
	 * @return string
	 */
	private static function long_sentence( $over ) {
		$over = max( 1, (int) $over );

		/* translators: 1: how many characters over, 2: the longest note allowed. */
		return sprintf( _n( 'Your note is %1$s character over the limit of %2$s. Shorten it and send it again.', 'Your note is %1$s characters over the limit of %2$s. Shorten it and send it again.', $over, 'wpcredits-program-manager' ), number_format_i18n( $over ), number_format_i18n( self::MAX_TEXT ) );
	}

	/**
	 * The sentence after `interest-long` for an event over its limit: which one, counted among the
	 * events named, and how far over it is.
	 *
	 * @param int $which The event's place among those named, from 1.
	 * @param int $over  How many characters, as typed, it is over `MAX_EVENT_LEN`.
	 * @return string
	 */
	private static function event_sentence( $which, $over ) {
		$over = max( 1, (int) $over );

		/* translators: 1: the event's place among those named, 2: how many characters over, 3: the longest an event may be. */
		return sprintf( _n( 'Event %1$s is %2$s character over the limit of %3$s. Shorten it and send it again.', 'Event %1$s is %2$s characters over the limit of %3$s. Shorten it and send it again.', $over, 'wpcredits-program-manager' ), number_format_i18n( (int) $which ), number_format_i18n( $over ), number_format_i18n( self::MAX_EVENT_LEN ) );
	}

	/**
	 * The sentence after `interest-long` for more events than one message may name.
	 *
	 * @param int $named How many events were named.
	 * @return string
	 */
	private static function events_sentence( $named ) {
		/* translators: 1: how many events were named, 2: the most one message may name. */
		return sprintf( _n( 'You named %1$s event, and the limit is %2$s. Name the others in another message.', 'You named %1$s events, and the limit is %2$s. Name the others in another message.', (int) $named, 'wpcredits-program-manager' ), number_format_i18n( (int) $named ), number_format_i18n( self::MAX_EVENTS ) );
	}

	/**
	 * Flash and go back to the dashboard, through the dashboard's own method (see the profile card).
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, or ''.
	 * @param string $detail A sentence after the status's own, or ''.
	 */
	private static function leave( $status, $record, $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail );
		exit;
	}
}
