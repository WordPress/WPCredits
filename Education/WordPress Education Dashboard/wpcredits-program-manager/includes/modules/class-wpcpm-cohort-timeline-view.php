<?php
/**
 * The cohort timeline, drawn: the Administrator Dashboard's Upcoming cohorts card and the
 * full-width timeline view it opens (design of 10 November 2026).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws what `WPCPM_Cohort_Timeline` computed, and nothing it did not.
 *
 * **The view is the dashboard page itself**, at its own address with `?wpcpm_view=cohort-timeline`,
 * so it is gated exactly as the page is (its access level and `CAP_MANAGE`, checked by
 * `WPCPM_Administrators_Dashboard::render()` before this class is asked for anything), and there is
 * no second page to create, adopt or lose.
 *
 * **The chart is HTML and CSS**: one column per month, stacked from the baseline in the three kinds
 * (already on the program, confirmed and starting, in review), with the mentors' places as one line
 * across. No script and no library: the heights are percentages printed on each segment. Identity is
 * never color alone: the legend names the three, the review layer carries a diagonal hatch, and the
 * month table under the chart holds every number the chart draws. Each column is a tab stop whose
 * accessible name says its numbers, and whose tooltip shows them on hover and focus.
 */
final class WPCPM_Cohort_Timeline_View {

	/** The query argument the view is asked for by, and its one value. */
	const ARG  = 'wpcpm_view';
	const VIEW = 'cohort-timeline';

	/** The card's key: its anchor is `wpcpm-cohort-timeline`. */
	const CARD = 'cohort-timeline';

	/** The stylesheet handle. */
	const STYLE = 'wpcpm-cohort-timeline';

