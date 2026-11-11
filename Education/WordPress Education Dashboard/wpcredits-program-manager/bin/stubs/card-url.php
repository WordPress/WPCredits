<?php
/**
 * A card's address, as WPCPM_Administrators_Dashboard::card_url() makes it (1.123.71), for the suites that
 * stand in their own dashboard class: Watch's cards at `?wpcpm_view=watch`, The program's at
 * `?wpcpm_view=reference`, every other on the Overview. bin/test-administrators-dashboard.php checks the
 * real one against the same addresses.
 *
 * @package WPCreditsProgramManager
 */

if ( ! function_exists( 'wpcpm_test_card_url' ) ) {
	/**
	 * The address.
	 *
	 * @param string $page The page's address, or ''.
	 * @param string $card The card's anchor key.
	 * @param string $item An anchor inside the card, or ''.
	 * @return string
	 */
	function wpcpm_test_card_url( $page, $card, $item = '' ) {
		if ( '' === (string) $page ) {
			return '';
		}

		$views = array(
			'watch'     => array( 'cohort-timeline', 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health', 'quiet-students' ),
			'reference' => array( 'sponsors', 'programs', 'mentor-engagement' ),
		);
		$query = '';

		foreach ( $views as $view => $cards ) {
			if ( in_array( (string) $card, $cards, true ) ) {
				$query = ( false === strpos( (string) $page, '?' ) ? '?' : '&' ) . 'wpcpm_view=' . $view;
			}
		}

		return $page . $query . '#' . ( '' !== (string) $item ? (string) $item : 'wpcpm-' . $card );
	}
}
