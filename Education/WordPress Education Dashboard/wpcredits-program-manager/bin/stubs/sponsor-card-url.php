<?php
/**
 * A Sponsor Dashboard card's address, as WPCPM_Sponsors_Dashboard::card_url() makes it (1.123.78), for the
 * suites that stand in their own dashboard class: the card's view at `?wpcpm_view=`, the Overview's with none,
 * then the sponsor's key when one is given, then the card's anchor. bin/test-sponsors-dashboard.php checks the
 * real one against the same addresses.
 *
 * @package WPCreditsProgramManager
 */

if ( ! function_exists( 'wpcpm_test_sponsor_card_url' ) ) {
	/**
	 * The address.
	 *
	 * @param string $page   The page's address, or ''.
	 * @param string $card   The card's anchor key.
	 * @param string $record The sponsor's key, or ''.
	 * @param string $item   An anchor inside the card, or ''.
	 * @return string
	 */
	function wpcpm_test_sponsor_card_url( $page, $card, $record = '', $item = '' ) {
		if ( '' === (string) $page ) {
			return '';
		}

		$views = array(
			'sponsorship' => array( 'sponsorship', 'events', 'reports', 'badge' ),
			'offers'      => array( 'offers', 'posts' ),
			'reached'     => array( 'usage' ),
			'mentors'     => array( 'mentors', 'looking' ),
			'company'     => array( 'profile', 'people' ),
			'agreement'   => array( 'agreement' ),
		);
		$args  = array();

		foreach ( $views as $view => $cards ) {
			if ( in_array( (string) $card, $cards, true ) ) {
				$args[] = 'wpcpm_view=' . $view;
			}
		}

		if ( '' !== (string) $record ) {
			$args[] = 'wpcpm_sponsor_view=' . $record;
		}

		$query = array() === $args ? '' : ( false === strpos( (string) $page, '?' ) ? '?' : '&' ) . implode( '&', $args );

		return $page . $query . '#' . ( '' !== (string) $item ? (string) $item : 'wpcpm-sponsor-' . $card );
	}
}