	/**
	 * Register (but do not enqueue) the stylesheet, over the Administrator Dashboard's.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/cohort-timeline.css',
				array( WPCPM_Administrators_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}
	}

	/**
	 * Whether this request asks the dashboard page for the timeline view.
	 *
	 * @return bool
	 */
	public static function is_requested() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		return isset( $_GET[ self::ARG ] ) && self::VIEW === sanitize_key( wp_unslash( $_GET[ self::ARG ] ) );
	}

	/**
	 * The view's address, or '' while the dashboard page has none.
	 *
	 * @return string
	 */
	public static function url() {
		$page = WPCPM_Administrators_Dashboard::page_url();

		return '' === $page ? '' : add_query_arg( self::ARG, self::VIEW, $page );
	}

	/*
	 * --------------------------------------------------------------------
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The Upcoming cohorts card: the next six months in four figures and a small chart, and the way
	 * to the whole timeline.
	 *
	 * @param array $report What `WPCPM_Cohort_Timeline::report()` returned; empty draws nothing.
	 */
	public static function render_card( array $report ) {
		// Before the students sync has read any roster the twelve months are empty, which would read as
		// covered: no card until there is something to count (from the release's review, 1.123.69).
		if ( empty( $report['months'] ) || empty( $report['read'] ) ) {
			return;
		}

		$summary = $report['summary'];
		$starts  = (int) $summary['starting_confirmed'] + (int) $summary['starting_review'];
		$url     = self::url();
		$action  = '' === $url ? null : array(
			'label'   => __( 'Open the cohort timeline', 'wpcredits-program-manager' ),
			'href'    => $url,
			'context' => __( 'Upcoming cohorts', 'wpcredits-program-manager' ),
		);

		WPCPM_Dashboard_Card::open(
			array_merge(
				WPCPM_Administrators_Cards::SHELL,
				array(
					'key'    => self::CARD,
					'title'  => __( 'Upcoming cohorts', 'wpcredits-program-manager' ),
					'count'  => $starts,
					'open'   => WPCPM_Cohort_Timeline::STATE_SHORT === $summary['state'],
					'state'  => self::phrase( $summary ),
					'action' => $action,
				)
			)
		);

		self::render_stats( $summary, true );
		self::render_chart( $report, WPCPM_Cohort_Timeline::HORIZON, true );

		if ( null !== $action ) {
			printf(
				'<p class="wpcpm-administrator__more"><a href="%1$s">%2$s</a></p>',
				esc_url( $action['href'] ),
				esc_html( $action['label'] )
			);
		}

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The phrase on the card's row: the first shortage, else the first tight month, else that the
	 * mentors cover the next six months.
	 *
	 * @param array $summary The report's summary.
	 * @return string
	 */
	public static function phrase( array $summary ) {
		if ( '' !== $summary['first_short'] ) {
			return sprintf(
				/* translators: 1: how many more mentors, 2: a month and year, as "March 2027". */
				_n( '%1$s more mentor needed by %2$s', '%1$s more mentors needed by %2$s', max( 1, (int) $summary['recruit_confirmed'] ), 'wpcredits-program-manager' ),
				number_format_i18n( max( 1, (int) $summary['recruit_confirmed'] ) ),
				self::month_long( $summary['first_short'] )
			);
		}

		if ( '' !== $summary['first_tight'] ) {
			return sprintf(
				/* translators: %s: a month and year, as "March 2027". */
				__( 'Tight from %s if the registrations in review are placed', 'wpcredits-program-manager' ),
				self::month_long( $summary['first_tight'] )
			);
		}

		return __( 'The mentors cover the next six months', 'wpcredits-program-manager' );
	}

	/*
	 * --------------------------------------------------------------------
	 * The view
	 * --------------------------------------------------------------------
	 */

	/**
	 * The whole timeline: the head, the figures, the outlook, the chart, the month table, the intakes and
	 * the notes on how the numbers are made.
	 *
	 * The head is the frame's, the one the Reference view opens with: the page's name, then the view's
	 * in the serif. It has no way back of its own (1.123.70): the menu beside it is the way back, and a
	 * link naming the Administrator Dashboard a third time was one name too many (the owner, 11 November
	 * 2026: "Duplicated").
	 *
	 * @param array  $report  What `WPCPM_Cohort_Timeline::report()` returned.
	 * @param string $eyebrow The page's name, as the dashboard prints it, or '' for none. Markup.
	 */
	public static function render_view( array $report, $eyebrow = '' ) {
		echo '<div class="wpcpm-timeline-view">';

		echo '<header class="wpcpm-head wpcpm-timeline-view__header"><div class="wpcpm-head__copy">';
		echo $eyebrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built, WPCPM_Administrators_Dashboard::page_name().
		printf( '<h2 class="wpcpm-head__title">%s</h2>', esc_html__( 'Cohort timeline', 'wpcredits-program-manager' ) );
		printf(
			'<p class="wpcpm-head__lede">%s</p>',
			esc_html__( 'Who is on the program each month for the next twelve, who is new, and whether the active mentors have the places for them.', 'wpcredits-program-manager' )
		);

		if ( ! empty( $report['read'] ) ) {
			printf(
				'<p class="wpcpm-head__facts">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: a date and time. */
						__( 'Read from the program records on %s.', 'wpcredits-program-manager' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $report['read'] )
					)
				)
			);
		}

		echo '</div></header>';

		if ( empty( $report['months'] ) || empty( $report['read'] ) ) {
			printf( '<p>%s</p>', esc_html__( 'Nothing to show yet: the students sync has not read the Students table.', 'wpcredits-program-manager' ) );
			echo '</div>';

			return;
		}

		$summary = $report['summary'];

		self::render_stats( $summary, false );
		self::render_outlook( $summary );

		echo '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-chart-title">';
		printf( '<h3 id="wpcpm-timeline-chart-title" class="wpcpm-timeline-view__heading">%s</h3>', esc_html__( 'Students on the program each month', 'wpcredits-program-manager' ) );
		self::render_chart( $report, count( $report['months'] ), false );
		echo '</section>';

		echo '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-table-title">';
		printf( '<h3 id="wpcpm-timeline-table-title" class="wpcpm-timeline-view__heading">%s</h3>', esc_html__( 'Month by month', 'wpcredits-program-manager' ) );
		self::render_table( $report );
		echo '</section>';

		echo '<section class="wpcpm-timeline-view__section" aria-labelledby="wpcpm-timeline-intakes-title">';
		printf( '<h3 id="wpcpm-timeline-intakes-title" class="wpcpm-timeline-view__heading">%s</h3>', esc_html__( 'Intakes', 'wpcredits-program-manager' ) );
		self::render_intakes( $report );
		echo '</section>';

		self::render_notes( $summary );

		echo '</div>';
	}

	/**
	 * The four figures: students starting in the next six months, active mentors, places, mentors to
	 * recruit for the peak.
	 *
	 * @param array $summary The report's summary.
	 * @param bool  $compact Whether this is the card's row.
	 */
	private static function render_stats( array $summary, $compact ) {
		$starts  = (int) $summary['starting_confirmed'] + (int) $summary['starting_review'];
		$recruit = (int) $summary['recruit'];
		$stats   = array(
			array(
				'label' => __( 'Starting in the next 6 months', 'wpcredits-program-manager' ),
				'value' => $starts,
				'note'  => sprintf(
					/* translators: 1: confirmed students, 2: registrations in review. */
					__( '%1$s confirmed, %2$s in review', 'wpcredits-program-manager' ),
					number_format_i18n( (int) $summary['starting_confirmed'] ),
					number_format_i18n( (int) $summary['starting_review'] )
				),
				'tone'  => '',
			),
			array(
				'label' => __( 'Active mentors', 'wpcredits-program-manager' ),
				'value' => (int) $summary['mentors'],
				'note'  => sprintf(
					/* translators: %s: students per mentor. */
					_n( '%s student each', '%s students each', (int) $summary['ratio'], 'wpcredits-program-manager' ),
					number_format_i18n( (int) $summary['ratio'] )
				),
				'tone'  => '',
			),
			array(
				'label' => __( 'Mentor places', 'wpcredits-program-manager' ),
				'value' => (int) $summary['places'],
				'note'  => '' === $summary['peak_month'] ? __( 'No students in the next 6 months', 'wpcredits-program-manager' ) : sprintf(
					/* translators: 1: students, 2: a month and year. */
					__( 'Peak: %1$s students in %2$s', 'wpcredits-program-manager' ),
					number_format_i18n( (int) $summary['peak_load'] ),
					self::month_long( $summary['peak_month'] )
				),
				'tone'  => '',
			),
			array(
				'label' => __( 'Mentors to recruit', 'wpcredits-program-manager' ),
				'value' => $recruit,
				'note'  => 0 === $recruit
					? __( 'Enough for the peak', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: how many mentors cover the peak. */
						_n( '%s mentor covers the peak', '%s mentors cover the peak', (int) $summary['needed'], 'wpcredits-program-manager' ),
						number_format_i18n( (int) $summary['needed'] )
					),
				'tone'  => 0 === $recruit ? 'ok' : ( (int) $summary['recruit_confirmed'] > 0 ? 'short' : 'tight' ),
			),
		);

		$tags = array(
			'ok'    => __( 'Covered', 'wpcredits-program-manager' ),
			'tight' => __( 'Tight', 'wpcredits-program-manager' ),
			'short' => __( 'Short', 'wpcredits-program-manager' ),
		);

		printf( '<ul class="wpcpm-timeline-stats%s">', $compact ? ' wpcpm-timeline-stats--compact' : '' );

		foreach ( $stats as $stat ) {
			printf(
				'<li class="wpcpm-timeline-stats__item%1$s"><span class="wpcpm-timeline-stats__label">%2$s</span><span class="wpcpm-timeline-stats__value">%3$s%5$s</span><span class="wpcpm-timeline-stats__note">%4$s</span></li>',
				'' === $stat['tone'] ? '' : ' wpcpm-timeline-stats__item--' . esc_attr( $stat['tone'] ),
				esc_html( $stat['label'] ),
				esc_html( number_format_i18n( (int) $stat['value'] ) ),
				esc_html( $stat['note'] ),
				// The state in words, the homepage's status tag beside the figure (1.123.69).
				isset( $tags[ $stat['tone'] ] ) ? sprintf( '<span class="wpcpm-timeline-stats__tag wpcpm-timeline-stats__tag--%1$s">%2$s</span>', esc_attr( $stat['tone'] ), esc_html( $tags[ $stat['tone'] ] ) ) : ''
			);
		}

		echo '</ul>';
	}

	/**
	 * One sentence on the next six months, in the state's color with its icon and its word.
	 *
	 * @param array $summary The report's summary.
	 */
	private static function render_outlook( array $summary ) {
		$state = (string) $summary['state'];

		if ( WPCPM_Cohort_Timeline::STATE_SHORT === $state ) {
			$text = sprintf(
				/* translators: 1: a month and year, 2: how many more mentors for the confirmed students, 3: how many with the registrations in review. */
				_n( 'From %1$s the confirmed students alone need more places than the mentors have. Recruit %2$s more mentor for them, %3$s if the registrations in review are placed too.', 'From %1$s the confirmed students alone need more places than the mentors have. Recruit %2$s more mentors for them, %3$s if the registrations in review are placed too.', max( 1, (int) $summary['recruit_confirmed'] ), 'wpcredits-program-manager' ),
				self::month_long( $summary['first_short'] ),
				number_format_i18n( max( 1, (int) $summary['recruit_confirmed'] ) ),
				number_format_i18n( (int) $summary['recruit'] )
			);
		} elseif ( WPCPM_Cohort_Timeline::STATE_TIGHT === $state ) {
			$text = sprintf(
				/* translators: 1: a month and year, 2: how many more mentors. */
				_n( 'The confirmed students fit. From %1$s the registrations in review would need more places: %2$s more mentor covers them.', 'The confirmed students fit. From %1$s the registrations in review would need more places: %2$s more mentors cover them.', (int) $summary['recruit'], 'wpcredits-program-manager' ),
				self::month_long( $summary['first_tight'] ),
				number_format_i18n( (int) $summary['recruit'] )
			);
		} else {
			$text = __( 'The active mentors have places for every student in the next six months, the registrations in review included.', 'wpcredits-program-manager' );
		}

		printf(
			'<div class="wpcpm-timeline-outlook wpcpm-timeline-outlook--%1$s" role="status">%2$s<p><strong>%3$s</strong> %4$s</p></div>',
			esc_attr( $state ),
			self::icon( $state ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed inline SVG.
			esc_html( self::state_word( $state ) ),
			esc_html( $text )
		);
	}

	/**
	 * The chart: one stacked column per month against the places line, the half-year bands above.
	 *
	 * @param array $report  The report.
	 * @param int   $count   How many months to draw, from the first.
	 * @param bool  $compact The card's small chart: no axis, no bands, no starts and finishes.
	 */
	private static function render_chart( array $report, $count, $compact ) {
		$months = array_slice( $report['months'], 0, max( 1, (int) $count ) );
		$places = (int) $report['places'];
		$peak   = $places;

		foreach ( $months as $month ) {
			$peak = max( $peak, (int) $month['total'] );
		}

		$scale = self::scale( $peak );
		$top   = $scale['top'];
		$id    = $compact ? 'wpcpm-timeline-mini' : 'wpcpm-timeline-chart';

		printf(
			'<figure class="wpcpm-timeline%1$s" style="--wpcpm-timeline-months: %2$d;" aria-labelledby="%3$s-caption">',
			$compact ? ' wpcpm-timeline--compact' : '',
			count( $months ),
			esc_attr( $id )
		);

		printf(
			'<figcaption id="%1$s-caption" class="screen-reader-text">%2$s</figcaption>',
			esc_attr( $id ),
			esc_html(
				sprintf(
					/* translators: 1: how many months, 2: the places. */
					__( 'Students on the program for the next %1$s months against %2$s mentor places. The month table holds every number.', 'wpcredits-program-manager' ),
					number_format_i18n( count( $months ) ),
					number_format_i18n( $places )
				)
			)
		);

		self::render_legend( $places );

		// A tab stop and a named region, as every box that scrolls sideways on this site: a keyboard
		// reaches it and its arrow keys scroll it, and a screen reader says what scrolls.
		printf( '<div class="wpcpm-timeline__scroll" tabindex="0" role="region" aria-labelledby="%s-caption"><div class="wpcpm-timeline__plot">', esc_attr( $id ) );

		if ( ! $compact ) {
			echo '<div class="wpcpm-timeline__bands" aria-hidden="true">';

			$bands = array();
			$taken = 0;

			foreach ( $report['bands'] as $band ) {
				if ( $taken >= count( $months ) ) {
					break;
				}

				$span    = min( (int) $band['span'], count( $months ) - $taken );
				$taken  += $span;
				$bands[] = array(
					'label' => WPCPM_Cohort::label( $band['key'] ),
					'span'  => $span,
				);
			}

			foreach ( $bands as $i => $band ) {
				printf(
					'<span class="wpcpm-timeline__band wpcpm-timeline__band--%1$s" style="grid-column: span %2$d;">%3$s</span>',
					0 === $i % 2 ? 'odd' : 'even',
					(int) $band['span'],
					esc_html( $band['label'] )
				);
			}

			echo '</div>';
		}

		// The overlay is the bars' height exactly, so the gridlines and the places line sit on the
		// same scale as the segments, whatever the labels under the columns take.
		echo '<div class="wpcpm-timeline__area"><div class="wpcpm-timeline__overlay wpcpm-timeline__overlay--grid">';

		if ( ! $compact ) {
			echo '<div class="wpcpm-timeline__axis" aria-hidden="true">';

			foreach ( $scale['ticks'] as $tick ) {
				printf(
					'<span class="wpcpm-timeline__tick" style="bottom: %1$s%%;"><span>%2$s</span></span>',
					esc_attr( self::pct( $tick, $top ) ),
					esc_html( number_format_i18n( $tick ) )
				);
			}

			echo '</div>';
		}

		echo '</div><div class="wpcpm-timeline__overlay wpcpm-timeline__overlay--places">';

		printf(
			'<div class="wpcpm-timeline__places" style="bottom: %1$s%%;" aria-hidden="true"><span class="wpcpm-timeline__places-label">%2$s</span></div>',
			esc_attr( self::pct( $places, $top ) ),
			esc_html(
				sprintf(
					/* translators: %s: the mentor places. */
					_n( '%s place', '%s places', $places, 'wpcredits-program-manager' ),
					number_format_i18n( $places )
				)
			)
		);

		echo '</div><ol class="wpcpm-timeline__months">';

		foreach ( $months as $i => $month ) {
			self::render_column( $month, $top, $compact, $i, count( $months ) );
		}

		echo '</ol></div></div></div></figure>';
	}

	/**
	 * One month's column: the stack, the flag over a month that is short or tight, the month's
	 * name under it, and the tooltip.
	 *
	 * @param array $month   One month of the report.
	 * @param int   $top     The scale's top.
	 * @param bool  $compact The card's small chart.
	 * @param int   $index   The column's place, from 0.
	 * @param int   $count   How many columns.
	 */
	private static function render_column( array $month, $top, $compact, $index, $count ) {
		$state  = (string) $month['state'];
		$starts = (int) $month['starts']['confirmed'] + (int) $month['starts']['review'];
		$side   = $index >= $count - 3 ? ' wpcpm-timeline__month--end' : ( $index < 2 ? ' wpcpm-timeline__month--start' : '' );
		$name   = self::month_long( $month['key'] );
		$lines  = self::column_lines( $month );

		printf(
			'<li class="wpcpm-timeline__month wpcpm-timeline__month--%1$s%2$s" tabindex="0" aria-label="%3$s">',
			esc_attr( $state ),
			esc_attr( $side ),
			esc_attr( $name . ': ' . implode( ', ', $lines ) . '.' )
		);

		echo '<span class="wpcpm-timeline__bar" aria-hidden="true">';

		if ( WPCPM_Cohort_Timeline::STATE_OK !== $state ) {
			$over = WPCPM_Cohort_Timeline::STATE_SHORT === $state ? - (int) $month['balance_confirmed'] : - (int) $month['balance_total'];

			printf(
				'<span class="wpcpm-timeline__flag" style="bottom: %1$s%%;">%2$s<span>%3$s</span></span>',
				esc_attr( self::pct( (int) $month['total'], $top ) ),
				self::icon( $state, 12 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed inline SVG.
				esc_html( sprintf( /* translators: %s: students over the places. */ __( '+%s', 'wpcredits-program-manager' ), number_format_i18n( $over ) ) )
			);
		}

		echo '<span class="wpcpm-timeline__stack">';

		foreach ( WPCPM_Cohort_Timeline::KINDS as $kind ) {
			$n = (int) $month['load'][ $kind ];

			if ( $n < 1 ) {
				continue;
			}

			printf(
				'<span class="wpcpm-timeline__seg wpcpm-timeline__seg--%1$s" style="height: %2$s%%;"></span>',
				esc_attr( $kind ),
				esc_attr( self::pct( $n, $top ) )
			);
		}

		echo '</span></span>';

		printf(
			'<span class="wpcpm-timeline__label" aria-hidden="true"><span class="wpcpm-timeline__mon">%1$s</span>%2$s</span>',
			esc_html( self::month_short( $month['key'] ) ),
			( ! $compact && ( 0 === $index || '01' === substr( $month['key'], 5, 2 ) ) ) ? '<span class="wpcpm-timeline__year">' . esc_html( substr( $month['key'], 0, 4 ) ) . '</span>' : ''
		);

		if ( ! $compact ) {
			printf(
				'<span class="wpcpm-timeline__flow" aria-hidden="true"><span class="wpcpm-timeline__in%1$s">%2$s</span><span class="wpcpm-timeline__out%3$s">%4$s</span></span>',
				$starts > 0 ? '' : ' is-zero',
				esc_html( $starts > 0 ? '+' . number_format_i18n( $starts ) : "\xC2\xA0" ),
				(int) $month['ending'] > 0 ? '' : ' is-zero',
				esc_html( (int) $month['ending'] > 0 ? "\xE2\x88\x92" . number_format_i18n( (int) $month['ending'] ) : "\xC2\xA0" )
			);
		}

		echo '<span class="wpcpm-timeline__tip" aria-hidden="true">';
		printf( '<strong>%s</strong>', esc_html( $name ) );

		foreach ( $lines as $line ) {
			printf( '<span>%s</span>', esc_html( $line ) );
		}

		echo '</span></li>';
	}

	/**
	 * What a month's tooltip and accessible name say, line by line.
	 *
	 * @param array $month One month of the report.
	 * @return string[]
	 */
	private static function column_lines( array $month ) {
		$lines = array(
			sprintf(
				/* translators: %s: students. */
				_n( '%s student on the program', '%s students on the program', (int) $month['total'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $month['total'] )
			),
			sprintf(
				/* translators: 1: already in progress, 2: confirmed and new, 3: in review. */
				__( '%1$s continuing, %2$s confirmed new, %3$s in review', 'wpcredits-program-manager' ),
				number_format_i18n( (int) $month['load'][ WPCPM_Cohort_Timeline::KIND_CONTINUING ] ),
				number_format_i18n( (int) $month['load'][ WPCPM_Cohort_Timeline::KIND_STARTING ] ),
				number_format_i18n( (int) $month['load'][ WPCPM_Cohort_Timeline::KIND_REVIEW ] )
			),
			sprintf(
				/* translators: 1: starting, 2: finishing. */
				__( '%1$s starting, %2$s finishing', 'wpcredits-program-manager' ),
				number_format_i18n( (int) $month['starts']['confirmed'] + (int) $month['starts']['review'] ),
				number_format_i18n( (int) $month['ending'] )
			),
			sprintf(
				/* translators: 1: the places, 2: a balance word, as "2 free" or "3 short". */
				__( '%1$s places, %2$s', 'wpcredits-program-manager' ),
				number_format_i18n( (int) $month['places'] ),
				self::balance_words( (int) $month['balance_total'] )
			),
		);

		return $lines;
	}

	/**
	 * The legend: the three kinds and the places line.
	 *
	 * @param int $places The places.
	 */
	private static function render_legend( $places ) {
		$items = array(
			WPCPM_Cohort_Timeline::KIND_CONTINUING => __( 'Already on the program', 'wpcredits-program-manager' ),
			WPCPM_Cohort_Timeline::KIND_STARTING   => __( 'Confirmed, starting', 'wpcredits-program-manager' ),
			WPCPM_Cohort_Timeline::KIND_REVIEW     => __( 'Registration in review', 'wpcredits-program-manager' ),
		);

		echo '<ul class="wpcpm-timeline__legend">';

		foreach ( $items as $kind => $label ) {
			printf(
				'<li><span class="wpcpm-timeline__key wpcpm-timeline__seg--%1$s" aria-hidden="true"></span>%2$s</li>',
				esc_attr( $kind ),
				esc_html( $label )
			);
		}

		printf(
			'<li><span class="wpcpm-timeline__key wpcpm-timeline__key--places" aria-hidden="true"></span>%s</li>',
			esc_html(
				sprintf(
					/* translators: %s: the mentor places. */
					__( 'Mentor places (%s)', 'wpcredits-program-manager' ),
					number_format_i18n( (int) $places )
				)
			)
		);

		echo '</ul>';
	}

	/**
	 * The month table: every number the chart draws, and the state in a word and an icon.
	 *
	 * @param array $report The report.
	 */
	private static function render_table( array $report ) {
		echo '<div class="wpcpm-timeline-table" tabindex="0" role="region" aria-labelledby="wpcpm-timeline-table-title">';
		echo '<table><thead><tr>';

		$heads = array(
			__( 'Month', 'wpcredits-program-manager' ),
			__( 'Starting', 'wpcredits-program-manager' ),
			__( 'Finishing', 'wpcredits-program-manager' ),
			__( 'On the program', 'wpcredits-program-manager' ),
			__( 'Without a mentor', 'wpcredits-program-manager' ),
			__( 'Places', 'wpcredits-program-manager' ),
			__( 'Balance', 'wpcredits-program-manager' ),
			__( 'Outlook', 'wpcredits-program-manager' ),
		);

		foreach ( $heads as $head ) {
			printf( '<th scope="col">%s</th>', esc_html( $head ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $report['months'] as $month ) {
			$review = (int) $month['starts']['review'];
			$state  = (string) $month['state'];

			printf(
				'<tr><th scope="row">%1$s</th><td class="is-num">%2$s%3$s</td><td class="is-num">%4$s</td><td class="is-num">%5$s</td><td class="is-num">%6$s</td><td class="is-num">%7$s</td><td class="is-num wpcpm-timeline-table__balance--%8$s">%9$s</td><td><span class="wpcpm-timeline-chip wpcpm-timeline-chip--%8$s">%10$s%11$s</span></td></tr>',
				esc_html( self::month_long( $month['key'] ) ),
				esc_html( number_format_i18n( (int) $month['starts']['confirmed'] ) ),
				$review > 0 ? ' <span class="wpcpm-timeline-table__more">' . esc_html( sprintf( /* translators: %s: registrations in review. */ __( '+%s in review', 'wpcredits-program-manager' ), number_format_i18n( $review ) ) ) . '</span>' : '',
				esc_html( number_format_i18n( (int) $month['ending'] ) ),
				esc_html( number_format_i18n( (int) $month['total'] ) ),
				esc_html( number_format_i18n( (int) $month['unassigned'] ) ),
				esc_html( number_format_i18n( (int) $month['places'] ) ),
				esc_attr( $state ),
				esc_html( self::balance_words( (int) $month['balance_total'] ) ),
				self::icon( $state, 14 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed inline SVG.
				esc_html( self::state_word( $state ) )
			);
		}

		echo '</tbody></table></div>';
	}

	/**
	 * The intakes: each month with students starting, how many, from which institutions, on which
	 * tracks. A month with none is not listed.
	 *
	 * @param array $report The report.
	 */
	private static function render_intakes( array $report ) {
		$tracks = WPCPM_Administrators_Cards::tracks();
		$any    = false;

		echo '<ol class="wpcpm-timeline-intakes">';

		foreach ( $report['months'] as $month ) {
			$confirmed = (int) $month['starts']['confirmed'];
			$review    = (int) $month['starts']['review'];

			if ( $confirmed + $review < 1 ) {
				continue;
			}

			$any = true;

			echo '<li class="wpcpm-timeline-intakes__item">';
			printf(
				'<p class="wpcpm-timeline-intakes__when"><span class="wpcpm-timeline-intakes__month">%1$s</span><span class="wpcpm-timeline-intakes__n">%2$s</span></p>',
				esc_html( self::month_long( $month['key'] ) ),
				esc_html(
					sprintf(
						/* translators: %s: students starting. */
						_n( '%s starting', '%s starting', $confirmed + $review, 'wpcredits-program-manager' ),
						number_format_i18n( $confirmed + $review )
					)
				)
			);

			// The split as a thin bar, the numbers beside it in words.
			printf(
				'<p class="wpcpm-timeline-intakes__split"><span class="wpcpm-timeline-intakes__bar" aria-hidden="true"><span class="wpcpm-timeline__seg--starting" style="width: %1$s%%;"></span><span class="wpcpm-timeline__seg--review" style="width: %2$s%%;"></span></span><span>%3$s</span></p>',
				esc_attr( self::pct( $confirmed, $confirmed + $review ) ),
				esc_attr( self::pct( $review, $confirmed + $review ) ),
				esc_html(
					sprintf(
						/* translators: 1: confirmed, 2: in review. */
						__( '%1$s confirmed, %2$s in review', 'wpcredits-program-manager' ),
						number_format_i18n( $confirmed ),
						number_format_i18n( $review )
					)
				)
			);

			self::render_chips( __( 'Institutions', 'wpcredits-program-manager' ), $month['institutions'], __( 'No institution named', 'wpcredits-program-manager' ), array() );
			self::render_chips( __( 'Tracks', 'wpcredits-program-manager' ), $month['tracks'], __( 'Track not assigned yet', 'wpcredits-program-manager' ), $tracks );

			echo '</li>';
		}

		echo '</ol>';

		if ( ! $any ) {
			printf( '<p class="wpcpm-administrator__quiet">%s</p>', esc_html__( 'No students start in the next twelve months yet.', 'wpcredits-program-manager' ) );
		}
	}

	/**
	 * One labelled list of name and count chips.
	 *
	 * @param string $label  The list's label.
	 * @param array  $counts Name => count, largest first.
	 * @param string $unnamed What an empty name is called.
	 * @param array  $names  Key => display name, for keys that are not names themselves.
	 */
	private static function render_chips( $label, array $counts, $unnamed, array $names ) {
		if ( empty( $counts ) ) {
			return;
		}

		printf( '<div class="wpcpm-timeline-intakes__group"><span class="wpcpm-timeline-intakes__label">%s</span><ul class="wpcpm-timeline-intakes__chips">', esc_html( $label ) );

		foreach ( $counts as $key => $n ) {
			$key  = (string) $key;
			$name = '' === $key ? $unnamed : ( isset( $names[ $key ] ) ? (string) $names[ $key ] : $key );

			printf(
				'<li class="wpcpm-timeline-intakes__chip%1$s">%2$s <span>%3$s</span></li>',
				'' === $key ? ' is-unknown' : '',
				esc_html( $name ),
				esc_html( number_format_i18n( (int) $n ) )
			);
		}

		echo '</ul></div>';
	}

	/**
	 * How the numbers are made, in a few lines, with the way to the setting.
	 *
	 * @param array $summary The report's summary.
	 */
	private static function render_notes( array $summary ) {
		echo '<aside class="wpcpm-timeline-notes"><h3 class="wpcpm-timeline-view__heading">' . esc_html__( 'How these numbers are made', 'wpcredits-program-manager' ) . '</h3><ul>';

		printf(
			'<li>%1$s <a href="%2$s">%3$s</a></li>',
			esc_html(
				sprintf(
					/* translators: 1: active mentors, 2: students per mentor, 3: the places. */
					__( 'Places are the %1$s active mentors times %2$s students each: %3$s. The site cannot know who will join or leave, so the places are the same every month.', 'wpcredits-program-manager' ),
					number_format_i18n( (int) $summary['mentors'] ),
					number_format_i18n( (int) $summary['ratio'] ),
					number_format_i18n( (int) $summary['places'] )
				)
			),
			esc_url( admin_url( 'admin.php?page=wpcpm-settings&tab=people#cohort-planning' ) ),
			esc_html__( 'Change students per mentor', 'wpcredits-program-manager' )
		);

		printf( '<li>%s</li>', esc_html__( 'Registrations in review are the students the program has not placed yet: Students rows with no status, and registrations the site has not written to Airtable. Interested leads are not counted.', 'wpcredits-program-manager' ) );

		if ( (int) $summary['estimated'] > 0 ) {
			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: 1: students, 2: months. */
						_n( '%1$s student has no end date and is counted for %2$s months from the start.', '%1$s students have no end date and are counted for %2$s months from the start.', (int) $summary['estimated'], 'wpcredits-program-manager' ),
						number_format_i18n( (int) $summary['estimated'] ),
						number_format_i18n( WPCPM_Cohort_Timeline::DEFAULT_MONTHS )
					)
				)
			);
		}

		if ( (int) $summary['overdue'] > 0 ) {
			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: %s: students. */
						_n( '%s student is still in progress after the end date and is counted this month.', '%s students are still in progress after the end date and are counted this month.', (int) $summary['overdue'], 'wpcredits-program-manager' ),
						number_format_i18n( (int) $summary['overdue'] )
					)
				)
			);
		}

		printf( '<li>%s</li>', esc_html__( 'Test institutions are left out of every number.', 'wpcredits-program-manager' ) );

		echo '</ul></aside>';
	}

	/*
	 * --------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * A clean scale for a peak: the top and three or four ticks on round numbers.
	 *
	 * @param int $peak The highest value drawn.
	 * @return array{top: int, ticks: int[]}
	 */
	public static function scale( $peak ) {
		$peak = max( 1, (int) $peak );
		$step = 1;

		foreach ( array( 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000 ) as $candidate ) {
			$step = $candidate;

			if ( $peak / $candidate <= 4 ) {
				break;
			}
		}

		$top   = (int) ( ceil( $peak / $step ) * $step );
		$top   = $top === $peak ? $top + $step : $top;
		$ticks = array();

		for ( $tick = 0; $tick <= $top; $tick += $step ) {
			$ticks[] = $tick;
		}

		return array(
			'top'   => $top,
			'ticks' => $ticks,
		);
	}

	/**
	 * A share as a percentage for a style, with a dot whatever the locale.
	 *
	 * @param int $value The part.
	 * @param int $whole The whole.
	 * @return string
	 */
	private static function pct( $value, $whole ) {
		if ( (int) $whole < 1 ) {
			return '0';
		}

		return rtrim( rtrim( sprintf( '%.2F', min( 100, max( 0, 100 * (int) $value / (int) $whole ) ) ), '0' ), '.' );
	}

	/**
	 * A balance in words: free places, exactly full, or short.
	 *
	 * @param int $balance Places minus students.
	 * @return string
	 */
	private static function balance_words( $balance ) {
		if ( 0 === (int) $balance ) {
			return __( 'full', 'wpcredits-program-manager' );
		}

		if ( $balance > 0 ) {
			/* translators: %s: free places. */
			return sprintf( __( '%s free', 'wpcredits-program-manager' ), number_format_i18n( (int) $balance ) );
		}

		/* translators: %s: places short. */
		return sprintf( __( '%s short', 'wpcredits-program-manager' ), number_format_i18n( - (int) $balance ) );
	}

	/**
	 * A state in one word.
	 *
	 * @param string $state A STATE_* value.
	 * @return string
	 */
	private static function state_word( $state ) {
		if ( WPCPM_Cohort_Timeline::STATE_SHORT === $state ) {
			return __( 'Short', 'wpcredits-program-manager' );
		}

		if ( WPCPM_Cohort_Timeline::STATE_TIGHT === $state ) {
			return __( 'Tight', 'wpcredits-program-manager' );
		}

		return __( 'Covered', 'wpcredits-program-manager' );
	}

	/**
	 * The state's icon: a check, a clock for tight, an exclamation for short. Decorative: the word
	 * beside it always says the state.
	 *
	 * @param string $state A STATE_* value.
	 * @param int    $size  Pixels.
	 * @return string Fixed markup.
	 */
	private static function icon( $state, $size = 20 ) {
		$paths = array(
			WPCPM_Cohort_Timeline::STATE_OK    => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.7 2.7L16.5 9.5"/>',
			WPCPM_Cohort_Timeline::STATE_TIGHT => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
			WPCPM_Cohort_Timeline::STATE_SHORT => '<path d="M12 3.5 21.5 20h-19z"/><path d="M12 10v4.5M12 17v.5"/>',
		);
		$path  = isset( $paths[ $state ] ) ? $paths[ $state ] : $paths[ WPCPM_Cohort_Timeline::STATE_OK ];

		return sprintf(
			'<svg class="wpcpm-timeline-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$path
		);
	}

	/**
	 * A month key as "Mar", in the site's language.
	 *
	 * @param string $key `Y-m`.
	 * @return string
	 */
	private static function month_short( $key ) {
		return (string) wp_date( 'M', self::mid_month( $key ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A month key as "March 2027", in the site's language.
	 *
	 * @param string $key `Y-m`.
	 * @return string
	 */
	public static function month_long( $key ) {
		return (string) wp_date( 'F Y', self::mid_month( $key ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Noon on the 15th of a month, UTC: a moment no timezone moves into another month.
	 *
	 * @param string $key `Y-m`.
	 * @return int
	 */
	private static function mid_month( $key ) {
		return (int) gmmktime( 12, 0, 0, (int) substr( (string) $key, 5, 2 ), 15, (int) substr( (string) $key, 0, 4 ) );
	}
}
