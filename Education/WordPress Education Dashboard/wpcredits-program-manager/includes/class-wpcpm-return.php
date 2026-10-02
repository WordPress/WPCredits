<?php
/**
 * Where a decision goes back to: its wp-admin screen, or the Administrator Dashboard.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A two-value allowlist for the page a handler returns to.
 *
 * The Administrator Dashboard posts the wp-admin queue's own decisions, and each of those
 * handlers used to redirect to its screen. A posted URL is never followed: `admin` and
 * `dashboard` are the two places, each mapped to a URL this class builds, and anything
 * else is the handler's default. That is the rule `WPCPM_Request::is_explicit_redirect()`
 * set for the login redirect, kept here for forms (design of 4 September 2026, decision 3).
 */
final class WPCPM_Return {
	/** The posted field naming the place. */
	const FIELD = 'wpcpm_return';
	/** The posted field naming the card to land on. */
	const ANCHOR_FIELD = 'wpcpm_return_to';
	/** The handler's own wp-admin screen, which is also what a missing value means. */
	const ADMIN = 'admin';
	/** The Administrator Dashboard. */
	const DASHBOARD = 'dashboard';
	/**
	 * The ids the dashboard's sections carry, minus the `wpcpm-` prefix.
	 *
	 * Every one of the Administrator Dashboard's cards, and the attention strip above them.
	 * `offers-low`, `interests` and `sponsors` were missing while no decision was posted from
	 * those three cards, which made `WPCPM_Administrators_Cards::card_open()`'s own contract
	 * ("the anchor, one of WPCPM_Return::ANCHORS") false for a quarter of its callers and left
	 * a trap for the first decision put on one of them: `field()` drops an anchor this list
	 * does not name, silently (deep check FADMN-6).
	 */
	const ANCHORS = array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'sponsor-applications', 'sponsor-posts', 'sponsor-agreements', 'offers-low', 'duplicates', 'interests', 'sponsors', 'programs', 'health' );

	/**
	 * Print the hidden fields that bring a decision back to the dashboard.
	 *
	 * Nothing is printed for the wp-admin default, so a form the queue draws on its own
	 * screen is byte for byte what it was.
	 *
	 * @param string $where  ADMIN or DASHBOARD.
	 * @param string $anchor One of ANCHORS, or '' for the top of the page.
	 */
	public static function field( $where, $anchor = '' ) {
		if ( self::DASHBOARD !== $where ) {
			return;
		}

		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( self::DASHBOARD ) );

		if ( in_array( (string) $anchor, self::ANCHORS, true ) ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ANCHOR_FIELD ), esc_attr( $anchor ) );
		}
	}

	/**
	 * The URL a handler redirects to after its decision.
	 *
	 * @param string $default The handler's own screen.
	 * @return string
	 */
	public static function url( $default ) {
		$default = (string) $default;

		if ( self::DASHBOARD !== WPCPM_Request::posted_key( self::FIELD ) ) {
			return $default;
		}

		// The page may not exist: the module has not been activated, or the page was
		// deleted. The default is the screen the handler belongs to, never the front page.
		if ( ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
			return $default;
		}

		$page = (string) WPCPM_Administrators_Dashboard::page_url();

		if ( '' === $page ) {
			return $default;
		}

		$anchor = WPCPM_Request::posted_key( self::ANCHOR_FIELD );

		return in_array( $anchor, self::ANCHORS, true ) ? $page . '#wpcpm-' . $anchor : $page;
	}

	/**
	 * Print "Open on the Administrator Dashboard": the way from a wp-admin screen that reads a queue
	 * to the place on the dashboard where it is decided.
	 *
	 * The address is the page, then the element id of one item of the card when the caller names
	 * one, so the reader lands on the item the screen was showing, else `#wpcpm-` and the card's id,
	 * the address a decision posted from that card comes back to (`url()`), so the way in and the
	 * way back land on the same card. One printer for every screen and block that links there,
	 * beside the card ids it reads; a block that ends in a line of its own markup, as the review
	 * blocks do, names the paragraph's class.
	 *
	 * Nothing is printed for a card the dashboard does not have, and nothing while its page is
	 * missing: there is nowhere to link, and the caller says so in its own place, in the words
	 * `WPCPM_Administrators_Dashboard::page_missing()` keeps for it.
	 *
	 * @param string $card      One of ANCHORS: the card that decides what the link is printed for.
	 * @param string $item      The element id of one item on that card, or '' for the card itself.
	 * @param string $css_class The paragraph's class, or '' for none.
	 */
	public static function render_dashboard_link( $card, $item = '', $css_class = '' ) {
		if ( ! in_array( (string) $card, self::ANCHORS, true ) || ! class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
			return;
		}

		$page = (string) WPCPM_Administrators_Dashboard::page_url();

		if ( '' === $page ) {
			return;
		}

		$css_class = sanitize_html_class( (string) $css_class );

		printf(
			'<p%1$s><a href="%2$s">%3$s</a></p>',
			'' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '',
			esc_url( $page . '#' . ( '' !== (string) $item ? (string) $item : 'wpcpm-' . $card ) ),
			esc_html__( 'Open on the Administrator Dashboard', 'wpcredits-program-manager' )
		);
	}
}
