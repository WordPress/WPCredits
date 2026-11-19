<?php
/**
 * The Talks view of the Administrator Dashboard (Institutions off Airtable, release 2, Task 16).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the Administrators follow every school's talks once the site keeps the record of institutions: a view of the
 * Administrator Dashboard's frame, at `?wpcpm_view=talks`, in the menu after Watch while `shown()`.
 *
 * **The view:** its head says how many follow-ups are due, once, in words, for the schools shown (Mine, the default for
 * every viewer, or All: one subtle control at the head's top right), and the oldest, with one action, Add a lead; then
 * Find, directly over what it filters; the follow-ups due (a table, oldest first, never cut, the one place due is said)
 * and the schools by stage, Lead to Confirmed, each an h2 over its list with its count in words; "No stage" after them,
 * open; Revisit Later, Not Moving Forward, SPAM and any stage the site's lists do not hold folded; last, the Add a lead
 * fold. A stage over `SHOWN` rows shows that many and one quiet "Show all N". Student is not a group (the approved design
 * draws Lead to Confirmed), and a closed lead is in no group: Find finds both, in all the talks.
 *
 * **A lead's page** (`ARG_LEAD`): the frame's head with the lead's name as the view's title, then its Talks card
 * (`WPCPM_Institution_Talks::render()`, without the name). A lead that became an institution links to its Talks card.
 * The menu is the way back.
 *
 * What is due is `WPCPM_Institution_Talks::due()`'s (one list of stages, `DUE_STAGES`); the menu's count is the
 * viewer's own (`due_count()`).
 */
final class WPCPM_Institution_Talks_View {

	/** Whose schools the view shows: `mine` or `all`. */
	const ARG_WHO = 'wpcpm_talks_who';

	/** Find: words a school's name holds. */
	const ARG_FIND = 'wpcpm_talks_find';

	/** A lead's page. */
	const ARG_LEAD = WPCPM_Institution_Talks::ARG_LEAD;

	/** The stage group shown whole, by its slug (`slug()`). */
	const ARG_WHOLE = 'wpcpm_talks_whole';

	/** The rows a stage group shows before its "Show all N". */
	const SHOWN = 20;

	/** Mine. */
	const MINE = 'mine';

	/** All. */
	const ALL = 'all';

	/**
	 * Every school's talks, read once for the request (Task 16 fix round 1, G2): the menu's count and the view share
	 * them. The facts are the site's, the same for every reader; who is shown is decided after, for each viewer.
	 *
	 * @var array|null
	 */
	private static $facts = null;

	/**
	 * How many times the facts were read, for the suites.
	 *
	 * @var int
	 */
	private static $reads = 0;

	/**
	 * Every school's talks, read once per request.
	 *
	 * @return array<string, array> `WPCPM_Institution_Talks::all_facts()`, each without its `program_details`.
	 */
	public static function facts() {
		if ( null === self::$facts ) {
			self::$facts = array();

			// Everything but the Program details, which only the Talks card prints.
			foreach ( WPCPM_Institution_Talks::all_facts() as $key => $one ) {
				unset( $one['program_details'] );
				self::$facts[ $key ] = $one;
			}

			++self::$reads;
		}

		return self::$facts;
	}

	/**
	 * How many times `facts()` read the site.
	 *
	 * @return int
	 */
	public static function reads() {
		return self::$reads;
	}

	/**
	 * Forget the facts read, so the next `facts()` reads the site again (a suite that changes the site between drawings).
	 */
	public static function forget() {
		self::$facts = null;
	}

	/**
	 * Whether the view is the page's: only once the site keeps the record of institutions.
	 *
	 * @return bool
	 */
	public static function shown() {
		return WPCPM_Institutions_Index::on_site();
	}

	/**
	 * The schools a reader is shown, oldest follow-up first, then by name. With words to find: every school whose name
	 * holds them, without regard to case or accents, at any stage, a closed lead too, whoever it is assigned to. Without:
	 * every school but a closed lead, and for `mine` only the viewer's (`is_mine()`).
	 *
	 * @param array  $facts     `WPCPM_Institution_Talks::all_facts()`.
	 * @param string $who       `mine` or `all`.
	 * @param int    $viewer_id The viewer.
	 * @param string $find      Words to find, or ''.
	 * @return array[] The schools' facts.
	 */
	public static function rows( array $facts, $who, $viewer_id, $find ) {
		$find = self::fold( $find );
		$out  = array();

		foreach ( $facts as $one ) {
			if ( ! is_array( $one ) || ! isset( $one['key'] ) ) {
				continue;
			}

			// A school's Program details are the Talks card's alone: no row, due table or email holds them.
			unset( $one['program_details'] );

			if ( '' !== $find ) {
				if ( false !== strpos( self::fold( (string) $one['name'] ), $find ) ) {
					$out[] = $one;
				}

				continue;
			}

			if ( ! empty( $one['closed'] ) ) {
				continue;
			}

			if ( self::MINE === $who && ! self::is_mine( $one, (int) $viewer_id ) ) {
				continue;
			}

			$out[] = $one;
		}

		usort( $out, array( 'WPCPM_Institution_Talks', 'by_follow_up' ) );

		return $out;
	}

