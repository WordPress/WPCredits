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
		echo '<p class="wpcpm-student__note">' . esc_html__( 'How many people claimed from each offer, by month. Nobody is named here and no name is kept for you: the program keeps the list of who claimed, for support.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $stats['offers'] ) ) {
			echo '<p>' . esc_html__( 'Numbers appear here once you have an offer.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div></details></section>';
			return;
		}

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

		foreach ( $stats['offers'] as $offer ) {
			$series = array();

			foreach ( $offer['series'] as $month => $n ) {
				$series[] = $month . ': ' . number_format_i18n( (int) $n );
			}

			/* translators: 1: offer title, 2: the twelve months and their counts. */
			printf( '<p class="wpcpm-usage__series wpcpm-student__note">%s</p>', esc_html( sprintf( __( '%1$s, last twelve months: %2$s', 'wpcredits-program-manager' ), $offer['title'], implode( ', ', $series ) ) ) );
		}

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
