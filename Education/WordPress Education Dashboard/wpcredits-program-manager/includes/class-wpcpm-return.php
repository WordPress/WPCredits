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
	/** The ids the dashboard's sections carry, minus the `wpcpm-` prefix. */
	const ANCHORS = array( 'attention', 'applications', 'agreements', 'reports', 'requests', 'programs', 'health' );

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
}
