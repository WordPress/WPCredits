<?php
/**
 * The Institution Dashboard's addresses, as WPCPM_Institutions_Dashboard builds them (Task 9 of the dashboard frame:
 * every way back opens the view that holds its card), for the suites that stand in their own dashboard class:
 * `view_url()`, `view_of()`, `card_url()`, `requested_record()` and `way_back()`. The view at `?wpcpm_view=` (the
 * Overview's with none, a view the page does not have left out), then the extra arguments, encoded, then an
 * Administrator's school when it reads as a record ID, then the card's anchor. bin/test-institution-ways-back.php
 * checks the real class against these for every card, both readers, an invalid record, an item and arguments.
 *
 * @package WPCreditsProgramManager
 */

if ( ! function_exists( 'wpcpm_test_institution_view_url' ) ) {
	/** The cards each view draws, as `WPCPM_Institutions_Dashboard::VIEW_CARDS` lists them. */
	function wpcpm_test_institution_views() {
		return array(
			'students'       => array( 'roster', 'student-status', 'student-form', 'student-invite' ),
			'enroll'         => array( 'import' ),
			'reports'        => array( 'report' ),
			'institution'    => array( 'details', 'people', 'tutors' ),
			'agreement'      => array( 'agreement' ),
			'events'         => array( 'sevents' ),
			'administrators' => array( 'for-administrators', 'admin-answers', 'admin-history' ),
		);
	}

	/**
	 * A view's address.
	 *
	 * @param string $page   The page's address, or ''.
	 * @param string $view   A view key.
	 * @param string $record The school's record ID, or ''.
	 * @param array  $args   More arguments, unencoded.
	 * @return string
	 */
	function wpcpm_test_institution_view_url( $page, $view = 'overview', $record = '', array $args = array() ) {
		if ( '' === (string) $page ) {
			return '';
		}
		$query = array();
		if ( 'overview' !== $view && array_key_exists( $view, wpcpm_test_institution_views() ) ) {
			$query['wpcpm_view'] = $view;
		}
		foreach ( $args as $k => $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$query[ rawurlencode( (string) $k ) ] = rawurlencode( (string) $v );
			}
		}
		$record = trim( (string) $record );
		if ( 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/D', $record ) ) {
			$query['wpcpm_institution_view'] = $record;
		}
		$pairs = array();
		foreach ( $query as $k => $v ) {
			$pairs[] = $k . '=' . $v;
		}
		return array() === $pairs ? (string) $page : $page . ( false === strpos( (string) $page, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
	}

	/**
	 * The view that draws a card, the Overview for a card no view lists.
	 *
	 * @param string $card The card's anchor without `wpcpm-`.
	 * @return string
	 */
	function wpcpm_test_institution_view_of( $card ) {
		foreach ( wpcpm_test_institution_views() as $view => $cards ) {
			if ( in_array( (string) $card, $cards, true ) ) {
				return $view;
			}
		}
		return 'overview';
	}

	/**
	 * A card's address, at its anchor or at an id inside it.
	 *
	 * @param string $page   The page's address, or ''.
	 * @param string $card   The card's anchor without `wpcpm-`.
	 * @param string $record The school's record ID, or ''.
	 * @param string $item   An anchor id, or ''.
	 * @param array  $args   More arguments, unencoded.
	 * @return string
	 */
	function wpcpm_test_institution_card_url( $page, $card, $record = '', $item = '', array $args = array() ) {
		$url = wpcpm_test_institution_view_url( $page, wpcpm_test_institution_view_of( $card ), $record, $args );
		if ( '' === $url ) {
			return '';
		}
		$item = (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $item );
		return $url . '#' . ( '' !== $item ? $item : 'wpcpm-' . preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $card ) ) );
	}

	/**
	 * The school an Administrator's request names: the query first, then the form, a record ID the index holds
	 * (asked of the suite's `WPCPM_Institutions_Index::has()` when it has one), for `CAP_MANAGE` alone.
	 *
	 * @return string
	 */
	function wpcpm_test_institution_requested_record() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return '';
		}
		$get   = isset( $_GET['wpcpm_institution_view'] ) && is_scalar( $_GET['wpcpm_institution_view'] ) ? trim( (string) $_GET['wpcpm_institution_view'] ) : '';
		$asked = '' !== $get ? $get : ( isset( $_POST['wpcpm_institution_view'] ) && is_scalar( $_POST['wpcpm_institution_view'] ) ? trim( (string) $_POST['wpcpm_institution_view'] ) : '' );
		if ( 1 !== preg_match( '/^rec[A-Za-z0-9]{14}$/D', $asked ) ) {
			return '';
		}
		return ! class_exists( 'WPCPM_Institutions_Index' ) || WPCPM_Institutions_Index::has( $asked ) ? $asked : '';
	}

	/**
	 * The address of the view that holds a card, with the school an Administrator's request names, no anchor.
	 *
	 * @param string $page The page's address, or ''.
	 * @param string $card The card's anchor without `wpcpm-`.
	 * @return string
	 */
	function wpcpm_test_institution_way_back( $page, $card ) {
		return wpcpm_test_institution_view_url( $page, wpcpm_test_institution_view_of( $card ), wpcpm_test_institution_requested_record() );
	}
}
