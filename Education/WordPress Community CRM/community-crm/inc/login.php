<?php
/**
 * Sign-in for the WordPress Community CRM front page.
 *
 * The design's homepage doubles as the login screen, so the theme ships a
 * community-crm/login-form block (plus a [community_crm_login] shortcode) that
 * renders a real WordPress login form inside the hero card, and keeps failed
 * attempts on the homepage instead of bouncing to wp-login.php.
 *
 * wp-login.php itself is re-skinned to match, so both routes look the same.
 *
 * @package Community_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * URLs
 * ------------------------------------------------------------------------ */

if ( ! function_exists( 'ccrm_login_page_url' ) ) {
	/**
	 * The page that hosts the sign-in form. The front page by default.
	 *
	 * @return string
	 */
	function ccrm_login_page_url() {
		/**
		 * Filters the URL of the page hosting the sign-in form.
		 *
		 * @param string $url Login page URL.
		 */
		return (string) apply_filters( 'ccrm_login_page_url', home_url( '/' ) );
	}
}

if ( ! function_exists( 'ccrm_default_redirect_url' ) ) {
	/**
	 * Where to send people once they are signed in.
	 *
	 * Jetpack CRM (the CRM behind this front end) puts its dashboard at
	 * admin.php?page=zerobscrm-dash, so use that when the plugin is active and
	 * fall back to the WordPress dashboard otherwise.
	 *
	 * @return string
	 */
	function ccrm_default_redirect_url() {
		$url = defined( 'ZBS_ROOTFILE' )
			? admin_url( 'admin.php?page=zerobscrm-dash' )
			: admin_url();

		/**
		 * Filters the post-sign-in destination.
		 *
		 * @param string $url Redirect URL.
		 */
		return (string) apply_filters( 'ccrm_login_redirect_url', $url );
	}
}

if ( ! function_exists( 'ccrm_request_access_url' ) ) {
	/**
	 * Where "Request access" points. Defaults to the on-page panel anchor;
	 * point it at a form, a Slack channel or a Trac ticket as needed.
	 *
	 * @return string
	 */
	function ccrm_request_access_url() {
		/**
		 * Filters the "Request access" destination.
		 *
		 * @param string $url Request-access URL.
		 */
		return (string) apply_filters( 'ccrm_request_access_url', '#access' );
	}
}

