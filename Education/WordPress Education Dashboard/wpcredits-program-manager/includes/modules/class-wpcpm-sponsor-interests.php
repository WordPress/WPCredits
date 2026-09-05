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
			'interest-sent'    => array( 'success', __( 'Thank you. Your program contact has been told, and your interest is on record.', 'wpcredits-program-manager' ) ),
			'interest-unsent'  => array( 'warning', __( 'Your interest is on record, but nobody at the program could be told right now. Write to your program contact as well.', 'wpcredits-program-manager' ) ),
			'interest-empty'   => array( 'error', __( 'Tick at least one option, name an event or write a note.', 'wpcredits-program-manager' ) ),
			'interest-ceiling' => array( 'error', __( 'Five a day is the limit. Try again tomorrow.', 'wpcredits-program-manager' ) ),
			'interest-failed'  => array( 'error', __( 'The program records could not be updated right now. Try again later.', 'wpcredits-program-manager' ) ),
			'refused'          => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
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

		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING, (string) $viewer->ID ), self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::leave( 'interest-ceiling', $record );
		}

		$posted  = isset( $_POST['wpcpm_support'] ) && is_array( $_POST['wpcpm_support'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpcpm_support'] ) ) : array();
		$choices = array_values( array_intersect( self::CHOICES, $posted ) );
		$events  = array();

		// `posted_lines()` keeps a posted value's line breaks rather than collapsing them, but
		// hands back one string (see its docblock in includes/class-wpcpm-request.php) - every
		// other caller keeps it as one block of text. This is the one field in the plugin that
		// wants a list of individual lines, so the split happens here rather than being assumed.
		foreach ( array_slice( preg_split( '/\r\n|\r|\n/', WPCPM_Request::posted_lines( 'wpcpm_events' ) ), 0, self::MAX_EVENTS ) as $event ) {
			$event = mb_substr( sanitize_text_field( $event ), 0, self::MAX_EVENT_LEN );

			if ( '' !== $event ) {
				$events[] = $event;
			}
		}

		$note = mb_substr( sanitize_textarea_field( WPCPM_Request::posted_text( 'wpcpm_note' ) ), 0, self::MAX_TEXT );

		if ( empty( $choices ) && empty( $events ) && '' === $note ) {
			self::leave( 'interest-empty', $record );
		}

		$line = self::line( $choices, $events, $note, $viewer->display_name, wp_date( 'Y-m-d' ) );

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
		$build   = static function ( $user ) use ( $sponsor, $line ) {
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
					$line
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
	 * @param string   $record Sponsor record ID.
	 * @param callable $build  The mail builder `WPCPM_Mail::send()` takes.
	 * @return int How many were sent.
	 */
	public static function mail_manager( $record, $build ) {
		$manager = WPCPM_Sponsors_Index::manager_of( $record );

		if ( is_array( $manager ) && is_email( $manager['email'] ) ) {
			$user = get_user_by( 'email', $manager['email'] );

			if ( $user instanceof WP_User && $user->exists() ) {
				return WPCPM_Mail::send( $user, self::MAIL_CONTEXT, $build ) ? 1 : 0;
			}

			return WPCPM_Mail::send_to( $manager['email'], self::MAIL_CONTEXT, $build ) ? 1 : 0;
		}

		return (int) WPCPM_Institutions::notify_managers( self::MAIL_CONTEXT, $build, 'sponsor_notify' );
	}

	/**
	 * The card.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$row  = WPCPM_Sponsors_Index::row( $record );
		$row  = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$open = isset( $context['open'] ) && self::CARD === $context['open'];

		printf( '<details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure wpcpm-sponsor__card"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
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
				checked( in_array( $choice, (array) $row['support'], true ), true, false )
			);
		}

		echo '</fieldset>';
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-interest-events">%1$s</label><textarea id="wpcpm-interest-events" name="wpcpm_events" rows="3" placeholder="%2$s"></textarea><span class="wpcpm-student__note">%3$s</span></p>',
			esc_html__( 'Flagship events you would sponsor students to attend', 'wpcredits-program-manager' ),
			esc_attr__( 'WordCamp Europe 2027', 'wpcredits-program-manager' ),
			esc_html__( 'One per line.', 'wpcredits-program-manager' )
		);
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-interest-note">%1$s</label><textarea id="wpcpm-interest-note" name="wpcpm_note" rows="4" maxlength="%2$d"></textarea></p>',
			esc_html__( 'Anything else', 'wpcredits-program-manager' ),
			(int) self::MAX_TEXT
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

		echo '</div></details>';
	}

	/**
	 * Flash and go back to the dashboard, through the dashboard's own method (see the profile card).
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, or ''.
	 */
	private static function leave( $status, $record ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record );
		exit;
	}
}