	/**
	 * The menu's count: the follow-ups due of the viewer's own schools (Mine), 0 outside site mode.
	 *
	 * @param int $viewer_id The viewer.
	 * @return int
	 */
	public static function due_count( $viewer_id ) {
		if ( ! self::shown() ) {
			return 0;
		}

		return count( self::due_for( $viewer_id ) );
	}

	/**
	 * The viewer's own follow-ups due (Mine), oldest first: what the menu's count counts and what the Monday email lists
	 * (Task 17), from the one rule of Mine (`is_mine()`) and the one list of what is due (`WPCPM_Institution_Talks::due()`).
	 *
	 * @param int    $viewer_id The viewer.
	 * @param string $today     The day, `Y-m-d`; '' for today in the site's time.
	 * @return array[] The schools' facts.
	 */
	public static function due_for( $viewer_id, $today = '' ) {
		return WPCPM_Institution_Talks::due( self::rows( self::facts(), self::MINE, (int) $viewer_id, '' ), '' !== (string) $today ? (string) $today : self::today() );
	}

	/**
	 * Who a school is assigned to, as the view counts it (`assignee()`): the Monday email tells a recipient's own
	 * schools from the not assigned ones in their Mine by it.
	 *
	 * @param array $facts `facts()`.
	 * @return int A user ID, 0 for nobody.
	 */
	public static function assignee_of( array $facts ) {
		return self::assignee( $facts );
	}