if ( ! function_exists( 'ccrm_resolve_redirect_target' ) ) {
	/**
	 * Resolve the redirect_to value for the form, honouring a request that was
	 * bounced here from a protected screen but only ever to a local URL.
	 *
	 * @return string
	 */
	function ccrm_resolve_redirect_target() {
		$default = ccrm_default_redirect_url();

		if ( empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $default;
		}

		$requested = wp_unslash( $_GET['redirect_to'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// wp_validate_redirect() returns the fallback for any off-host target.
		return wp_validate_redirect( esc_url_raw( (string) $requested ), $default );
	}
}

/* ---------------------------------------------------------------------------
 * Keeping failed sign-ins on the homepage
 * ------------------------------------------------------------------------ */

if ( ! function_exists( 'ccrm_handle_login_failed' ) ) {
	/**
	 * Send failed attempts that came from our form back to the homepage with an
	 * error code, instead of letting core render wp-login.php.
	 *
	 * The hidden ccrm_login field identifies our form, so sign-ins from
	 * wp-login.php keep core's behaviour.
	 *
	 * @param string        $username Submitted username.
	 * @param WP_Error|null $error    Authentication error.
	 */
	function ccrm_handle_login_failed( $username, $error = null ) {
		unset( $username );

		if ( empty( $_POST['ccrm_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$code = 'failed';

		if ( is_wp_error( $error ) && $error->get_error_code() ) {
			// Only codes that say nothing about whether an account exists are
			// passed through. Core distinguishes invalid_username from
			// incorrect_password, and while ccrm_login_notice() renders both with
			// one generic message, putting the raw code in the URL would still
			// confirm the account — the redirect is as readable as the page.
			$safe_codes = array( 'empty_username', 'empty_password', 'empty_username_password', 'expired', 'denied' );
			$raw_code   = $error->get_error_code();

			if ( in_array( $raw_code, $safe_codes, true ) ) {
				$code = $raw_code;
			}
		}

		$args = array( 'login' => $code );

		if ( ! empty( $_POST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$target = wp_validate_redirect(
				esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				''
			);

			// Pass the raw URL: add_query_arg() encodes it, and encoding it
			// here too would round-trip a double-encoded value that
			// wp_validate_redirect() would then reject.
			if ( $target ) {
				$args['redirect_to'] = $target;
			}
		}

		$url = add_query_arg( $args, ccrm_login_page_url() ) . '#signin';

		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'wp_login_failed', 'ccrm_handle_login_failed', 10, 2 );

if ( ! function_exists( 'ccrm_maybe_redirect_wp_login' ) ) {
	/**
	 * Optionally send visitors of wp-login.php to the themed homepage.
	 *
	 * Off by default: leaving wp-login.php reachable is the safety net if the
	 * front page form is ever edited away. Enable with
	 * add_filter( 'ccrm_redirect_wp_login', '__return_true' );
	 */
	function ccrm_maybe_redirect_wp_login() {
		/** This filter is documented above. */
		if ( ! apply_filters( 'ccrm_redirect_wp_login', false ) ) {
			return;
		}

		// Only plain GET sign-in views; never POSTs, logout, password resets,
		// registration or interim (heartbeat) logins.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'login' !== $action || isset( $_GET['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$args = array();

		if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Raw, not pre-encoded — add_query_arg() does the encoding.
			$args['redirect_to'] = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		wp_safe_redirect( add_query_arg( $args, ccrm_login_page_url() ) );
		exit;
	}
}
add_action( 'login_init', 'ccrm_maybe_redirect_wp_login' );

/* ---------------------------------------------------------------------------
 * Notices
 * ------------------------------------------------------------------------ */

if ( ! function_exists( 'ccrm_login_notice' ) ) {
	/**
	 * Build the notice for the current request, if any.
	 *
	 * Authentication failures are reported generically so the form never
	 * confirms whether an account exists.
	 *
	 * @return array{type:string,message:string}|null
	 */
	function ccrm_login_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['loggedout'] ) ) {
			return array(
				'type'    => 'success',
				'message' => __( 'You are signed out.', 'community-crm' ),
			);
		}

		if ( empty( $_GET['login'] ) ) {
			return null;
		}

		$code = sanitize_key( wp_unslash( $_GET['login'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		switch ( $code ) {
			case 'empty_username':
			case 'empty_password':
			case 'empty_username_password':
				return array(
					'type'    => 'error',
					'message' => __( 'Enter both your username and your password.', 'community-crm' ),
				);

			case 'expired':
				return array(
					'type'    => 'info',
					'message' => __( 'Your session expired. Sign in again to continue.', 'community-crm' ),
				);

			case 'denied':
				return array(
					'type'    => 'error',
					'message' => __( 'Your account does not have access to the Community CRM yet. Request access below.', 'community-crm' ),
				);

			default:
				return array(
					'type'    => 'error',
					'message' => __( 'That username and password combination is not correct.', 'community-crm' ),
				);
		}
	}
}

if ( ! function_exists( 'ccrm_render_notice' ) ) {
	/**
	 * Render a notice block.
	 *
	 * @param array{type:string,message:string}|null $notice Notice data.
	 * @return string
	 */
	function ccrm_render_notice( $notice ) {
		if ( empty( $notice['message'] ) ) {
			return '';
		}

		$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'error', 'success', 'info' ), true )
			? $notice['type']
			: 'info';

		return sprintf(
			'<div class="ccrm-notice ccrm-notice--%1$s" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			esc_html( $notice['message'] )
		);
	}
}

/* ---------------------------------------------------------------------------
 * The sign-in panel
 * ------------------------------------------------------------------------ */

if ( ! function_exists( 'ccrm_render_login_form' ) ) {
	/**
	 * Render the username/password form.
	 *
	 * @param array $atts Panel attributes.
	 * @return string
	 */
	function ccrm_render_login_form( $atts ) {
		$redirect = ccrm_resolve_redirect_target();
		$notice   = ccrm_render_notice( ccrm_login_notice() );

		$heading = '' !== $atts['heading'] ? $atts['heading'] : __( 'Sign in', 'community-crm' );

		// No default hint: the panel shows one only if the block is given one.
		$hint = (string) $atts['hint'];

		ob_start();
		?>
		<div class="ccrm-signin__form">
			<h2 class="ccrm-signin__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( '' !== $hint ) : ?>
				<p class="ccrm-signin__hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>

			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in ccrm_render_notice(). ?>

			<?php
			/**
			 * Fires before the credential fields, for single sign-on buttons.
			 *
			 * An SSO plugin can print a "Continue with WordPress.org" button
			 * here; the divider below only appears if something was printed.
			 */
			$sso = '';
			if ( has_action( 'ccrm_login_form_sso' ) ) {
				ob_start();
				do_action( 'ccrm_login_form_sso' );
				$sso = trim( (string) ob_get_clean() );
			}

			if ( '' !== $sso ) {
				printf( '<div class="ccrm-signin__sso">%s</div>', $sso ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- third-party markup from the ccrm_login_form_sso action.
				printf(
					'<p class="ccrm-signin__divider">%s</p>',
					esc_html__( 'or', 'community-crm' )
				);
			}
			?>

			<form name="ccrm-loginform" id="ccrm-loginform" action="<?php echo esc_url( wp_login_url() ); ?>" method="post">
				<div class="ccrm-field">
					<label class="ccrm-field__label" for="ccrm-user-login">
						<?php esc_html_e( 'Username or email address', 'community-crm' ); ?>
					</label>
					<input
						type="text"
						class="ccrm-field__input"
						name="log"
						id="ccrm-user-login"
						autocapitalize="off"
						autocomplete="username"
						spellcheck="false"
						required
					/>
				</div>

				<div class="ccrm-field">
					<label class="ccrm-field__label" for="ccrm-user-pass">
						<?php esc_html_e( 'Password', 'community-crm' ); ?>
					</label>
					<input
						type="password"
						class="ccrm-field__input"
						name="pwd"
						id="ccrm-user-pass"
						autocomplete="current-password"
						spellcheck="false"
						required
					/>
				</div>

				<div class="ccrm-signin__meta">
					<label class="ccrm-checkbox" for="ccrm-rememberme">
						<input type="checkbox" name="rememberme" id="ccrm-rememberme" value="forever" />
						<span><?php esc_html_e( 'Keep me signed in', 'community-crm' ); ?></span>
					</label>
					<a class="ccrm-signin__lost" href="<?php echo esc_url( wp_lostpassword_url( ccrm_login_page_url() ) ); ?>">
						<?php esc_html_e( 'Lost your password?', 'community-crm' ); ?>
					</a>
				</div>

				<button type="submit" name="wp-submit" class="ccrm-button ccrm-button--block">
					<?php esc_html_e( 'Sign in', 'community-crm' ); ?>
				</button>

				<input type="hidden" name="ccrm_login" value="1" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
				<?php
				/**
				 * Fires inside the form, before the closing tag.
				 *
				 * Mirrors core's login_form hook so two-factor and similar
				 * plugins can add their fields.
				 */
				do_action( 'ccrm_login_form' );
				?>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ccrm_render_access_panel' ) ) {
	/**
	 * Render the "Request access" side panel.
	 *
	 * @param array $atts Panel attributes.
	 * @return string
	 */
	function ccrm_render_access_panel( $atts ) {
		$heading = '' !== $atts['accessHeading'] ? $atts['accessHeading'] : __( 'Need access?', 'community-crm' );
		$intro   = '' !== $atts['accessIntro'] ? $atts['accessIntro'] : __( 'Accounts are handed out by the Community team. Deputies and mentors can vouch for you.', 'community-crm' );
		$url     = '' !== $atts['accessUrl'] ? $atts['accessUrl'] : ccrm_request_access_url();

		$steps = array(
			__( 'Tell us which meetup or WordCamp you help run.', 'community-crm' ),
			__( 'Share your WordPress.org username.', 'community-crm' ),
			__( 'A deputy reviews the request and enables your account.', 'community-crm' ),
		);

		ob_start();
		?>
		<aside class="ccrm-access" id="access">
			<h2 class="ccrm-access__heading"><?php echo esc_html( $heading ); ?></h2>
			<p class="ccrm-signin__hint"><?php echo esc_html( $intro ); ?></p>
			<ol class="ccrm-access__list">
				<?php foreach ( $steps as $step ) : ?>
					<li><?php echo esc_html( $step ); ?></li>
				<?php endforeach; ?>
			</ol>
			<a class="ccrm-button ccrm-button--secondary" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'Request access', 'community-crm' ); ?>
			</a>
		</aside>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ccrm_render_signed_in' ) ) {
	/**
	 * Render the panel shown to someone who is already signed in.
	 *
	 * @return string
	 */
	function ccrm_render_signed_in() {
		$user = wp_get_current_user();

		ob_start();
		?>
		<div class="ccrm-signin__form">
			<h2 class="ccrm-signin__heading"><?php esc_html_e( 'You are signed in', 'community-crm' ); ?></h2>
			<div class="ccrm-signedin">
				<div class="ccrm-signedin__avatar"><?php echo get_avatar( $user->ID, 48 ); ?></div>
				<div class="ccrm-signedin__text">
					<p class="ccrm-signedin__name"><?php echo esc_html( $user->display_name ); ?></p>
					<p class="ccrm-signedin__meta">@<?php echo esc_html( $user->user_login ); ?></p>
				</div>
			</div>
			<div class="ccrm-signedin__actions">
				<a class="ccrm-button" href="<?php echo esc_url( ccrm_default_redirect_url() ); ?>">
					<?php esc_html_e( 'Open the CRM', 'community-crm' ); ?>
				</a>
				<a class="ccrm-button ccrm-button--secondary" href="<?php echo esc_url( wp_logout_url( ccrm_login_page_url() ) ); ?>">
					<?php esc_html_e( 'Sign out', 'community-crm' ); ?>
				</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ccrm_render_login_block' ) ) {
	/**
	 * Render callback for community-crm/login-form.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	function ccrm_render_login_block( $attributes = array() ) {
		$atts = wp_parse_args(
			(array) $attributes,
			array(
				'heading'       => '',
				'hint'          => '',
				'showAccess'    => true,
				'accessHeading' => '',
				'accessIntro'   => '',
				'accessUrl'     => '',
			)
		);

		// Inside the editor the current user is always signed in, which would
		// preview the wrong state — force the form for block-renderer requests.
		$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

		$primary = ( is_user_logged_in() && ! $is_editor_preview )
			? ccrm_render_signed_in()
			: ccrm_render_login_form( $atts );

		$access = $atts['showAccess'] ? ccrm_render_access_panel( $atts ) : '';

		$classes = 'ccrm-signin';
		if ( '' === $access ) {
			$classes .= ' ccrm-signin--single';
		}

		// Deliberately not get_block_wrapper_attributes(): the block declares
		// no style supports, so it would add nothing, and it reads render
		// state that does not exist when this runs from the shortcode.
		return sprintf(
			'<section id="signin" class="%1$s"><div class="ccrm-signin__grid">%2$s%3$s</div></section>',
			esc_attr( $classes ),
			$primary,
			$access
		);
	}
}

if ( ! function_exists( 'ccrm_register_login_block' ) ) {
	/**
	 * Register the sign-in block and its editor script.
	 *
	 * Registered in PHP with a render callback so the theme needs no build
	 * step; the editor uses ServerSideRender to preview it.
	 */
	function ccrm_register_login_block() {
		$attributes = array(
			'heading'       => array( 'type' => 'string', 'default' => '' ),
			'hint'          => array( 'type' => 'string', 'default' => '' ),
			'showAccess'    => array( 'type' => 'boolean', 'default' => true ),
			'accessHeading' => array( 'type' => 'string', 'default' => '' ),
			'accessIntro'   => array( 'type' => 'string', 'default' => '' ),
			'accessUrl'     => array( 'type' => 'string', 'default' => '' ),
		);

		wp_register_script(
			'ccrm-login-block',
			get_theme_file_uri( 'assets/js/login-block.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
			CCRM_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ccrm-login-block', 'community-crm' );
		}

		register_block_type(
			'community-crm/login-form',
			array(
				'api_version'     => 3,
				'title'           => __( 'CRM sign-in', 'community-crm' ),
				'description'     => __( 'The WordPress.org sign-in form and request-access panel for the Community CRM front page.', 'community-crm' ),
				'category'        => 'theme',
				'icon'            => 'admin-network',
				'keywords'        => array( __( 'login', 'community-crm' ), __( 'sign in', 'community-crm' ), __( 'crm', 'community-crm' ) ),
				'supports'        => array(
					'html'   => false,
					'align'  => false,
					'anchor' => false,
				),
				'attributes'      => $attributes,
				'editor_script'   => 'ccrm-login-block',
				'render_callback' => 'ccrm_render_login_block',
				'example'         => array( 'attributes' => array() ),
			)
		);
	}
}
add_action( 'init', 'ccrm_register_login_block' );

if ( ! function_exists( 'ccrm_login_shortcode' ) ) {
	/**
	 * [community_crm_login] — the same panel for classic content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	function ccrm_login_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'heading'        => '',
				'hint'           => '',
				'show_access'    => 'yes',
				'access_heading' => '',
				'access_intro'   => '',
				'access_url'     => '',
			),
			$atts,
			'community_crm_login'
		);

		return ccrm_render_login_block(
			array(
				'heading'       => $atts['heading'],
				'hint'          => $atts['hint'],
				'showAccess'    => in_array( strtolower( (string) $atts['show_access'] ), array( 'yes', 'true', '1' ), true ),
				'accessHeading' => $atts['access_heading'],
				'accessIntro'   => $atts['access_intro'],
				'accessUrl'     => $atts['access_url'],
			)
		);
	}
}
add_shortcode( 'community_crm_login', 'ccrm_login_shortcode' );

/* ---------------------------------------------------------------------------
 * wp-login.php, wearing the same clothes
 * ------------------------------------------------------------------------ */

if ( ! function_exists( 'ccrm_login_screen_styles' ) ) {
	/**
	 * Skin wp-login.php with the theme's tokens.
	 */
	function ccrm_login_screen_styles() {
		wp_enqueue_style(
			'ccrm-login-screen',
			get_theme_file_uri( 'assets/css/login-screen.css' ),
			array( 'login' ),
			CCRM_VERSION
		);
	}
}
add_action( 'login_enqueue_scripts', 'ccrm_login_screen_styles' );

if ( ! function_exists( 'ccrm_login_header_url' ) ) {
	/**
	 * Point the login logo at the CRM homepage rather than wordpress.org.
	 *
	 * @return string
	 */
	function ccrm_login_header_url() {
		return ccrm_login_page_url();
	}
}
add_filter( 'login_headerurl', 'ccrm_login_header_url' );

if ( ! function_exists( 'ccrm_login_header_text' ) ) {
	/**
	 * Use the site name for the login logo's title attribute.
	 *
	 * @return string
	 */
	function ccrm_login_header_text() {
		return get_bloginfo( 'name', 'display' );
	}
}
add_filter( 'login_headertext', 'ccrm_login_header_text' );

if ( ! function_exists( 'ccrm_login_screen_footer' ) ) {
	/**
	 * Echo the design's footer line under the wp-login.php card.
	 *
	 * Hooked to login_footer, not login_form: core fires login_form between
	 * the password field and "Remember Me", which would drop the line into
	 * the middle of the form.
	 */
	function ccrm_login_screen_footer() {
		printf(
			'<p class="ccrm-login-screen__poetry">%s</p>',
			esc_html__( 'Code is poetry.', 'community-crm' )
		);
	}
}
add_action( 'login_footer', 'ccrm_login_screen_footer' );
