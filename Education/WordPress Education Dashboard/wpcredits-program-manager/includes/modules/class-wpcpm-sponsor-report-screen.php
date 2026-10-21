<?php
/**
 * The annual sponsor report on the Sponsors screen: the Reports card, the editor, and their forms.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a program manager drafts a sponsor's annual report, writes it and finishes it, on the
 * wp-admin Sponsors screen.
 *
 * The part of the report that a manager works in, kept apart from `WPCPM_Sponsor_Report`, which
 * stores and generates and never learns who is asking, and from `WPCPM_Sponsor_Report_Files`, which
 * holds the PDF and prints the document. `WPCPM_Sponsors` draws the card and the editor through this
 * class and merges its words into the screen's own.
 *
 * **The Reports card** is the Sponsors screen's Reports tab. Each Approved sponsor, in the index's
 * order, has its reports listed, the newest period first, each by its title and its state, with
 * Open. Then comes the form that drafts one. Its day defaults to the last day of the term that a
 * renewal left owed its report, which is the report still due. Otherwise it is the last day of the
 * row's own term, and today for a sponsor with no term. The form is drawn when the sponsor's tier on
 * that day carries a report, or a term is owed: that is the test the store applies to a press of the
 * form as drawn, since it reads the tier held on the period's last day. So a sponsor whose term has
 * just ended keeps the form for the report now due, a lapsed Backer's for the term it held, and a
 * Friend, whose every press the store would refuse, reads "No report for this tier." instead.
 *
 * **The editor** is that tab's view of one report, drawn in place of the card when
 * `?wpcpm_sreport=<id>` names a report (`editor_url()`), with the way back to the tab. It shows the
 * title, the period and the state, and the snapshot's figures as tables. Those rows come from the
 * files class, so a figure is named and counted exactly as the sponsor will read it. The
 * narratives follow, one box each, all saved by one form. A final report's boxes are read-only,
 * since the store takes no save until it is reopened. Then Preview, the PDF, Mark final or Reopen,
 * and Regenerate, drawn according to the state.
 *
 * **The six forms** each ask for the program's capability first, then the nonce keyed to the
 * sponsor or the report they act on, and only then reach the store. Every outcome is a sentence on
 * the Sponsors screen's channel (`messages()`), printed on the tab the press comes back to. A press
 * made in the editor comes back to the editor, and to the Reports tab when the report it names is
 * gone. A draft refused on the Reports tab comes back to it; one refused on the Administrator
 * Dashboard goes back there, on that page's channel. A draft that succeeds opens in the editor
 * wherever it was pressed. The draft form is `render_draft_form()` on both pages, so what it posts is
 * written once.
 *
 * **Not a renewal** is the Tier box's button, for a term that a moved day left owed its report when
 * the day was corrected rather than renewed. It clears the owed term, writes a row on the trail,
 * and says it is done, naming the term's last day (`sentence_for()`), on the sponsor's Edit form it
 * was pressed on.
 *
 * A manager's screen, so nothing here waits for the tiers switch; what a sponsor's members read is
 * the Sponsor Dashboard's. The editor draws the snapshot, which holds counts and the mentors' names
 * and nothing else about a person, and `bin/test-sponsor-report.php` walks it with the documents.
 */
final class WPCPM_Sponsor_Report_Screen {

	/** Draft a sponsor's report for a period end. The nonce is keyed to the sponsor: `wpcpm_sreport_draft_<key>`. */
	const ACTION_DRAFT = 'wpcpm_sreport_draft';

	/** Save a report's narratives, every one at once. The nonce is keyed to the report: `wpcpm_sreport_save_<id>`. */
	const ACTION_SAVE = 'wpcpm_sreport_save';

	/** Mark a draft final. Keyed to the report. */
	const ACTION_FINAL = 'wpcpm_sreport_final';

	/** Make a final report a draft again. Keyed to the report. */
	const ACTION_REOPEN = 'wpcpm_sreport_reopen';

	/** Read a draft's figures again, keeping its narratives. Keyed to the report. */
	const ACTION_REGENERATE = 'wpcpm_sreport_regenerate';

	/** Say a moved day owes no report: the Tier box's Not a renewal. Keyed to the sponsor. */
	const ACTION_NOT_RENEWAL = 'wpcpm_sreport_not_renewal';

	/** The sponsor the draft form and Not a renewal post about. */
	const FIELD_RECORD = 'wpcpm_record';

	/** The period's last day the draft form posts, `Y-m-d`. */
	const FIELD_UNTIL = 'wpcpm_until';

	/** A narrative's field: this, then the narrative's key, as in `wpcpm_sreport_text_cost_line`. */
	const FIELD_TEXT = 'wpcpm_sreport_text_';

	/** The Reports card's id. */
	const ANCHOR = 'wpcpm-sponsor-reports';

	/** The ID of Not a renewal's form, which the Tier box's button names in its `form` attribute. */
	const NOT_RENEWAL_FORM = 'wpcpm-sreport-not-renewal';

	/** The flash channel the last day of the term Not a renewal cleared travels on, for its notice. */
	const FLASH_CLEARED = 'sreport_cleared_day';

	/** The flash channel a Save refused for one narrative's length carries that box's label on. */
	const FLASH_LONG = 'sreport_long_box';

	/**
	 * The flash channel a Save refused for the words the cleaner would take from one narrative
	 * carries that box's label on.
	 */
	const FLASH_LOSS = 'sreport_loss_box';

	/** How many rows a narrative's box is drawn with. */
	const TEXT_ROWS = 8;