	/**
	 * Draw the view, or a lead's page when one is asked for.
	 *
	 * @param WP_User $viewer The Administrator reading it.
	 * @param array   $frame  What the dashboard gives the view: `page_name` (the page's H1, escaped markup, or '') and
	 *                        `messages` (a callable that prints the page's outcomes and the card slot, after the head).
	 */
	public static function render( WP_User $viewer, array $frame = array() ) {
		$frame = array_merge(
			array(
				'page_name' => '',
				'messages'  => null,
			),
			$frame
		);

		$lead = self::asked( self::ARG_LEAD );

		if ( '' !== $lead ) {
			self::render_lead( $lead, $frame );

			return;
		}

		$viewer_id = (int) $viewer->ID;
		$who       = self::who();
		$find      = self::asked( self::ARG_FIND, true );
		$facts     = self::facts();
		$shown     = self::rows( $facts, $who, $viewer_id, '' );
		$due       = WPCPM_Institution_Talks::due( $shown, self::today() );

		self::render_head( $due, $who, $find, (string) $frame['page_name'] );
		self::messages( $frame );

		// A lead deleted from its page lands here, and the view says so where it shows an outcome (X4).
		$deleted = WPCPM_Institution_Talks::take_deleted();

		if ( '' !== $deleted ) {
			echo WPCPM_Dashboard_Card::message( 'success', $deleted, 'wpcpm-talks__message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_Dashboard_Card::message().
		}

		// Find stands directly over what it filters; Add a lead is the view's last block, opened by the head's button.
		self::render_find( $who, $find );

		if ( '' !== self::fold( $find ) ) {
			self::render_found( self::rows( $facts, $who, $viewer_id, $find ) );
		} else {
			self::render_due( $due );
			self::render_stages( $shown, $who );
		}

		self::render_add();
	}

	/*
	 * The reader's choices
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whose schools are shown: as asked, else Mine, for every viewer, so the head's count and the menu's agree when the
	 * view opens (Task 16 fix round 1, V2).
	 *
	 * @return string `mine` or `all`.
	 */
	private static function who() {
		$asked = self::asked( self::ARG_WHO );

		if ( in_array( $asked, array( self::MINE, self::ALL ), true ) ) {
			return $asked;
		}

		return self::MINE;
	}

	/**
	 * A GET argument, a view to read, which changes nothing.
	 *
	 * @param string $name  The argument.
	 * @param bool   $words True for words a reader typed (Find), kept as typed but trimmed; else a key.
	 * @return string
	 */
	private static function asked( $name, $words = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		if ( ! isset( $_GET[ $name ] ) || ! is_scalar( $_GET[ $name ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; cleaned below, as words or as a key.
		$value = wp_unslash( (string) $_GET[ $name ] );

		if ( $words ) {
			return trim( sanitize_text_field( $value ) );
		}

		// A lead key keeps its hyphen; a key of anything else is refused by its reader.
		return trim( (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) );
	}

	/**
	 * Whether a school is the viewer's (Task 16 fix round 1, V3): assigned to them; or assigned to nobody (`assignee()`)
	 * and in their area; or assigned to nobody and in nobody's area (no country, or one no area holds), which is every
	 * Administrator's, so no school drops out of every Mine. The head's count and the menu's both read this.
	 *
	 * @param array $facts     `facts()`.
	 * @param int   $viewer_id The viewer.
	 * @return bool
	 */
	private static function is_mine( array $facts, $viewer_id ) {
		$assigned = self::assignee( $facts );

		if ( $assigned > 0 ) {
			return $assigned === (int) $viewer_id;
		}

		$holder = '' !== (string) $facts['country'] ? (int) WPCPM_Institution_Areas::administrator_for( (string) $facts['country'] ) : 0;

		return 0 === $holder || $holder === (int) $viewer_id;
	}

	/**
	 * Who a school is assigned to, as the view counts it: the stored account while it manages the program, else nobody (an
	 * account that no longer manages it is "Not assigned" everywhere in the view).
	 *
	 * @param array $facts `facts()`.
	 * @return int A user ID, 0 for nobody.
	 */
	private static function assignee( array $facts ) {
		$assigned = (int) $facts['assigned'];

		return $assigned > 0 && user_can( $assigned, WPCPM_Roles::CAP_MANAGE ) ? $assigned : 0;
	}

	/**
	 * Words as two are compared: accents taken off, lowercase, trimmed.
	 *
	 * @param mixed $text Words.
	 * @return string
	 */
	private static function fold( $text ) {
		$text = is_scalar( $text ) ? trim( (string) $text ) : '';
		$text = remove_accents( $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Today, in the site's time.
	 *
	 * @return string `Y-m-d`.
	 */
	private static function today() {
		return (string) wp_date( 'Y-m-d' );
	}

	/**
	 * The view's address with the reader's choices, and changes over them.
	 *
	 * @param array $args Arguments to set; '' removes one.
	 * @return string Not escaped.
	 */
	private static function url( array $args = array() ) {
		$url = (string) WPCPM_Administrators_Dashboard::view_url( WPCPM_Institution_Talks::VIEW_TALKS );

		foreach ( $args as $name => $value ) {
			if ( '' !== (string) $value ) {
				$url = (string) add_query_arg( $name, (string) $value, $url );
			}
		}

		return $url;
	}

	/**
	 * A stage's slug, for its anchor and `ARG_WHOLE`.
	 *
	 * @param string $stage A stage, '' for none.
	 * @return string
	 */
	private static function slug( $stage ) {
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( (string) $stage ) ) ), '-' );

		return '' !== $slug ? $slug : 'none';
	}

	/**
	 * Print the page's outcomes and the card slot, as the dashboard gave them.
	 *
	 * @param array $frame What `render()` was given.
	 */
	private static function messages( array $frame ) {
		if ( is_callable( $frame['messages'] ) ) {
			call_user_func( $frame['messages'] );
		}
	}

	/*
	 * The head
	 * --------------------------------------------------------------------
	 */

	/**
	 * The head: the page's name and, at its end, Mine or All; how many follow-ups are due in the serif; the oldest in
	 * words.
	 *
	 * @param array[] $due       The follow-ups due of the schools shown.
	 * @param string  $who       `mine` or `all`.
	 * @param string  $find      Words to find, kept for the switch.
	 * @param string  $page_name The page's H1, escaped, or ''.
	 */
	private static function render_head( array $due, $who, $find, $page_name ) {
		$n = count( $due );

		echo '<header class="wpcpm-head"><div class="wpcpm-head__copy">';
		echo WPCPM_UI::head_row( $page_name . self::who_control( $who, $find ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name() and who_control().

		if ( 0 === $n ) {
			printf( '<p class="wpcpm-head__title">%s</p>', esc_html__( 'No follow-up is due', 'wpcredits-program-manager' ) );
			printf( '<p class="wpcpm-head__lede">%s</p>', esc_html__( 'No school has a follow-up date today or earlier.', 'wpcredits-program-manager' ) );
			echo self::add_button() . '</div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in add_button().

			return;
		}

		printf(
			'<p class="wpcpm-head__title">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: how many follow-ups are due. */
					_n( '%s follow-up is due', '%s follow-ups are due', $n, 'wpcredits-program-manager' ),
					number_format_i18n( $n )
				)
			)
		);
		printf(
			'<p class="wpcpm-head__lede">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: a school's name, 2: the day its follow-up was due. */
					__( 'The oldest, %1$s, has been due since %2$s.', 'wpcredits-program-manager' ),
					self::name_of( $due[0] ),
					WPCPM_Dates::day( (string) $due[0]['follow_up'] )
				)
			)
		);
		echo self::add_button() . '</div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in add_button().
	}

	/**
	 * The head's one action: Add a lead, secondary, a link to the fold at the view's end, which the dashboards' opener opens.
	 *
	 * @return string Escaped markup.
	 */
	private static function add_button() {
		return '<div class="wpcpm-head__actions">' . WPCPM_UI::button(
			array(
				'label' => __( 'Add a lead', 'wpcredits-program-manager' ),
				'kind'  => 'secondary',
				'href'  => '#' . WPCPM_Institution_Leads::ADD_ANCHOR,
				'side'  => 'front',
				// It stays on screen after the fold opens: the opener moves the focus to the fold's summary (fix round 2, F2).
				'data'  => array( 'wpcpm-focus-card' => true ),
			)
		) . '</div>';
	}

	/**
	 * Mine or All: one small control at the head's top right, the shown one said in the muted label, the other a quiet
	 * link. No Save: the link is the switch.
	 *
	 * @param string $who  `mine` or `all`.
	 * @param string $find Words to find, kept.
	 * @return string Escaped markup.
	 */
	private static function who_control( $who, $find ) {
		$mine = self::MINE === $who;

		return '<div class="wpcpm-ui-viewing">'
			. '<span class="wpcpm-ui-viewing__label">' . esc_html( $mine ? __( 'Showing your schools', 'wpcredits-program-manager' ) : __( 'Showing all schools', 'wpcredits-program-manager' ) ) . '</span>'
			. WPCPM_UI::button(
				array(
					'label' => $mine ? __( 'Show all schools', 'wpcredits-program-manager' ) : __( 'Show your schools', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					'href'  => self::url(
						array(
							self::ARG_WHO  => $mine ? self::ALL : self::MINE,
							self::ARG_FIND => $find,
						)
					),
					'side'  => 'front',
				)
			)
			. '</div>';
	}

	/*
	 * Find and Add a lead
	 * --------------------------------------------------------------------
	 */

	/**
	 * Find: one search box in a GET form, as the roster's filter bar, and Find; Clear while words are asked for.
	 *
	 * @param string $who  `mine` or `all`.
	 * @param string $find Words asked for.
	 */
	private static function render_find( $who, $find ) {
		echo '<form class="wpcpm-talks-view__find" method="get" role="search">';
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( WPCPM_Administrators_Dashboard::VIEW_ARG ), esc_attr( WPCPM_Institution_Talks::VIEW_TALKS ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ARG_WHO ), esc_attr( $who ) );

		$field = WPCPM_UI::field(
			array(
				'id'    => 'wpcpm-talks-find',
				'name'  => self::ARG_FIND,
				'label' => __( 'Find a school', 'wpcredits-program-manager' ),
				'type'  => 'search',
				'value' => $find,
			)
		);

		// A row of fields, as the Students view's filter bar: the search box 320px, then its one secondary Find, as the bar's
		// Show, and Clear while a query stands (fix round 2, F1).
		echo '<div class="wpcpm-ui-fields">' . $field . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::field().

		$buttons = array(
			WPCPM_UI::button(
				array(
					'label' => __( 'Find', 'wpcredits-program-manager' ),
					'kind'  => 'secondary',
					'side'  => 'front',
				)
			),
		);

		if ( '' !== $find ) {
			$buttons[] = WPCPM_UI::button(
				array(
					'label' => __( 'Clear', 'wpcredits-program-manager' ),
					'kind'  => 'quiet',
					'href'  => self::url( array( self::ARG_WHO => $who ) ),
					'side'  => 'front',
				)
			);
		}

		echo WPCPM_UI::actions( $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::button() and WPCPM_UI::actions().
		echo '</form>';
	}

	/**
	 * Add a lead: a fold, folded until a refusal comes back to it; its outcome the form's first block, linked to the box
	 * it refused, the typing kept; one primary, "Add the lead".
	 */
	private static function render_add() {
		$flash = WPCPM_Flash::take( WPCPM_Institution_Leads::ADD_FLASH );
		$flash = is_array( $flash ) && isset( $flash['text'] ) && is_string( $flash['text'] ) && '' !== $flash['text'] ? $flash : null;
		$kept  = WPCPM_Typed_Text::kept( WPCPM_Institution_Leads::ADD_FORM );
		$field = null !== $flash && isset( $flash['field'] ) && in_array( $flash['field'], WPCPM_Institution_Leads::ADD_FIELDS, true ) ? (string) $flash['field'] : '';
		$value = static function ( $name ) use ( $kept ) {
			return isset( $kept[ $name ] ) && is_scalar( $kept[ $name ] ) ? (string) $kept[ $name ] : '';
		};
		$id    = static function ( $name ) {
			return 'wpcpm-add-lead-' . str_replace( '_', '-', (string) $name );
		};
		$box   = static function ( $name, $label, array $more = array() ) use ( $value, $id, $field, $flash ) {
			return WPCPM_UI::field(
				array_merge(
					array(
						'type'    => 'text',
						'id'      => $id( $name ),
						'name'    => $name,
						'label'   => $label,
						'value'   => $value( $name ),
						'problem' => $name === $field ? (string) $flash['text'] : '',
					),
					$more
				)
			);
		};

		WPCPM_Dashboard_Card::open(
			array(
				'key'           => 'add-lead',
				'title'         => __( 'Add a lead', 'wpcredits-program-manager' ),
				// How to do this, as the Administrator Dashboard's other cards carry it (G7b).
				'help'          => WPCPM_Guide_Jobs::link( 'add-lead', __( 'Add a lead', 'wpcredits-program-manager' ) ),
				'open'          => null !== $flash,
				'prefix'        => 'wpcpm-',
				'id_on'         => 'section',
				'section_class' => '',
			)
		);

		$out = sprintf(
			'<form method="post" action="%1$s" novalidate data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Adding', 'wpcredits-program-manager' )
		)
			. WPCPM_UI::nonce( WPCPM_Institution_Leads::ACTION_ADD )
			. sprintf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Leads::ACTION_ADD ) )
			. ( null !== $flash ? WPCPM_Dashboard_Card::message( 'error', (string) $flash['text'], 'wpcpm-talks__message', '' !== $field ? $id( $field ) : '' ) : '' )
			. $box(
				'name',
				__( 'Name', 'wpcredits-program-manager' ),
				array(
					'need'          => 'required',
					'required_attr' => false,
					'attrs'         => array( 'maxlength' => WPCPM_Institution_Leads::MAX_LENGTHS['name'] ),
				)
			)
			. $box( 'city', __( 'City', 'wpcredits-program-manager' ), array( 'attrs' => array( 'maxlength' => WPCPM_Institution_Leads::MAX_LENGTHS['city'] ) ) )
			. $box(
				'country',
				__( 'Country', 'wpcredits-program-manager' ),
				array(
					'type'    => 'combo',
					'options' => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + WPCPM_Countries::options(),
					'find'    => __( 'Type to find a country', 'wpcredits-program-manager' ),
					'none'    => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'   => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
				)
			)
			. $box( 'contact_person', __( 'Contact person', 'wpcredits-program-manager' ), array( 'attrs' => array( 'maxlength' => WPCPM_Institution_Leads::MAX_LENGTHS['contact_person'] ) ) )
			. $box(
				'contact_email',
				__( 'Contact\'s email', 'wpcredits-program-manager' ),
				array(
					'type'  => 'email',
					'attrs' => array( 'maxlength' => 200 ),
				)
			)
			. $box(
				'help_desk',
				__( 'Help desk link', 'wpcredits-program-manager' ),
				array(
					'attrs' => array(
						'maxlength' => 500,
						'inputmode' => 'url',
					),
				)
			)
			. $box( 'next_step', __( 'Next step', 'wpcredits-program-manager' ), array( 'attrs' => array( 'maxlength' => WPCPM_Institution_Leads::MAX_LENGTHS['next_step'] ) ) )
			. $box( 'follow_up', __( 'Follow up on', 'wpcredits-program-manager' ), array( 'type' => 'date' ) )
			. WPCPM_UI::field(
				array(
					'type'  => 'textarea',
					'id'    => $id( 'note' ),
					'name'  => 'note',
					'label' => __( 'Note', 'wpcredits-program-manager' ),
					'value' => $value( 'note' ),
					'rows'  => 4,
					'attrs' => array( 'maxlength' => WPCPM_Institution_Talk_Notes::MAX_LENGTH ),
				)
			)
			. WPCPM_UI::actions(
				array(
					WPCPM_UI::button(
						array(
							'label' => __( 'Add the lead', 'wpcredits-program-manager' ),
							'side'  => 'front',
						)
					),
				)
			)
			. '</form>';

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in WPCPM_Dashboard_Card::message().

