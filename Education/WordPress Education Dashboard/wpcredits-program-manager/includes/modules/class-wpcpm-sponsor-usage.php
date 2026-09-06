<?php
/**
 * The Sponsor Dashboard's Usage card: counts over time and offer, and a CSV of the same.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sponsors design spec of 4 September 2026, section 6.4, and decision 7: sponsors read
 * numbers, managers read names. Everything here comes from WPCPM_Sponsor_Claims::stats(),
 * which bin/test-sponsor-offers.php walks to prove it carries no name and no address; the card
 * adds nothing to it but a table and a form.
 */
final class WPCPM_Sponsor_Usage {

	const CARD          = 'usage';
	const ACTION_EXPORT = 'wpcpm_offer_stats_export';

	/**
	 * The handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * The export never flashes on success: it is the download. Its one refusal is the shared one.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array();
	}

	/**
	 * The card.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$stats = WPCPM_Sponsor_Claims::stats( $record );
		$open  = isset( $context['open'] ) && self::CARD === $context['open'];

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Usage', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( (int) $stats['totals']['total'] ) )
		);
		echo '<div class="wpcpm-group__body">';
		// A privacy fact, not a description: the group lead says what the card shows; this says
		// what it never will (spec decision 7).
		echo '<p class="wpcpm-student__note">' . esc_html__( 'Nobody is named here: the program keeps the list of who claimed, for support.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $stats['offers'] ) ) {
			echo '<p>' . esc_html__( 'Numbers appear here once you have an offer.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div></details></section>';
			return;
		}

		self::render_chart( $stats );

		echo '<table class="wpcpm-table wpcpm-usage"><thead><tr>';

		foreach ( array( __( 'Offer', 'wpcredits-program-manager' ), __( 'State', 'wpcredits-program-manager' ), __( 'Claims', 'wpcredits-program-manager' ), __( 'This month', 'wpcredits-program-manager' ), __( 'Available', 'wpcredits-program-manager' ), __( 'Claimed', 'wpcredits-program-manager' ), __( 'Void', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( $stats['offers'] as $offer ) {
			echo '<tr class="wpcpm-usage__offer">';
			printf( '<th scope="row">%s</th>', esc_html( $offer['title'] ) );
			printf( '<td>%s</td>', esc_html( WPCPM_Sponsor_Offers::state_label( $offer['state'] ) ) );

			foreach ( array( 'total', 'month', 'available', 'claimed', 'void' ) as $key ) {
				printf( '<td>%s</td>', esc_html( WPCPM_Sponsor_Offers::KIND_SHARED === $offer['kind'] && in_array( $key, array( 'available', 'claimed', 'void' ), true ) ? '' : number_format_i18n( (int) $offer[ $key ] ) ) );
			}

			echo '</tr>';
		}

		echo '</tbody><tfoot><tr class="wpcpm-usage__totals">';
		printf( '<th scope="row">%s</th><td></td>', esc_html__( 'All offers', 'wpcredits-program-manager' ) );

		foreach ( array( 'total', 'month', 'available', 'claimed', 'void' ) as $key ) {
			printf( '<td>%s</td>', esc_html( number_format_i18n( (int) $stats['totals'][ $key ] ) ) );
		}

		echo '</tr></tfoot></table>';

		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Preparing', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_EXPORT . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_EXPORT ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Download as CSV', 'wpcredits-program-manager' ) );
		echo '</form>';

		echo '</div></details></section>';
	}

	/**
	 * The claims of the last twelve months as a bar chart above the table (owner, 1.96.8): a
	 * group of bars per month, one bar per offer, laid out with boxes and percentages so the
	 * theme sizes and colors it and nothing here holds a pixel. The numbers stay readable
	 * without eyes: the plot's label carries the sentence the card printed under the table
	 * before, one per offer, and every bar names its offer, month and count. The scale column
	 * and the month row are aria-hidden: they repeat what that sentence says.
	 *
	 * @param array $stats WPCPM_Sponsor_Claims::stats()'s answer.
	 */
	public static function render_chart( array $stats ) {
		if ( empty( $stats['offers'] ) || empty( $stats['months'] ) ) {
			return;
		}

		$months = array_values( $stats['months'] );
		$peak   = 0;

		foreach ( $stats['offers'] as $offer ) {
			$peak = max( $peak, (int) max( $offer['series'] ) );
		}

		$scale     = self::scale( $peak );
		$sentences = array();
		$k         = 0;

		foreach ( $stats['offers'] as $offer ) {
			$series = array();

			foreach ( $offer['series'] as $month => $n ) {
				$series[] = $month . ': ' . number_format_i18n( (int) $n );
			}

			/* translators: 1: offer title, 2: the twelve months and their counts. */
			$sentences[] = sprintf( __( '%1$s, last twelve months: %2$s', 'wpcredits-program-manager' ), $offer['title'], implode( ', ', $series ) );
		}

		echo '<figure class="wpcpm-chart wpcpm-usage__chart">';
		printf(
			'<figcaption class="wpcpm-chart__title">%s</figcaption>',
			esc_html(
				sprintf(
					/* translators: 1: the first month, 2: the last month, both as "September 2026". */
					__( 'Claims by month, %1$s to %2$s', 'wpcredits-program-manager' ),
					self::month_label( $months[0], 'F Y' ),
					self::month_label( end( $months ), 'F Y' )
				)
			)
		);
		echo '<div class="wpcpm-chart__frame">';
		echo '<div class="wpcpm-chart__scale" aria-hidden="true">';

		for ( $tick = 0; $tick <= $scale['max']; $tick += $scale['step'] ) {
			printf( '<span class="wpcpm-chart__tick" style="bottom:%1$s%%">%2$s</span>', esc_attr( self::percent( $tick, $scale['max'] ) ), esc_html( number_format_i18n( $tick ) ) );
		}

		echo '</div>';
		printf( '<div class="wpcpm-chart__plot" role="img" aria-label="%s">', esc_attr( implode( '; ', $sentences ) ) );

		for ( $tick = $scale['step']; $tick <= $scale['max']; $tick += $scale['step'] ) {
			printf( '<span class="wpcpm-chart__grid" style="bottom:%s%%"></span>', esc_attr( self::percent( $tick, $scale['max'] ) ) );
		}

		foreach ( $months as $month ) {
			echo '<div class="wpcpm-chart__month">';
			$k = 0;

			foreach ( $stats['offers'] as $offer ) {
				++$k;
				$n = isset( $offer['series'][ $month ] ) ? (int) $offer['series'][ $month ] : 0;
				printf(
					'<span class="wpcpm-chart__bar wpcpm-chart__bar--%1$d" style="height:%2$s%%" title="%3$s"></span>',
					(int) self::series_index( $k ),
					esc_attr( self::percent( $n, $scale['max'] ) ),
					/* translators: 1: offer title, 2: the month as "September 2026", 3: the count. */
					esc_attr( sprintf( __( '%1$s, %2$s: %3$s', 'wpcredits-program-manager' ), $offer['title'], self::month_label( $month, 'F Y' ), number_format_i18n( $n ) ) )
				);
			}

			echo '</div>';
		}

		echo '</div>';
		echo '<ol class="wpcpm-chart__months" aria-hidden="true">';

		foreach ( $months as $month ) {
			printf( '<li>%s</li>', esc_html( self::month_label( $month, 'M' ) ) );
		}

		echo '</ol></div>';
		echo '<ul class="wpcpm-chart__legend" aria-hidden="true">';
		$k = 0;

		foreach ( $stats['offers'] as $offer ) {
			++$k;
			printf( '<li><span class="wpcpm-chart__swatch wpcpm-chart__swatch--%1$d"></span>%2$s</li>', (int) self::series_index( $k ), esc_html( $offer['title'] ) );
		}

		echo '</ul></figure>';
	}