	/**
	 * Hooks: the six forms, a program manager's alone, so none has an arm for a visitor who is signed
	 * out. Started from the Sponsors module's boot list, as the store and the files class are.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_DRAFT, array( __CLASS__, 'handle_draft' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_FINAL, array( __CLASS__, 'handle_final' ) );
		add_action( 'admin_post_' . self::ACTION_REOPEN, array( __CLASS__, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::ACTION_REGENERATE, array( __CLASS__, 'handle_regenerate' ) );
		add_action( 'admin_post_' . self::ACTION_NOT_RENEWAL, array( __CLASS__, 'handle_not_renewal' ) );
	}

	/**
	 * The six forms' outcomes, in a program manager's words, on the Sponsors screen's channel.
	 *
	 * `WPCPM_Sponsors::messages()` merges this map, as it merges the files class's, so every outcome
	 * has words on the screen the forms land back on. A refused draft says the store's reason. Not a
	 * renewal's success names no day here, nor a Save refused for a narrative's length, or for the
	 * words the cleaner would take from one, its box, for a page that prints the map as it is: the
	 * Sponsors screen prints each with what the press carried (`sentence_for()`).
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice type and sentence.
	 */
	public static function messages() {
		return array(
			'sreport-drafted'            => array( 'success', __( 'The report is drafted from the program\'s records. Write its narratives below and save them, then mark it final when it is ready for the sponsor.', 'wpcredits-program-manager' ) ),
			'sreport-exists'             => array( 'error', __( 'Nothing was drafted. A report for that term exists already: open that one instead.', 'wpcredits-program-manager' ) ),
			'sreport-bad-day'            => array( 'error', __( 'Nothing was drafted. That is not a day a report can end on: give the last day of the term as a date.', 'wpcredits-program-manager' ) ),
			'sreport-no-sponsor'         => array( 'error', __( 'Nothing was drafted. That sponsor is not on this site anymore.', 'wpcredits-program-manager' ) ),
			'sreport-no-tier'            => array( 'error', __( 'Nothing was drafted. The sponsor\'s tier on that day carries no annual report.', 'wpcredits-program-manager' ) ),
			'sreport-not-created'        => array( 'error', __( 'Nothing was drafted. The site could not create the report just now. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sreport-busy'               => array( 'error', __( 'A report for this sponsor is being drafted right now. Try again in a minute.', 'wpcredits-program-manager' ) ),
			'sreport-saved'              => array( 'success', __( 'The narratives are saved.', 'wpcredits-program-manager' ) ),
			// The Sponsors screen's Edit form says the same of a save that changed nothing.
			'sreport-unchanged'          => array( 'info', __( 'Nothing changed, so nothing was saved.', 'wpcredits-program-manager' ) ),
			'sreport-save-final'         => array( 'error', __( 'Nothing was saved. The report is final: reopen it to change its narratives.', 'wpcredits-program-manager' ) ),
			'sreport-unreadable'         => array( 'error', __( 'Nothing was saved. This report\'s figures cannot be read, so it has no sections to write under: regenerate it first.', 'wpcredits-program-manager' ) ),
			// The Sponsors screen names the box, which the press carries (`sentence_for()`).
			'sreport-too-long'           => array(
				'error',
				sprintf(
					/* translators: %s: the most characters a narrative holds. */
					_n( 'Nothing was saved: a narrative is longer than %s character. Shorten it and save again.', 'Nothing was saved: a narrative is longer than %s characters. Shorten it and save again.', WPCPM_Sponsor_Report::MAX_TEXT, 'wpcredits-program-manager' ),
					number_format_i18n( WPCPM_Sponsor_Report::MAX_TEXT )
				),
			),
			// The Sponsors screen names the box, which the press carries, in the sentence every box of
			// typed text refuses a loss with (`sentence_for()`).
			'sreport-loss'               => array( 'error', __( 'Nothing was saved, because WordPress would remove part of what you typed in a narrative. To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.', 'wpcredits-program-manager' ) ),
			'sreport-final'              => array( 'success', __( 'The report is final. The sponsor\'s members can read it on the Sponsor Dashboard while Sponsor tiers is switched on in Settings.', 'wpcredits-program-manager' ) ),
			'sreport-final-already'      => array( 'info', __( 'Nothing changed. The report was final already.', 'wpcredits-program-manager' ) ),
			'sreport-final-unreadable'   => array( 'error', __( 'Nothing changed. This report\'s figures cannot be read: regenerate it before marking it final.', 'wpcredits-program-manager' ) ),
			'sreport-reopened'           => array( 'success', __( 'The report is a draft again, and the sponsor no longer sees it.', 'wpcredits-program-manager' ) ),
			'sreport-draft-already'      => array( 'info', __( 'Nothing changed. The report was a draft already.', 'wpcredits-program-manager' ) ),
			'sreport-regenerated'        => array( 'success', __( 'The report\'s figures were read again from the program\'s records, and its narratives were kept.', 'wpcredits-program-manager' ) ),
			'sreport-regenerate-final'   => array( 'error', __( 'Nothing was read again. The report is final: reopen it first.', 'wpcredits-program-manager' ) ),
			'sreport-regenerate-refused' => array( 'error', __( 'Nothing was read again. The sponsor is not on this site anymore, or its tier on the term\'s last day carries no report.', 'wpcredits-program-manager' ) ),
			'sreport-gone'               => array( 'error', __( 'Nothing was changed. That report is not on this site anymore.', 'wpcredits-program-manager' ) ),
			'sreport-owed-cleared'       => array( 'success', __( 'Done: that term was a corrected day, not a renewal, so no report is owed for it.', 'wpcredits-program-manager' ) ),
			'sreport-owed-none'          => array( 'info', __( 'Nothing changed. No term of that sponsor was waiting for its report.', 'wpcredits-program-manager' ) ),
			'sreport-owed-not-saved'     => array( 'error', __( 'Nothing changed. The site could not save the sponsor just now. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sreport-owed-gone'          => array( 'error', __( 'Nothing changed. That sponsor is not on this site anymore.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The Reports card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Reports card: each Approved sponsor, in the rows' order, with its reports and, where its
	 * tier carries one, the form that drafts one.
	 *
	 * @param array  $rows  The sponsors index rows, keyed by key.
	 * @param string $today `Y-m-d`, the day the draft form offers a sponsor with no term, and so the
	 *                      day its tier is read on; '' for today.
	 */
	public static function render_card( array $rows, $today = '' ) {
		echo '<div class="wpcpm-card" id="' . esc_attr( self::ANCHOR ) . '">';
		echo '<h2>' . esc_html__( 'Reports', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The yearly report a sponsor\'s tier includes, one for each term: the twelve months ending on the term\'s last day. The daily run drafts it the day after the term ends, and for a sponsor whose tier carries a report one can be drafted here for any last day. Write its narratives, then mark it final: the sponsor\'s members read final reports on the Sponsor Dashboard while Sponsor tiers is switched on in Settings.', 'wpcredits-program-manager' ) . '</p>';

		$approved = 0;

		foreach ( $rows as $record => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['status'] ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
				continue;
			}

			++$approved;
			self::render_sponsor( (string) $record, $row, $today );
		}

		if ( 0 === $approved ) {
			echo '<p>' . esc_html__( 'No Approved sponsor yet.', 'wpcredits-program-manager' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * What the Reports card and the Tier box say while a moved day has left a term owed its report.
	 *
	 * @param array $row A sponsor's row.
	 * @return string '' when the row owes no report.
	 */
	public static function owed_sentence( array $row ) {
		$owed = WPCPM_Sponsor_Report::owed_of( $row );

		if ( empty( $owed ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: the last day of the term a report is still due for, in the site's date format. */
			__( 'A report for the term to %s is still due.', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Tiers::format_day( $owed['until'] )
		);
	}

	/**
	 * A status's sentence as the Sponsors screen prints it: Not a renewal's success said as done, with
	 * the last day of the term it cleared in the Tier box's format, so it cannot read as a refusal;
	 * a Save refused for one narrative's length, with that box's label, so the manager knows which
	 * to shorten; and a Save refused for the words the cleaner would take from one narrative, with that
	 * box's label, in the sentence every box of typed text refuses a loss with
	 * (`WPCPM_Typed_Text::loss_message()`). The flash carries a status alone and the owed term is gone
	 * from the row once it is cleared, so each press carries its detail on a channel of its own
	 * (`FLASH_CLEARED`, `FLASH_LONG`, `FLASH_LOSS`), and this takes all three where the notice is
	 * printed (`WPCPM_Sponsors::notice_sentence()`), whichever status that is, so that a detail meant
	 * for one notice is never left for another. Any other status, or one of these with nothing carried,
	 * keeps the sentence it has.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from `messages()`.
	 * @return string
	 */
	public static function sentence_for( $status, $sentence ) {
		$until = WPCPM_Flash::take( self::FLASH_CLEARED );
		$box   = WPCPM_Flash::take( self::FLASH_LONG );
		$lost  = WPCPM_Flash::take( self::FLASH_LOSS );

		if ( 'sreport-too-long' === (string) $status && is_string( $box ) && '' !== $box ) {
			return WPCPM_Sponsors::long_sentence( $box, WPCPM_Sponsor_Report::MAX_TEXT );
		}

		if ( 'sreport-loss' === (string) $status && is_string( $lost ) && '' !== $lost ) {
			return WPCPM_Typed_Text::loss_message( $lost, 'save' );
		}

		if ( 'sreport-owed-cleared' !== (string) $status || ! is_string( $until ) || ! WPCPM_Sponsor_Tiers::is_date( $until ) ) {
			return (string) $sentence;
		}

		return sprintf(
			/* translators: %s: the last day of the term that owes no report, in the site's date format. */
			__( 'Done: the term to %s was a corrected day, not a renewal, so no report is owed for it.', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Tiers::format_day( $until )
		);
	}

	/**
	 * A report open in its editor: the Sponsors screen's Reports tab with `?wpcpm_sreport=<id>`, which
	 * the tab draws in place of its card.
	 *
	 * The one builder of every address that opens the editor: Open on the Reports card and on the
	 * Administrator Dashboard's Sponsor reports card, and where a press made in the editor lands, the
	 * files class's PDF forms among them.
	 *
	 * @param int $post_id The report.
	 * @return string Not escaped.
	 */
	public static function editor_url( $post_id ) {
		return add_query_arg( array( WPCPM_Sponsor_Report_Files::ARG_EDITOR => (int) $post_id ), self::screen_url( 'reports' ) );
	}

	/**
	 * One sponsor on the card: its name, its reports, what is still due, and the form that drafts one,
	 * or, when its tier on the form's day carries no report, one sentence saying so, since the store
	 * would refuse every press of a form drawn for it.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    Its row.
	 * @param string $today  `Y-m-d`, or '' for today.
	 */
	private static function render_sponsor( $record, array $row, $today ) {
		$name    = self::sponsor_name( $record, $row );
		$reports = WPCPM_Sponsor_Report::reports_of( $record );
		$due     = self::owed_sentence( $row );
		$day     = self::default_until( $row, $today );

		// The tier on the form's own day, as the store reads it for a press of the form as drawn: read
		// today, a sponsor whose term has just ended would be a Friend, and lose the form for
		// the very report now due. An owed term carries a report by the rule that recorded it.
		$drafts = '' !== $due || WPCPM_Sponsor_Tiers::includes( WPCPM_Sponsor_Tiers::effective( $row, $day ), 'report_program' );

		echo '<h3>' . esc_html( $name ) . '</h3>';

		if ( empty( $reports ) ) {
			// With no form under it, "No report yet." would only say no twice.
			if ( $drafts ) {
				echo '<p class="description">' . esc_html__( 'No report yet.', 'wpcredits-program-manager' ) . '</p>';
			}
		} else {
			echo '<ul class="wpcpm-list">';

			foreach ( $reports as $post ) {
				printf(
					'<li>%1$s <span class="description">%2$s</span> <a href="%3$s">%4$s<span class="screen-reader-text"> %1$s</span></a></li>',
					esc_html( (string) $post->post_title ),
					esc_html( self::standing( $post ) ),
					esc_url( self::editor_url( (int) $post->ID ) ),
					esc_html__( 'Open', 'wpcredits-program-manager' )
				);
			}

			echo '</ul>';
		}

		if ( '' !== $due ) {
			echo '<p class="description">' . esc_html( $due ) . '</p>';
		}

		if ( ! $drafts ) {
			echo '<p class="description">' . esc_html__( 'No report for this tier.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		self::render_draft_form( $record, $day );
	}

	/**
	 * The form that drafts a sponsor's report: the Reports card's own here, and Draft now on the
	 * Administrator Dashboard's Sponsor reports card.
	 *
	 * Here, beside the handler that reads it, so the action, the field names, the nonce keyed to the
	 * sponsor and the once-mark are written once and the two forms cannot drift apart; the Semester
	 * reports card draws its Draft now from its own screen's renderer the same way. On this screen
	 * the day is a date box a manager may change, filled with the day the card offers. Drawn for the
	 * Administrator Dashboard with a term's day, the day is posted as it is, hidden, the return
	 * fields bring a refusal back to that page's card, and the button names the sponsor and the day
	 * for a screen reader, since every row's button there says Draft now. A draft that is made opens
	 * in the editor either way.
	 *
	 * @param string $record Sponsor key.
	 * @param string $until  The period's last day, `Y-m-d`: the date box's first value, '' for an
	 *                       empty box; with `$where`, the day posted.
	 * @param string $where  Where a refusal comes back to, as `WPCPM_Return::field()` takes it:
	 *                       WPCPM_Return::DASHBOARD when drawn on the Administrator Dashboard, else
	 *                       ''. With no day, the form is the date box wherever it is drawn.
	 */
	public static function render_draft_form( $record, $until = '', $where = '' ) {
		$record = (string) $record;
		$until  = (string) $until;
		$where  = (string) $where;
		// Hidden only where the day is a term's own and the form is drawn for another page: on this
		// screen a manager may draft for any day the tier carried a report on.
		$hidden = '' !== $until && '' !== $where;

		printf(
			'<form method="post" action="%1$s" class="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $hidden ? 'wpcpm-administrator__form' : 'wpcpm-inline-form' ),
			esc_attr__( 'Drafting', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_DRAFT . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_DRAFT ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_RECORD ), esc_attr( $record ) );

		if ( $hidden ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_UNTIL ), esc_attr( $until ) );
		} else {
			printf(
				'<label for="%1$s">%2$s</label> <input type="date" id="%1$s" name="%3$s" value="%4$s" required /> ',
				esc_attr( 'wpcpm-sreport-until-' . $record ),
				esc_html__( 'Last day of the term', 'wpcredits-program-manager' ),
				esc_attr( self::FIELD_UNTIL ),
				esc_attr( $until )
			);
		}

		if ( '' !== $where && class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( $where, 'sponsor-reports' );
		}

		if ( $hidden ) {
			printf(
				'<button type="submit" class="wpcpm-button wpcpm-button--secondary">%1$s<span class="screen-reader-text"> %2$s</span></button>',
				esc_html__( 'Draft now', 'wpcredits-program-manager' ),
				esc_html(
					sprintf(
						/* translators: 1: the sponsor's name, 2: the last day of its term, in the site's date format. */
						__( 'for %1$s, the term ending %2$s', 'wpcredits-program-manager' ),
						self::sponsor_name( $record, WPCPM_Sponsors_Index::row( $record ) ),
						WPCPM_Sponsor_Tiers::format_day( $until )
					)
				)
			);
		} else {
			printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Draft a report', 'wpcredits-program-manager' ) );
		}

		echo '</form>';
	}

	/**
	 * The day the draft form offers.
	 *
	 * The owed term's last day while a renewal left one, since that is the report still due, and a
	 * draft for the renewed term's day would be the account of a year that has not happened. Otherwise
	 * the last day of the row's own term, and today for a sponsor with no term.
	 *
	 * @param array  $row   A sponsor's row.
	 * @param string $today `Y-m-d`, or '' for today.
	 * @return string `Y-m-d`.
	 */
	private static function default_until( array $row, $today ) {
		$owed = WPCPM_Sponsor_Report::owed_of( $row );

		if ( ! empty( $owed ) ) {
			return $owed['until'];
		}

		$until = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';

		if ( WPCPM_Sponsor_Tiers::is_date( $until ) ) {
			return $until;
		}

		return '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();
	}

	/**
	 * A report's state as the card lists it: a draft, or final since the day it was marked, and
	 * whether a PDF is attached.
	 *
	 * @param WP_Post $post The report.
	 * @return string
	 */
	private static function standing( WP_Post $post ) {
		$state = __( 'Draft', 'wpcredits-program-manager' );

		if ( WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post ) ) {
			$stamp = WPCPM_Sponsor_Report::final_at( $post );
			$state = empty( $stamp['at'] )
				? __( 'Final', 'wpcredits-program-manager' )
				: sprintf(
					/* translators: %s: the day the report was marked final, in the site's date format. */
					__( 'Final since %s', 'wpcredits-program-manager' ),
					(string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $stamp['at'] )
				);
		}

		if ( null === WPCPM_Sponsor_Report_Files::file_of( $post ) ) {
			return $state;
		}

		/* translators: %s: the report's state, such as "Draft" or "Final since October 14, 2026". */
		return sprintf( __( '%s, with a PDF attached', 'wpcredits-program-manager' ), $state );
	}

	/*
	 * --------------------------------------------------------------------
	 * The editor
	 * --------------------------------------------------------------------
	 */

	/**
	 * The editor, drawn on the Sponsors screen's Reports tab in place of its card when the address
	 * names a report: the report's title and facts, then the way back to the tab, which lists every
	 * Approved sponsor's reports, then the sections and the buttons.
	 *
	 * @param int $post_id The report the address names.
	 * @return bool Whether a report was drawn. False, with nothing printed, for an ID that names no
	 *              report, so the tab draws its card under a notice instead.
	 */
	public static function render_editor( $post_id ) {
		$post = self::report( $post_id );

		if ( null === $post ) {
			return false;
		}

		$snapshot = WPCPM_Sponsor_Report::snapshot( $post );
		$is_final = WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post );
		// What a refused Save of this report kept, taken whenever its editor is drawn, so it never waits
		// for a later visit: a final report has no boxes to draw it in (`keep_typing()`).
		$kept = WPCPM_Typed_Text::kept( self::typed_form( (int) $post->ID ) );

		echo '<div class="wpcpm-card wpcpm-sreport" id="wpcpm-sreport-editor">';
		echo '<h2>' . esc_html( (string) $post->post_title ) . '</h2>';

		self::render_facts( $post, $snapshot, $is_final );

		printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( self::screen_url( 'reports' ) ), esc_html__( 'Back to the reports', 'wpcredits-program-manager' ) );

		// A snapshot at a version this code does not know would be figures with the wrong things in
		// them, and has no sections to write under, so the editor says so and draws none.
		if ( empty( $snapshot ) ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html(
					$is_final
						? __( 'This report\'s figures cannot be read, so its sections are not shown. Reopen it, and Regenerate reads them again for the same twelve months; the narratives are kept.', 'wpcredits-program-manager' )
						: __( 'This report\'s figures cannot be read, so its sections are not shown. Regenerate reads them again for the same twelve months, and the narratives are kept.', 'wpcredits-program-manager' )
				)
			);
		} else {
			self::render_sections( $post, $snapshot, $is_final, $is_final ? array() : $kept );
		}

		self::render_actions( $post, $is_final, ! empty( $snapshot ) );

		echo '</div>';

		return true;
	}

	/**
	 * The report's period and state, who marked it final and when, and the day its figures were read.
	 *
	 * @param WP_Post $post     The report.
	 * @param array   $snapshot As `WPCPM_Sponsor_Report::snapshot()` answers it.
	 * @param bool    $is_final Whether it is final.
	 */
	private static function render_facts( WP_Post $post, array $snapshot, $is_final ) {
		$period = WPCPM_Sponsor_Report::period( WPCPM_Sponsor_Report::until_of( $post ) );
		$facts  = array();

		if ( '' !== $period['from'] ) {
			$facts[] = sprintf(
				/* translators: 1: the first day of the report's period, 2: its last day, both in the site's date format. */
				__( 'The twelve months from %1$s to %2$s.', 'wpcredits-program-manager' ),
				WPCPM_Sponsor_Tiers::format_day( $period['from'] ),
				WPCPM_Sponsor_Tiers::format_day( $period['until'] )
			);
		}

		$facts[] = $is_final ? self::final_stamp( $post ) : __( 'A draft: the sponsor sees nothing of it until it is marked final.', 'wpcredits-program-manager' );

		printf( '<p class="wpcpm-sreport__facts">%s</p>', esc_html( implode( ' ', $facts ) ) );

		if ( ! empty( $snapshot['as_of'] ) && WPCPM_Sponsor_Tiers::is_date( $snapshot['as_of'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the day the report's figures were read, in the site's date format. */
						__( 'Figures read from the program\'s records on %s.', 'wpcredits-program-manager' ),
						WPCPM_Sponsor_Tiers::format_day( $snapshot['as_of'] )
					)
				)
			);
		}
	}

	/**
	 * Who marked a report final and when, from its stamp.
	 *
	 * @param WP_Post $post A final report.
	 * @return string
	 */
	private static function final_stamp( WP_Post $post ) {
		$stamp = WPCPM_Sponsor_Report::final_at( $post );

		if ( empty( $stamp['at'] ) ) {
			return __( 'Final.', 'wpcredits-program-manager' );
		}

		$date = (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $stamp['at'] );
		$time = (string) wp_date( (string) get_option( 'time_format', 'g:i a' ), (int) $stamp['at'] );
		$by   = $stamp['by'] > 0 ? get_user_by( 'id', $stamp['by'] ) : false;

		if ( $by instanceof WP_User ) {
			return sprintf(
				/* translators: 1: the day the report was marked final, 2: the time, 3: the Administrator who marked it. */
				__( 'Final: marked final on %1$s at %2$s by %3$s.', 'wpcredits-program-manager' ),
				$date,
				$time,
				$by->display_name
			);
		}

		return sprintf(
			/* translators: 1: the day the report was marked final, 2: the time. */
			__( 'Final: marked final on %1$s at %2$s.', 'wpcredits-program-manager' ),
			$date,
			$time
		);
	}

	/**
	 * The report's sections in the order it prints them, the narratives inside the one form that
	 * saves them, or read-only once the report is final.
	 *
	 * @param WP_Post $post     The report.
	 * @param array   $snapshot As `WPCPM_Sponsor_Report::snapshot()` answers it, readable.
	 * @param bool    $is_final Whether it is final.
	 * @param array   $kept     What a refused Save of the report kept, narrative key => what was
	 *                          typed, or an empty array.
	 */
	private static function render_sections( WP_Post $post, array $snapshot, $is_final, array $kept = array() ) {
		$sections   = WPCPM_Sponsor_Report::sections_for( $snapshot['sponsorship']['tier'], $snapshot['tracks'] );
		$narratives = WPCPM_Sponsor_Report::narratives( $post );
		$versions   = self::versions_of( $post );

		if ( $is_final ) {
			printf( '<p class="description">%s</p>', esc_html__( 'The report is final, so its narratives are read-only: reopen it to change them.', 'wpcredits-program-manager' ) );
		} else {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-sreport__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Saving', 'wpcredits-program-manager' )
			);
			wp_nonce_field( self::ACTION_SAVE . '_' . (int) $post->ID );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
			printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( WPCPM_Sponsor_Report_Files::FIELD_POST ), (int) $post->ID );
		}

		foreach ( $sections as $key => $section ) {
			self::render_section( (string) $key, $section, $snapshot, $narratives, $versions, $is_final, $kept );
		}

		if ( ! $is_final ) {
			printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Save the narratives', 'wpcredits-program-manager' ) );
			echo '</form>';
		}
	}

	/**
	 * One section: its figures as tables, its narrative box, or both once for each track it is
	 * drawn for. A generated section's first table is captioned with the section's heading, and a
	 * track's table and box with the heading and the track's name.
	 *
	 * @param string   $key        The section's key.
	 * @param array    $section    As `WPCPM_Sponsor_Report::sections_for()` answers it.
	 * @param array    $snapshot   The report's snapshot.
	 * @param array    $narratives As `WPCPM_Sponsor_Report::narratives()` answers them.
	 * @param int|null $versions   How many saved versions of the report are kept; null on a site
	 *                             that keeps none.
	 * @param bool     $is_final   Whether the report is final.
	 * @param array    $kept       What a refused Save of the report kept, narrative key => what was
	 *                             typed, or an empty array.
	 */
	private static function render_section( $key, array $section, array $snapshot, array $narratives, $versions, $is_final, array $kept = array() ) {
		if ( WPCPM_Sponsor_Report::SECTION_PROGRAM === $key ) {
			self::render_table( $section['title'], array(), WPCPM_Sponsor_Report_Files::figure_rows( $snapshot['program'], WPCPM_Sponsor_Report_Numbers::COUNTS ) );

			return;
		}

		if ( WPCPM_Sponsor_Report::SECTION_SPONSORSHIP === $key ) {
			self::render_sponsorship( $section['title'], $snapshot['sponsorship'] );

			return;
		}

		if ( ! empty( $section['per_track'] ) ) {
			$figures = array();

			foreach ( $snapshot['tracks'] as $track ) {
				$figures[ $track['key'] ] = $track;
			}

			foreach ( $section['per_track'] as $track => $label ) {
				$heading = self::track_heading( $section['title'], $label );

				if ( isset( $figures[ $track ] ) ) {
					self::render_table( $heading, array(), WPCPM_Sponsor_Report_Files::figure_rows( $figures[ $track ], WPCPM_Sponsor_Report_Numbers::TRACK_COUNTS ) );
				}

				self::render_narrative( $key . '_' . $track, $heading, $narratives, $versions, $is_final, $kept );
			}

			return;
		}

		if ( ! empty( $section['narrative'] ) ) {
			self::render_narrative( $key, $section['title'], $narratives, $versions, $is_final, $kept );
		}
	}

	/**
	 * A track's heading in a section drawn once per track: the section's heading and the track's
	 * name. Its table is captioned with it and its narrative's box labeled, and a Save refused for
	 * that box's length names it so.
	 *
	 * @param string $title The section's heading.
	 * @param string $label The track's name.
	 * @return string
	 */
	private static function track_heading( $title, $label ) {
		return sprintf(
			/* translators: 1: the section's heading, "The track" or "The tracks", 2: a track's name. */
			__( '%1$s: %2$s', 'wpcredits-program-manager' ),
			$title,
			$label
		);
	}

	/**
	 * A narrative's box label, as the editor draws it (`render_section()`): its section's heading,
	 * or for a section drawn once per track, the track's heading (`track_heading()`).
	 *
	 * @param WP_Post $post The report.
	 * @param string  $key  The narrative's key, as `WPCPM_Sponsor_Report::narrative_keys()` names it.
	 * @return string The key itself for a key no section of the report takes.
	 */
	private static function narrative_label( WP_Post $post, $key ) {
		foreach ( WPCPM_Sponsor_Report::sections_of( $post ) as $section_key => $section ) {
			if ( ! empty( $section['per_track'] ) ) {
				foreach ( $section['per_track'] as $track => $label ) {
					if ( $section_key . '_' . $track === (string) $key ) {
						return self::track_heading( $section['title'], $label );
					}
				}

				continue;
			}

			if ( (string) $section_key === (string) $key ) {
				return (string) $section['title'];
			}
		}

		return (string) $key;
	}

	/**
	 * The sponsorship: the brief under the section's heading, the events held in the term when one
	 * was, then the mentors and the claims by month, or a sentence where either has none; the rows
	 * the printed report prints.
	 *
	 * @param string $title       The section's heading.
	 * @param array  $sponsorship The snapshot's sponsorship.
	 */
	private static function render_sponsorship( $title, array $sponsorship ) {
		$rows = WPCPM_Sponsor_Report_Files::sponsorship_rows( $sponsorship );

		self::render_table( $title, array(), $rows['brief'] );

		// The printed report's table under its own caption, and, as there, nothing where none was held.
		if ( ! empty( $rows['events']['rows'] ) ) {
			self::render_table( __( 'Events held in this term', 'wpcredits-program-manager' ), $rows['events']['head'], $rows['events']['rows'] );
		}

		if ( empty( $rows['mentors']['rows'] ) ) {
			printf( '<p class="description">%s</p>', esc_html__( 'No mentor was linked to this sponsor when the report was generated.', 'wpcredits-program-manager' ) );
		} else {
			self::render_table( __( 'The mentors sponsored, and the students who started with each in these twelve months', 'wpcredits-program-manager' ), $rows['mentors']['head'], $rows['mentors']['rows'] );
		}

		if ( empty( $rows['claims']['rows'] ) ) {
			printf( '<p class="description">%s</p>', esc_html__( 'None of the sponsor\'s offers was claimed in these months.', 'wpcredits-program-manager' ) );

			return;
		}

		self::render_table( __( 'Offers and claims by calendar month', 'wpcredits-program-manager' ), $rows['claims']['head'], $rows['claims']['rows'] );
	}

	/**
	 * One table of figures: a caption, a header row when there are columns to name, and rows whose
	 * first cell names the row. Every cell is text, but a profile's link, whose text is its address.
	 *
	 * @param string $caption The caption.
	 * @param array  $head    The columns' names, or none.
	 * @param array  $rows    Each a list of cells: a string, or `array( 'link' => $url )`.
	 */
	private static function render_table( $caption, array $head, array $rows ) {
		echo '<table class="widefat striped wpcpm-sreport__table"><caption>' . esc_html( $caption ) . '</caption>';

		if ( ! empty( $head ) ) {
			echo '<thead><tr>';

			foreach ( $head as $name ) {
				echo '<th scope="col">' . esc_html( $name ) . '</th>';
			}

			echo '</tr></thead>';
		}

		echo '<tbody>';

		foreach ( $rows as $cells ) {
			echo '<tr>';

			foreach ( array_values( (array) $cells ) as $index => $cell ) {
				if ( 0 === $index ) {
					printf( '<th scope="row">%s</th>', wp_kses( self::cell( $cell ), array( 'a' => array( 'href' => array() ) ) ) );
				} else {
					printf( '<td>%s</td>', wp_kses( self::cell( $cell ), array( 'a' => array( 'href' => array() ) ) ) );
				}
			}

			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * One cell's markup: its text escaped, or a link whose text is its address.
	 *
	 * @param mixed $cell A string, or `array( 'link' => $url )`.
	 * @return string
	 */
	private static function cell( $cell ) {
		if ( is_array( $cell ) ) {
			$url = isset( $cell['link'] ) && is_scalar( $cell['link'] ) ? (string) $cell['link'] : '';

			return '' === $url ? '' : '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
		}

		return esc_html( is_scalar( $cell ) ? (string) $cell : '' );
	}

	/**
	 * One narrative's box, labeled, with the count of the report's saved versions beside the label,
	 * or nothing beside it on a site that keeps no revisions, where there would be none to count.
	 *
	 * A final report's box is read-only and carries no name, since there is no form to post it: the
	 * store takes no save until the report is reopened.
	 *
	 * Either box draws the text as the manager typed it, by the rules every box of typed text uses
	 * (`WPCPM_Typed_Text`): through `esc_textarea( typed_text() )`, with the `maxlength`
	 * `drawn_limit()` gives the draft's. Drawn as stored, it would show the cleaner's entities, `&lt;`
	 * where a "<" was typed, and a box holding "We <3 our team > all" would post a "tag" the cleaner
	 * drops, words and all. After a refused Save, the draft's box is drawn with what was typed,
	 * exactly, in the same room (`keep_typing()`).
	 *
	 * @param string   $key        The narrative's key, as `WPCPM_Sponsor_Report::narrative_keys()` names it.
	 * @param string   $label      Its label.
	 * @param array    $narratives As `WPCPM_Sponsor_Report::narratives()` answers them.
	 * @param int|null $versions   How many saved versions of the report are kept; null on a site that
	 *                             keeps none.
	 * @param bool     $is_final   Whether the report is final.
	 * @param array    $kept       What a refused Save of the report kept, narrative key => what was
	 *                             typed, or an empty array.
	 */
	private static function render_narrative( $key, $label, array $narratives, $versions, $is_final, array $kept = array() ) {
		$id    = 'wpcpm-sreport-text-' . sanitize_html_class( $key );
		$text  = isset( $narratives[ $key ] ) ? (string) $narratives[ $key ] : '';
		$typed = isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? $kept[ $key ] : null;

		if ( null === $versions ) {
			printf( '<div class="wpcpm-sreport__field"><p><label for="%1$s">%2$s</label></p>', esc_attr( $id ), esc_html( $label ) );
		} else {
			printf(
				'<div class="wpcpm-sreport__field"><p><label for="%1$s">%2$s</label> <span class="description">%3$s</span></p>',
				esc_attr( $id ),
				esc_html( $label ),
				esc_html(
					$versions > 0
						? sprintf(
							/* translators: %s: how many saved versions of the report are kept. */
							_n( '%s saved version', '%s saved versions', $versions, 'wpcredits-program-manager' ),
							number_format_i18n( $versions )
						)
						: __( 'No saved versions yet.', 'wpcredits-program-manager' )
				)
			);
		}

		if ( $is_final ) {
			printf( '<textarea id="%1$s" rows="%2$d" class="large-text" readonly="readonly">%3$s</textarea>', esc_attr( $id ), (int) self::TEXT_ROWS, esc_textarea( WPCPM_Typed_Text::typed_text( $text ) ) );
		} else {
			printf(
				'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text" maxlength="%4$d">%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( self::text_field( $key ) ),
				(int) self::TEXT_ROWS,
				(int) WPCPM_Typed_Text::drawn_limit( $text, WPCPM_Sponsor_Report::MAX_TEXT ),
				// The parser drops one line feed right after the opening tag, so a kept typing, which
				// can begin with a line break, is given one of its own first.
				null !== $typed ? "\n" . esc_textarea( $typed ) : esc_textarea( WPCPM_Typed_Text::typed_text( $text ) )
			);
		}

		echo '</div>';
	}

	/**
	 * Preview, the PDF, and the state's buttons: Mark final or Reopen, and Regenerate for a draft.
	 *
	 * The PDF can be attached, replaced or removed on a final report too, since a report is marked
	 * final, printed, and then attached. The forms are the files class's routes, posted with its
	 * field names and its nonces.
	 *
	 * @param WP_Post $post     The report.
	 * @param bool    $is_final Whether it is final.
	 * @param bool    $readable Whether its snapshot can be read. A report that cannot be read is not
	 *                          offered Mark final, which would give the sponsor a report of nothing.
	 */
	private static function render_actions( WP_Post $post, $is_final, $readable ) {
		$post_id = (int) $post->ID;
		$file    = WPCPM_Sponsor_Report_Files::file_of( $post );

		echo '<h3>' . esc_html__( 'Preview and PDF', 'wpcredits-program-manager' ) . '</h3>';
		printf(
			'<p><a class="button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> <span class="description">%3$s</span></p>',
			esc_url( WPCPM_Sponsor_Report_Files::print_url( $post ) ),
			esc_html__( 'Preview', 'wpcredits-program-manager' ),
			esc_html__( 'The report as the sponsor prints it, in a new tab: print it from there, or save it as a PDF to attach below.', 'wpcredits-program-manager' )
		);

		if ( null === $file ) {
			echo '<p>' . esc_html__( 'No PDF is attached yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			$name = trim( (string) get_post_meta( $post_id, WPCPM_Sponsor_Report::META_FILE_NAME, true ) );

			printf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html(
					'' === $name
						/* translators: %s: the attached PDF's size. */
						? sprintf( __( 'A PDF is attached, %s.', 'wpcredits-program-manager' ), size_format( $file['size'] ) )
						/* translators: 1: the name the PDF was attached under, 2: its size. */
						: sprintf( __( 'A PDF is attached: %1$s, %2$s.', 'wpcredits-program-manager' ), $name, size_format( $file['size'] ) )
				),
				esc_url( WPCPM_Sponsor_Report_Files::download_url( $post ) ),
				esc_html__( 'Download it', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sreport__pdf" enctype="multipart/form-data" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Attaching', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Sponsor_Report_Files::ACTION_UPLOAD . '_' . $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Sponsor_Report_Files::ACTION_UPLOAD ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( WPCPM_Sponsor_Report_Files::FIELD_POST ), (int) $post_id );
		printf(
			'<label for="wpcpm-sreport-file">%1$s</label> <input type="file" id="wpcpm-sreport-file" name="%2$s" accept="application/pdf,.pdf" required /> <button type="submit" class="button">%3$s</button>',
			esc_html__( 'The PDF', 'wpcredits-program-manager' ),
			esc_attr( WPCPM_Sponsor_Report_Files::FIELD_FILE ),
			null === $file ? esc_html__( 'Attach the PDF', 'wpcredits-program-manager' ) : esc_html__( 'Replace the PDF', 'wpcredits-program-manager' )
		);
		echo '</form>';

		if ( null !== $file ) {
			self::render_button(
				WPCPM_Sponsor_Report_Files::ACTION_REMOVE,
				$post_id,
				__( 'Removing', 'wpcredits-program-manager' ),
				__( 'Remove the PDF', 'wpcredits-program-manager' ),
				'button-link-delete',
				__( 'Remove the PDF from this report? The file is deleted from this site.', 'wpcredits-program-manager' )
			);
		}

		echo '<h3>' . esc_html__( 'Draft and final', 'wpcredits-program-manager' ) . '</h3>';

		if ( $is_final ) {
			self::render_button( self::ACTION_REOPEN, $post_id, __( 'Reopening', 'wpcredits-program-manager' ), __( 'Reopen', 'wpcredits-program-manager' ), 'button', '' );
			printf( '<p class="description">%s</p>', esc_html__( 'Reopened, the report is a draft again: the sponsor no longer sees it, and its narratives and figures can change.', 'wpcredits-program-manager' ) );

			return;
		}

		// Each is a form of its own, which posts no box, so each asks first: words typed and not saved
		// would be gone. Save the narratives stays the page's one primary button.
		$notes = array();

		if ( $readable ) {
			self::render_button(
				self::ACTION_FINAL,
				$post_id,
				__( 'Marking final', 'wpcredits-program-manager' ),
				__( 'Mark final', 'wpcredits-program-manager' ),
				'button',
				__( 'Mark this report final? Words typed above and not saved are not kept: save the narratives first.', 'wpcredits-program-manager' )
			);

			$notes[] = __( 'Marked final, the report is the sponsor\'s to read on the Sponsor Dashboard while Sponsor tiers is switched on in Settings, and its narratives and figures wait for a reopen.', 'wpcredits-program-manager' );
		}

		self::render_button(
			self::ACTION_REGENERATE,
			$post_id,
			__( 'Reading the program records', 'wpcredits-program-manager' ),
			__( 'Regenerate', 'wpcredits-program-manager' ),
			'button',
			__( 'Read the figures again from the program\'s records, for the same twelve months? The saved narratives are kept; words typed above and not saved are not.', 'wpcredits-program-manager' )
		);

		$notes[] = __( 'Regenerate reads the figures again and keeps the saved narratives.', 'wpcredits-program-manager' );

		printf( '<p class="description">%s</p>', esc_html( implode( ' ', $notes ) ) );
	}

	/**
	 * A one-button form posting to `admin-post.php` about one report, with its nonce keyed to it.
	 *
	 * @param string $action  The `admin_post_` action.
	 * @param int    $post_id The report.
	 * @param string $busy    What the pressed button says while the request is in flight.
	 * @param string $label   The button.
	 * @param string $classes The button's classes.
	 * @param string $confirm A question asked before it posts, or '' for none: the button's
	 *                        `data-wpcpm-confirm`, as the Sponsors screen's own forms carry theirs,
	 *                        which forms.js (wp-admin loads it too) asks before its guard sees a
	 *                        submit, so a No ends the press there.
	 */
	private static function render_button( $action, $post_id, $busy, $label, $classes, $confirm ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy )
		);
		wp_nonce_field( $action . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( WPCPM_Sponsor_Report_Files::FIELD_POST ), (int) $post_id );

		if ( '' !== $confirm ) {
			printf( '<button type="submit" class="%1$s" data-wpcpm-confirm="%2$s">%3$s</button>', esc_attr( $classes ), esc_attr( $confirm ), esc_html( $label ) );
		} else {
			printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $classes ), esc_html( $label ) );
		}

		echo '</form>';
	}

	/*
	 * --------------------------------------------------------------------
	 * Not a renewal
	 * --------------------------------------------------------------------
	 */

	/**
	 * Not a renewal's form, for the Sponsors screen to draw after a sponsor's Edit form while the row
	 * owes a report, and nothing while it owes none. The Tier box's button inside the Edit form posts
	 * it through its `form` attribute, since a form cannot sit inside another.
	 *
	 * The question is the form's own `data-wpcpm-confirm`, as the offer's Void form carries its own:
	 * the button sits outside the form, so forms.js finds no marked control in it to read, and asks
	 * the form's question whatever the browser says was pressed.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    Its row.
	 */
	public static function render_not_renewal_form( $record, array $row ) {
		if ( empty( WPCPM_Sponsor_Report::owed_of( $row ) ) ) {
			return;
		}

		printf(
			'<form method="post" action="%1$s" id="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s" data-wpcpm-confirm="%4$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::NOT_RENEWAL_FORM ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			esc_attr__( 'Say that no report is owed for that term? Do this only when its last day was moved to correct it, not to renew, since that term then gets no report.', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_NOT_RENEWAL . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_NOT_RENEWAL ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_RECORD ), esc_attr( $record ) );
		echo '</form>';
	}

	/*
	 * --------------------------------------------------------------------
	 * The six forms
	 * --------------------------------------------------------------------
	 */

	/**
	 * Draft a sponsor's report for the period ending on the day the form names.
	 *
	 * The capability, then the nonce keyed to the sponsor, then the store, which decides everything
	 * else and refuses with its reason: a sponsor it does not hold, a value that is not a day, a term
	 * whose report exists, a tier that carries none on that day, a draft of the sponsor's reports that
	 * the daily run or another manager has under way. A refusal lands on the Reports tab, the card it
	 * was pressed on, or back on the Administrator Dashboard when it was pressed there. A draft that is
	 * made opens in the editor wherever it was pressed, because writing in it is what a manager does
	 * next.
	 */
	public static function handle_draft() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( self::FIELD_RECORD );

		check_admin_referer( self::ACTION_DRAFT . '_' . $record );

		$made = WPCPM_Sponsor_Report::generate( $record, WPCPM_Request::posted_text( self::FIELD_UNTIL ) );

		if ( is_wp_error( $made ) ) {
			self::leave( self::draft_refusal( $made ), self::screen_url( 'reports' ) );
		}

		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sreport-drafted' );
		wp_safe_redirect( self::editor_url( (int) $made ) );
		exit;
	}

	/**
	 * Save every narrative the editor's form sent.
	 *
	 * Only a box the form drew is saved, empty or not. A page drawn before a regeneration changed the
	 * report's sections does not send the boxes it never drew, and their words stay as they are.
	 *
	 * A box whose changed text the cleaner would take words from refuses the whole save before
	 * anything is stored (`WPCPM_Typed_Text::cleaner_loses()`): "Costs <25, adults >18 pay" would be
	 * stored as "Costs 18 pay". The first such box, in the report's order, is named by the label the
	 * press carries (`FLASH_LOSS`, `sentence_for()`). A box posted back as it was drawn is the stored
	 * text and is not asked: an older store can have kept what the cleaner would take, and only what a
	 * manager changed is theirs to be told about. It is not handed to the store either, which would
	 * clean it and write it back without those words, so it keeps its stored text whatever its length.
	 *
	 * A box whose changed text is longer than `WPCPM_Sponsor_Report::MAX_TEXT` as the manager typed
	 * it refuses the whole save before anything is stored (`WPCPM_Sponsor_Report::long_narrative()`),
	 * and the notice names it, by the label the press carries (`FLASH_LONG`, `sentence_for()`).
	 *
	 * Either refusal keeps every narrative box of the report as it was typed, for the one time the
	 * editor is drawn again (`keep_typing()`), since one box refuses the save of all of them.
	 *
	 * The store is the one that decides. It answers yes once the words are stored, even when
	 * WordPress would not take the version, and for a save that changed none, which stores nothing
	 * and says so, as the Edit form does (`WPCPM_Sponsor_Report::changes_narratives()`, asked
	 * beforehand); and no only for a final report, one whose figures cannot be read, which has no
	 * sections to write under, and a text over its limit; the flash says which.
	 */
	public static function handle_save() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( WPCPM_Sponsor_Report_Files::FIELD_POST );

		check_admin_referer( self::ACTION_SAVE . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::leave( 'sreport-gone', self::screen_url( 'reports' ) );
		}

		$form  = self::typed_form( $post_id );
		$texts = array();
		$typed = array();

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		foreach ( WPCPM_Sponsor_Report::narrative_keys( WPCPM_Sponsor_Report::sections_of( $post ) ) as $key ) {
			// Null is what a field that was not sent reads as, and no box sends it.
			$text = WPCPM_Request::posted_lines( self::text_field( $key ), null );

			if ( null !== $text ) {
				$texts[ $key ] = $text;
				$typed[ $key ] = WPCPM_Request::posted_raw( self::text_field( $key ) );
			}
		}

		// A final report refuses every save as final, whatever its boxes hold.
		if ( WPCPM_Sponsor_Report::STATE_FINAL !== WPCPM_Sponsor_Report::state( $post ) ) {
			$stored = WPCPM_Sponsor_Report::narratives( $post );

			foreach ( $typed as $key => $raw ) {
				$was = isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';

				// Read trimmed, as the cleaner reads what is posted: a text area drops a line break its
				// text begins with, and an older store could keep a space or a break at either end.
				if ( WPCPM_Typed_Text::same_lines( trim( $raw ), trim( $was ) ) ) {
					unset( $texts[ $key ] );

					continue;
				}

				if ( WPCPM_Typed_Text::cleaner_loses( $raw, 'lines' ) ) {
					self::keep_typing( $form, $typed, $stored );
					WPCPM_Flash::set( self::FLASH_LOSS, self::narrative_label( $post, $key ) );
					self::leave( 'sreport-loss', self::editor_url( $post_id ) );
				}
			}

			$long = WPCPM_Sponsor_Report::long_narrative( $post, $texts );

			if ( ! empty( $long ) ) {
				self::keep_typing( $form, $typed, $stored );
				WPCPM_Flash::set( self::FLASH_LONG, self::narrative_label( $post, $long['key'] ) );
				self::leave( 'sreport-too-long', self::editor_url( $post_id ) );
			}
		}

		$changes = WPCPM_Sponsor_Report::changes_narratives( $post, $texts );

		if ( WPCPM_Sponsor_Report::save_narratives( $post, $texts, get_current_user_id() ) ) {
			self::leave( $changes ? 'sreport-saved' : 'sreport-unchanged', self::editor_url( $post_id ) );
		}

		self::leave( WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post ) ? 'sreport-save-final' : 'sreport-unreadable', self::editor_url( $post_id ) );
	}

	/**
	 * Mark a draft final: the store stamps who and when, and writes the row on the trail.
	 *
	 * Refused for a draft whose figures cannot be read, which the sponsor would read as a report of
	 * nothing: the editor draws no Mark final for one, and a form drawn before its figures went
	 * unreadable is refused here.
	 */
	public static function handle_final() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( WPCPM_Sponsor_Report_Files::FIELD_POST );

		check_admin_referer( self::ACTION_FINAL . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::leave( 'sreport-gone', self::screen_url( 'reports' ) );
		}

		if ( WPCPM_Sponsor_Report::STATE_FINAL !== WPCPM_Sponsor_Report::state( $post ) && empty( WPCPM_Sponsor_Report::sections_of( $post ) ) ) {
			self::leave( 'sreport-final-unreadable', self::editor_url( $post_id ) );
		}

		$done = WPCPM_Sponsor_Report::finalize( $post, get_current_user_id() );

		self::leave( $done ? 'sreport-final' : 'sreport-final-already', self::editor_url( $post_id ) );
	}

	/**
	 * Make a final report a draft again: the store drops the stamp with the state.
	 */
	public static function handle_reopen() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( WPCPM_Sponsor_Report_Files::FIELD_POST );

		check_admin_referer( self::ACTION_REOPEN . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::leave( 'sreport-gone', self::screen_url( 'reports' ) );
		}

		$done = WPCPM_Sponsor_Report::reopen( $post, get_current_user_id() );

		self::leave( $done ? 'sreport-reopened' : 'sreport-draft-already', self::editor_url( $post_id ) );
	}

	/**
	 * Read a draft's figures again into the same report, keeping its narratives, which the store says
	 * on the trail. A final report is reopened first.
	 */
	public static function handle_regenerate() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( WPCPM_Sponsor_Report_Files::FIELD_POST );

		check_admin_referer( self::ACTION_REGENERATE . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::leave( 'sreport-gone', self::screen_url( 'reports' ) );
		}

		if ( WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post ) ) {
			self::leave( 'sreport-regenerate-final', self::editor_url( $post_id ) );
		}

		$done = WPCPM_Sponsor_Report::regenerate( $post );

		self::leave( $done ? 'sreport-regenerated' : 'sreport-regenerate-refused', self::editor_url( $post_id ) );
	}

	/**
	 * Clear the term a moved day left owed its report: the Tier box's Not a renewal, for a day that was
	 * corrected rather than renewed, so that no report is drafted for a term that never was.
	 *
	 * The capability, then the nonce keyed to the sponsor, then the row. The owed term is read by the
	 * store's own rule, and a row that owes none, or holds half of a term, has nothing to clear. The
	 * row on the trail names the term's last day and tier, the facts it cleared, and the notice names
	 * the day, carried beside the status (`sentence_for()`).
	 *
	 * Every outcome comes back to the sponsor's Edit form, where the button was pressed
	 * (`WPCPM_Sponsors::edit_url()`), but a sponsor the store no longer holds, which has no form to
	 * come back to: that press comes back to the Sponsors tab, which lists every sponsor there is.
	 */
	public static function handle_not_renewal() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( self::FIELD_RECORD );

		check_admin_referer( self::ACTION_NOT_RENEWAL . '_' . $record );

		$row = WPCPM_Sponsors_Index::row( $record );

		if ( null === $row ) {
			self::leave( 'sreport-owed-gone', self::screen_url( 'sponsors' ) );
		}

		$owed = WPCPM_Sponsor_Report::owed_of( $row );
		$edit = WPCPM_Sponsors::edit_url( $record );

		if ( empty( $owed ) ) {
			self::leave( 'sreport-owed-none', $edit );
		}

		if ( ! WPCPM_Sponsors_Index::patch( $record, array( 'report_owed' => array() ) ) ) {
			self::leave( 'sreport-owed-not-saved', $edit );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => WPCPM_Sponsor_Report::LOG_OWED_CLEARED,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: %s: the last day of the term, in the site's date format. */
					__( 'No sponsor report is owed for the term ending %s: an Administrator said its last day was moved to correct it, not to renew.', 'wpcredits-program-manager' ),
					WPCPM_Sponsor_Tiers::format_day( $owed['until'] )
				),
				'data'     => array(
					'until' => $owed['until'],
					'tier'  => $owed['tier'],
				),
			)
		);

		WPCPM_Flash::set( self::FLASH_CLEARED, (string) $owed['until'] );
		self::leave( 'sreport-owed-cleared', $edit );
	}

	/*
	 * --------------------------------------------------------------------
	 * Internals
	 * --------------------------------------------------------------------
	 */

	/**
	 * A report by its ID, or null: a post that is not a report, is not in the store's own status, or
	 * is not there at all. The files class's rule, so a report the one refuses the other refuses too.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	private static function report( $post_id ) {
		$post = (int) $post_id > 0 ? get_post( (int) $post_id ) : null;

		if ( ! $post instanceof WP_Post || WPCPM_Sponsor_Report::POST_TYPE !== $post->post_type || 'private' !== $post->post_status ) {
			return null;
		}

		return $post;
	}

	/**
	 * A sponsor's name as this screen and its draft form print it: its row's, trimmed, or its key
	 * when the row names none.
	 *
	 * @param string     $record Sponsor key.
	 * @param array|null $row    Its row, as the sponsors index answers it.
	 * @return string
	 */
	private static function sponsor_name( $record, $row ) {
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : (string) $record;
	}

	/**
	 * Where the press lands, with its outcome flashed where that page reads it, and stop.
	 *
	 * The posted destination is never followed as a URL: `WPCPM_Return` maps a press made on the
	 * Administrator Dashboard to that page, and anything else to the place on the Sponsors screen this
	 * class names. The flash follows the destination, since the dashboard reads a channel of its own,
	 * as the sponsor agreement's and the application queue's decisions flash there.
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $url    Where on the Sponsors screen.
	 */
	private static function leave( $status, $url ) {
		$target = class_exists( 'WPCPM_Return' ) ? WPCPM_Return::url( $url ) : $url;

		WPCPM_Flash::set( $target === $url ? WPCPM_Sponsors::FLASH : WPCPM_Institutions::FLASH, $status );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * The status a refused draft flashes, by the store's refusal.
	 *
	 * @param WP_Error $error What `WPCPM_Sponsor_Report::generate()` answered.
	 * @return string A key of `messages()`. A refusal the map does not name, the database's own, is
	 *                the site's failure to create the report.
	 */
	private static function draft_refusal( WP_Error $error ) {
		$statuses = array(
			'wpcpm_sreport_exists'  => 'sreport-exists',
			'wpcpm_sreport_date'    => 'sreport-bad-day',
			'wpcpm_sreport_sponsor' => 'sreport-no-sponsor',
			'wpcpm_sreport_tier'    => 'sreport-no-tier',
			'busy'                  => 'sreport-busy',
		);
		$code     = (string) $error->get_error_code();

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'sreport-not-created';
	}

	/**
	 * How many saved versions of a report are kept: its revisions, each holding the narratives and
	 * the figures as they stood when it was saved.
	 *
	 * @param WP_Post $post The report.
	 * @return int|null Null on a site that keeps no revisions (`wp_revisions_to_keep()` answering 0),
	 *                  where there is nothing to count and "no saved versions yet" would never come
	 *                  true.
	 */
	private static function versions_of( WP_Post $post ) {
		if ( ! wp_revisions_enabled( $post ) ) {
			return null;
		}

		return count( (array) wp_get_post_revisions( (int) $post->ID, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * A report's editor, as `WPCPM_Typed_Text::keep()` names its Save form: the form, and the report it
	 * saves, so a refused Save's typing comes back in that report's editor and in no other.
	 *
	 * @param int $post_id The report.
	 * @return string
	 */
	private static function typed_form( $post_id ) {
		return 'sreport:' . (int) $post_id;
	}

	/**
	 * Keep what a refused Save held for the one time the editor is drawn again (`render_editor()`):
	 * every narrative box the form sent, as typed, each up to the room its box was drawn with, and never
	 * less than the text it was drawn with (`WPCPM_Typed_Text::kept_room()`), so a narrative an older
	 * store kept over its limit comes back whole.
	 *
	 * @param string $form   The form, as `typed_form()` names it.
	 * @param array  $typed  Narrative key => what was typed, unslashed and not cleaned.
	 * @param array  $stored The narratives the boxes were drawn from
	 *                       (`WPCPM_Sponsor_Report::narratives()`).
	 */
	private static function keep_typing( $form, array $typed, array $stored ) {
		$room = array();

		foreach ( array_keys( $typed ) as $key ) {
			$room[ $key ] = WPCPM_Typed_Text::kept_room( isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '', WPCPM_Sponsor_Report::MAX_TEXT );
		}

		WPCPM_Typed_Text::keep( $form, $typed, $room );
	}

	/**
	 * The field a narrative's box posts under.
	 *
	 * @param string $key The narrative's key.
	 * @return string
	 */
	private static function text_field( $key ) {
		return self::FIELD_TEXT . sanitize_key( $key );
	}

	/**
	 * The Sponsors screen at one of its tabs: `reports`, which draws the Reports card and, as its view
	 * of one report, the editor in the card's place; or `sponsors`, which lists every sponsor and draws
	 * a sponsor's Edit form, where Not a renewal is pressed.
	 *
	 * @param string $tab `reports` or `sponsors`.
	 * @return string
	 */
	private static function screen_url( $tab ) {
		return admin_url( 'admin.php?page=wpcpm-sponsors&tab=' . $tab );
	}
}