		WPCPM_Dashboard_Card::close();
	}

	/*
	 * The lists
	 * --------------------------------------------------------------------
	 */

	/**
	 * The follow-ups due: a table, oldest first, never cut: the school (its talks' page), the next step and the day.
	 *
	 * @param array[] $due `WPCPM_Institution_Talks::due()`.
	 */
	private static function render_due( array $due ) {
		if ( array() === $due ) {
			return;
		}

		$school  = array(
			'key'    => 'school',
			'label'  => __( 'School', 'wpcredits-program-manager' ),
			'kind'   => 'text',
			'header' => 'row',
		);
		$next    = array(
			'key'   => 'next',
			'label' => __( 'Next step', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);
		$day     = array(
			'key'   => 'day',
			'label' => __( 'Follow up on', 'wpcredits-program-manager' ),
			'kind'  => 'short',
		);
		$heading = 'wpcpm-talks-due-heading';
		$out     = '<section class="wpcpm-talks-view__due">'
			. WPCPM_UI::heading( __( 'Follow-ups due', 'wpcredits-program-manager' ), 'h2', $heading )
			. WPCPM_UI::table_open( array( $school, $next, $day ), array( 'labelledby' => $heading ) );

		foreach ( $due as $one ) {
			$step = trim( (string) $one['next_step'] );
			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $school, self::name_link( $one ) )
				. ( '' !== $step ? WPCPM_UI::cell( $next, WPCPM_UI::breakable( $step ) ) : WPCPM_UI::cell( $next, esc_html__( 'Not recorded', 'wpcredits-program-manager' ), 'empty' ) )
				. WPCPM_UI::cell( $day, WPCPM_UI::whole( WPCPM_Dates::day( (string) $one['follow_up'] ) ) )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close() . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
	}

	/**
	 * The schools by stage: Lead to Confirmed, each holding schools, an in-view heading over its list; "No stage" after
	 * them; then folded, Revisit Later, Not Moving Forward, SPAM and each stage the site's lists do not hold, A to Z.
	 *
	 * @param array[] $shown The schools shown, in order.
	 * @param string  $who   `mine` or `all`.
	 */
	private static function render_stages( array $shown, $who ) {
		$groups = array();

		foreach ( $shown as $one ) {
			$groups[ (string) $one['stage'] ][] = $one;
		}

		echo '<section class="wpcpm-talks-view__stages">';

		if ( array() === $groups ) {
			echo WPCPM_UI::list_empty( self::MINE === $who ? __( 'You have no school in the talks yet.', 'wpcredits-program-manager' ) : __( 'No school is in the talks yet.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
			echo '</section>';

			return;
		}

		$whole  = self::asked( self::ARG_WHOLE );
		$folded = WPCPM_Institution_Talks::closed();
		$known  = WPCPM_Institution_Talks::stages();
		$open   = self::open_stages();

		foreach ( $open as $stage ) {
			if ( isset( $groups[ $stage ] ) ) {
				self::render_group( $stage, $stage, $groups[ $stage ], $who, $whole, false );
			}
		}

		if ( isset( $groups[''] ) ) {
			self::render_group( '', __( 'No stage', 'wpcredits-program-manager' ), $groups[''], $who, $whole, false );
		}

		// The open groups end their section; the folded ones follow it, together, as the view's next block (the page's
		// 28px under it), each card fold the parts' fold apart from the next.
		echo '</section>';

		$unknown = array_values( array_diff( array_keys( $groups ), $known, array( '' ) ) );
		usort( $unknown, 'strcasecmp' );

		$last = array_values( array_intersect( array_merge( array( WPCPM_Institution_Talks::STAGE_REVISIT ), array_diff( $folded, array( WPCPM_Institution_Talks::STAGE_REVISIT ) ), $unknown ), array_keys( $groups ) ) );

		if ( array() === $last ) {
			return;
		}

		echo '<div class="wpcpm-talks-view__folded">';

		foreach ( $last as $stage ) {
			self::render_group( $stage, $stage, $groups[ $stage ], $who, $whole, true );
		}

		echo '</div>';
	}

	/**
	 * The stages drawn open, in their order: Lead, then the agreement's order up to Confirmed.
	 *
	 * @return string[]
	 */
	private static function open_stages() {
		$order = WPCPM_Institution_Agreement::STAGE_ORDER;
		$at    = array_search( WPCPM_Institution_Talks::STAGE_CONFIRMED, $order, true );

		return array_merge( array( WPCPM_Institution_Talks::STAGE_LEAD ), false === $at ? $order : array_slice( $order, 0, (int) $at + 1 ) );
	}

	/**
	 * One stage's group: its heading with its count in words (a fold of the parts for a folded one), the first `SHOWN`
	 * rows, and one quiet "Show all N" when it holds more, unless it is the group asked for whole.
	 *
	 * @param string  $stage  The stage, '' for none.
	 * @param string  $title  Its name as the reader reads it.
	 * @param array[] $rows   Its schools, in order.
	 * @param string  $who    `mine` or `all`.
	 * @param string  $whole  The group asked for whole, by its slug, or ''.
	 * @param bool    $folded Whether it is drawn folded.
	 */
	private static function render_group( $stage, $title, array $rows, $who, $whole, $folded ) {
		$slug   = self::slug( $stage );
		$id     = 'wpcpm-talks-stage-' . $slug;
		$n      = count( $rows );
		$phrase = sprintf(
			/* translators: %s: how many schools. */
			_n( '%s school', '%s schools', $n, 'wpcredits-program-manager' ),
			number_format_i18n( $n )
		);
		$all = $whole === $slug;

		if ( $folded ) {
			printf( '<section class="wpcpm-ui-fold-card" id="%1$s"><details class="wpcpm-ui-fold"%2$s><summary class="wpcpm-ui-fold__head">', esc_attr( $id ), $all ? ' open' : '' );
			$head = WPCPM_UI::fold_head(
				array(
					'size'   => 'card',
					'title'  => $title,
					'phrase' => $phrase,
				)
			);

			echo $head . '</summary><div class="wpcpm-ui-fold__body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::fold_head().
		} else {
			/* translators: 1: a stage of the talks, 2: how many schools are at it, as "3 schools". */
			echo WPCPM_UI::heading( sprintf( __( '%1$s: %2$s', 'wpcredits-program-manager' ), $title, $phrase ), 'h2', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().
		}

		$cut = ! $all && $n > self::SHOWN;

		echo WPCPM_UI::list_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		foreach ( $cut ? array_slice( $rows, 0, self::SHOWN ) : $rows as $one ) {
			echo self::row( $one, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row().
		}

		echo WPCPM_UI::list_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		if ( $cut ) {
			$more = WPCPM_UI::actions(
				array(
					WPCPM_UI::button(
						array(
							/* translators: %s: how many schools the group holds. */
							'label' => sprintf( __( 'Show all %s', 'wpcredits-program-manager' ), number_format_i18n( $n ) ),
							'kind'  => 'quiet',
							'href'  => self::url(
								array(
									self::ARG_WHO   => $who,
									self::ARG_WHOLE => $slug,
								)
							) . '#' . $id,
							'side'  => 'front',
						)
					),
				)
			);

			echo $more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::button() and WPCPM_UI::actions().
		}

		if ( $folded ) {
			echo '</div></details></section>';
		}
	}

	/**
	 * What Find found: a heading with the count in words over the list, each row's stage its status tag ("Closed" for a
	 * closed lead); "No school matches that name." when none.
	 *
	 * @param array[] $found The schools found.
	 */
	private static function render_found( array $found ) {
		echo '<section class="wpcpm-talks-view__found">';

		if ( array() === $found ) {
			echo WPCPM_UI::list_empty( __( 'No school matches that name.', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::list_empty().
			echo '</section>';

			return;
		}

		$n = count( $found );

		$heading = WPCPM_UI::heading(
			sprintf(
				/* translators: %s: how many schools match. */
				_n( '%s school in all the talks matches that name', '%s schools in all the talks match that name', $n, 'wpcredits-program-manager' ),
				number_format_i18n( $n )
			),
			'h2'
		);

		echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().
		echo WPCPM_UI::list_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.

		foreach ( $found as $one ) {
			echo self::row( $one, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::row().
		}

		echo WPCPM_UI::list_close() . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
	}

	/**
	 * One school's row (Task 16 fix round 1, V5): its name (its talks' page), at most one status tag, and its facts. Due
	 * is said by the table of follow-ups due alone, so no row is red. The tag is "Closed" for a closed lead, else "Not
	 * assigned" for a school nobody who manages the program is assigned to; never a fact as well. The facts: in what Find
	 * found the stage first ("No stage" for none), as text; then where it is, its Administrator, the next step and the
	 * follow-up day.
	 *
	 * @param array $one   `facts()`.
	 * @param bool  $found Whether the row is one Find found.
	 * @return string Escaped markup.
	 */
	private static function row( array $one, $found ) {
		$assigned = self::assignee( $one );
		$tag      = null;

		if ( ! empty( $one['closed'] ) ) {
			$tag = array(
				'words' => __( 'Closed', 'wpcredits-program-manager' ),
				'tone'  => '',
			);
		} elseif ( $assigned < 1 ) {
			$tag = array(
				'words' => __( 'Not assigned', 'wpcredits-program-manager' ),
				'tone'  => '',
			);
		}

		$facts = array();

		if ( $found ) {
			$facts[] = '' !== (string) $one['stage'] ? (string) $one['stage'] : __( 'No stage', 'wpcredits-program-manager' );
		}

		$place = implode(
			', ',
			array_filter(
				array( trim( (string) $one['city'] ), trim( (string) $one['country_name'] ) ),
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);

		if ( '' !== $place ) {
			$facts[] = array( 'html' => WPCPM_UI::breakable( $place ) );
		}

		if ( $assigned > 0 ) {
			$facts[] = array( 'html' => WPCPM_UI::breakable( WPCPM_Institution_Assignment::label( $assigned ) ) );
		}

		if ( '' !== trim( (string) $one['next_step'] ) ) {
			$facts[] = array( 'html' => WPCPM_UI::breakable( trim( (string) $one['next_step'] ) ) );
		}

		$day = WPCPM_Dates::day( (string) $one['follow_up'] );

		if ( '' !== $day ) {
			/* translators: %s: the day of the follow-up. */
			$facts[] = array( 'html' => WPCPM_UI::whole( sprintf( __( 'Follow up on %s', 'wpcredits-program-manager' ), $day ) ) );
		}

		return WPCPM_UI::row(
			array(
				'name_html' => WPCPM_UI::breakable( self::name_of( $one ) ),
				'href'      => WPCPM_Institution_Talks::url( (string) $one['key'] ),
				'tag'       => $tag,
				'facts'     => $facts,
			)
		);
	}

	/**
	 * A school's name, its key when it has none.
	 *
	 * @param array $one `facts()`.
	 * @return string
	 */
	private static function name_of( array $one ) {
		return '' !== trim( (string) $one['name'] ) ? trim( (string) $one['name'] ) : (string) $one['key'];
	}

	/**
	 * A school's name as a link to its talks' page, the name breakable.
	 *
	 * @param array $one `facts()`.
	 * @return string Escaped markup.
	 */
	private static function name_link( array $one ) {
		$url  = WPCPM_Institution_Talks::url( (string) $one['key'] );
		$name = WPCPM_UI::breakable( self::name_of( $one ) );

		return '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name;
	}

	/*
	 * A lead's page
	 * --------------------------------------------------------------------
	 */

	/**
	 * A lead's page: the head, the lead's name the view's title, then its Talks card, which does not say the name again.
	 * A lead that became an institution says so, its name a link to the school's Talks card (Task 16 fix round 1, G1: the
	 * assignee's email still points here); any other key says it is no lead in the talks. No way back of its own: the menu
	 * is the way back.
	 *
	 * @param string $key   The lead key asked for.
	 * @param array  $frame What `render()` was given.
	 */
	private static function render_lead( $key, array $frame ) {
		$facts  = WPCPM_Institution_Talks::is_lead_key( $key ) ? WPCPM_Institution_Talks::facts( $key ) : null;
		$became = null === $facts && current_user_can( WPCPM_Roles::CAP_MANAGE ) ? WPCPM_Institution_Leads::promoted_to( $key ) : '';
		$school = '' !== $became ? WPCPM_Institution_Talks::facts( $became ) : null;

		$line = '';

		if ( null !== $school ) {
			$line = sprintf(
				'<p class="wpcpm-head__lede">%s</p>',
				sprintf(
					/* translators: %s: the school's name, a link to its Talks card. */
					esc_html__( 'This lead is an institution now: %s.', 'wpcredits-program-manager' ),
					self::name_link( $school )
				)
			);
		} elseif ( null === $facts ) {
			$line = sprintf( '<p class="wpcpm-head__lede">%s</p>', esc_html__( 'This lead is not in the talks.', 'wpcredits-program-manager' ) );
		}

		// A note refused for a lead whose page has no card to show it on: one message in the view's own column, directly
		// above the page's own line, which says why (round 3, P2).
		$refused = null === $facts && WPCPM_Institution_Talks::take_refused_note( $key );

		echo '<header class="wpcpm-head"><div class="wpcpm-head__copy">';
		echo (string) $frame['page_name']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_name().
		printf( '<p class="wpcpm-head__title">%s</p>', null !== $facts ? WPCPM_UI::breakable( self::name_of( $facts ) ) : esc_html__( 'Talks', 'wpcredits-program-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::breakable().

		if ( ! $refused ) {
			echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above and in name_link().
		}

		echo '</div></header>';

		if ( $refused ) {
			echo WPCPM_Dashboard_Card::message( 'error', __( 'The note was not added.', 'wpcredits-program-manager' ), 'wpcpm-talks__message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_Dashboard_Card::message().
			echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above and in name_link().
		}

		self::messages( $frame );

		if ( null !== $facts ) {
			WPCPM_Institution_Talks::render( $key, array( 'named' => false ) );
		}
	}
}