	/**
	 * A scale the eye can read: the step is 1, 2 or 5 times a power of ten, the smallest that
	 * reaches the peak in at most four steps, and the top is the first step at or above the
	 * peak. A peak of 0 still draws a scale of one, so an empty year has a base line and a top.
	 *
	 * @param int $peak The highest count on the chart.
	 * @return array{max: int, step: int}
	 */
	public static function scale( $peak ) {
		$peak  = max( 1, (int) $peak );
		$steps = array( 1, 2, 5 );
		$i     = 0;
		$step  = 1;

		while ( $peak / $step > 4 ) {
			++$i;
			$step = $steps[ $i % 3 ] * (int) pow( 10, intdiv( $i, 3 ) );
		}

		return array(
			'max'  => (int) ceil( $peak / $step ) * $step,
			'step' => $step,
		);
	}

	/**
	 * A count as a percentage of the top of the scale, two decimals, no trailing zeros: the
	 * bar's height and the tick's place.
	 *
	 * @param int $n   The count.
	 * @param int $max The top of the scale, never 0.
	 * @return string
	 */
	private static function percent( $n, $max ) {
		$max = max( 1, (int) $max );

		return rtrim( rtrim( number_format( min( 100, max( 0, (int) $n / $max * 100 ) ), 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * The series colors cycle through six; the seventh offer wears the first color again.
	 *
	 * @param int $k The offer's position on the card, from 1.
	 * @return int 1 to 6.
	 */
	private static function series_index( $k ) {
		return ( ( max( 1, (int) $k ) - 1 ) % 6 ) + 1;
	}

	/**
	 * A stats() month key ("2026-09") in the site's language, on the 15th at noon UTC so no
	 * time zone moves it into a neighboring month.
	 *
	 * @param string $month  The key.
	 * @param string $format A date format: 'M' for the axis, 'F Y' for the caption and titles.
	 * @return string
	 */
	private static function month_label( $month, $format ) {
		$parts = explode( '-', (string) $month );

		if ( 2 !== count( $parts ) ) {
			return (string) $month;
		}

		return wp_date( $format, gmmktime( 12, 0, 0, (int) $parts[1], 15, (int) $parts[0] ) );
	}

	/**
	 * The same numbers as a file. The nonce, then the claim (ACT_VIEW_STATS), then the build:
	 * nothing is computed for an account the policy refuses.
	 */
	public static function handle_export() {
		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );
		check_admin_referer( self::ACTION_EXPORT . '_' . $record );

		$claim = WPCPM_Sponsor_Roster::claim( $record, WPCPM_Sponsor_Policy::ACT_VIEW_STATS );

		if ( is_wp_error( $claim ) ) {
			call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), 'refused', self::CARD, '' );
			exit;
		}

		$body = WPCPM_Sponsor_Claims::csv( WPCPM_Sponsor_Claims::stats( $claim['record'] ) );
		$name = is_array( $claim['row'] ) ? sanitize_title( (string) $claim['row']['name'] ) : '';

		self::send( $body, 'sponsor-usage-' . ( '' !== $name ? $name : 'sponsor' ) . '-' . wp_date( 'Y-m-d' ) . '.csv' );
	}

	/**
	 * Send the CSV to the browser as a download and end the request.
	 *
	 * @param string $body The CSV.
	 * @param string $name The file name.
	 */
	private static function send( $body, $name ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A CSV body built by WPCPM_Institution_Export::csv(), which neutralises every cell.
		exit;
	}
}
