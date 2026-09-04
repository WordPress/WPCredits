<?php
/**
 * The branded login screen.
 *
 * `wp-login.php` is not a template, so a block theme cannot reach it through
 * templates or patterns - the login hooks are the only way in. Everything here is
 * presentation, plus the one thing worth saying to someone arriving on this page:
 * these accounts were created by the program, so a first password comes from
 * "Lost your password?".
 *
 * Nothing changes how authentication works. The plugin already sends mentors to
 * "My Students" after login; this page only says so.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the login stylesheet.
 */
function wpcredits_login_styles() {
	wp_enqueue_style(
		'wpcredits-login',
		get_theme_file_uri( 'assets/css/login.css' ),
		array( 'login' ),
		WPCREDITS_VERSION
	);
}
add_action( 'login_enqueue_scripts', 'wpcredits_login_styles' );

/**
 * Point the login logo at the site rather than at wordpress.org.
 *
 * @return string
 */
function wpcredits_login_logo_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'wpcredits_login_logo_url' );

/**
 * And name the site in its link text.
 *
 * @return string
 */
function wpcredits_login_logo_text() {
	return get_bloginfo( 'name', 'display' );
}
add_filter( 'login_headertext', 'wpcredits_login_logo_text' );

/**
 * Whether this request is the login form itself.
 *
 * `wp-login.php` also serves password reset and registration. The masthead
 * belongs on all of them; telling someone resetting a password where mentors land
 * after logging in does not.
 *
 * @return bool
 */
function wpcredits_is_login_form() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check on wp-login.php's own query var.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

	return in_array( $action, array( 'login', '' ), true );
}

/**
 * The masthead and the form's heading.
 *
 * Prepended to the login message, which is the one hook that prints inside the
 * card above the form. The standard logo link stays where it is, so anyone
 * navigating by heading still finds it.
 *
 * @param string $message Whatever wp-login.php is about to print.
 * @return string
 */
function wpcredits_login_message( $message ) {
	$tagline = trim( (string) get_bloginfo( 'description', 'display' ) );

	$out  = '<div class="wpc-login__brand">';
	$out .= '<div class="wpc-login__mark">';
	$out .= wp_kses( wpcredits_get_logo( 38 ), wpcredits_svg_allowed_html() );
	$out .= sprintf(
		'<p class="wpc-login__name">%s</p>',
		wp_kses(
			wpcredits_accent_word( get_bloginfo( 'name', 'display' ) ),
			array(
				'em' => array(),
				'i'  => array(),
			)
		)
	);
	$out .= '</div>';

	if ( '' !== $tagline ) {
		$out .= sprintf( '<p class="wpc-login__tagline">%s</p>', esc_html( $tagline ) );
	}

	$out .= '</div>';

	if ( wpcredits_is_login_form() ) {
		$out .= '<div class="wpc-login__intro">';
		$out .= sprintf( '<h2 class="wpc-login__title">%s</h2>', esc_html__( 'Log in', 'wpcredits-theme' ) );
		$out .= '</div>';
	}

	$out .= $message;

	// Signing in while already signed in redraws the form, which reads as a failed
	// login. Say what happened and offer the page they were going to.
	$page = wpcredits_mentor_page_url();

	if ( is_user_logged_in() && wpcredits_is_login_form() && '' !== $page && wpcredits_viewer_is_mentor() ) {
		$out .= sprintf(
			'<p class="message wpc-login__signed-in">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'You are already logged in.', 'wpcredits-theme' ),
			esc_url( $page ),
			esc_html__( 'Open My Students', 'wpcredits-theme' )
		);
	}

	return $out;
}
add_filter( 'login_message', 'wpcredits_login_message' );

/**
 * How to get a first password, and the site's sign-off.
 *
 * Printed after the card. Most people arriving here have never set a password -
 * the program created their account - so saying so beside "Lost your password?"
 * saves a support message.
 */
function wpcredits_login_footer() {
	if ( wpcredits_is_login_form() ) {
		printf(
			'<p class="wpc-login__note">%s</p>',
			wp_kses(
				sprintf(
					/* translators: %s: the "Lost your password?" link text, wrapped for emphasis. */
					esc_html__( 'Your account was created by the program. If you have never set a password, use %s with the email address in your Airtable record.', 'wpcredits-theme' ),
					'<b>' . esc_html__( 'Lost your password?', 'wpcredits-theme' ) . '</b>'
				),
				array( 'b' => array() )
			)
		);
	}

	printf( '<p class="wpc-login__poetry">%s</p>', esc_html__( 'Code is poetry.', 'wpcredits-theme' ) );
}
add_action( 'login_footer', 'wpcredits_login_footer' );
