<?php
/**
 * Tool — Header notices.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writing the four audience notices, on one screen.
 *
 * Four editors and one Save button. `wp_editor()` gives each one the classic editor with its
 * media button, so a notice can hold links, images, lists and headings without this screen
 * having to provide any of that itself.
 *
 * The editors are deliberately *not* `teeny`. The small editor drops the media button, and
 * the combination of `teeny` with a custom toolbar cancels both the toolbar and the media
 * button — which would leave a notice unable to hold the images the feature was asked for.
 */
class WPCPM_Header_Notices extends WPCPM_Tool {

	/** Nonce action and field name for the save. */
	const NONCE = 'wpcpm_save_notices';

	/** Flash channel for the outcome message. */
	const FLASH = 'notices';

	/**
	 * Tool ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'header-notices';
	}

	/**
	 * Tool name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Header notices', 'wpcredits-program-manager' );
	}

	/**
	 * One-line description for the Tools screen.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Write a notice for one audience — students, mentors, institutions, sponsors or administrators. Each one appears at the top of the page for the people it is addressed to, and for nobody else.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		add_action( 'admin_post_' . self::NONCE, array( $this, 'handle_save' ) );
	}

	/**
	 * Always ready.
	 *
	 * The base class treats "ready" as "Airtable is connected", which every other tool needs
	 * and this one does not — a notice is written by hand and reads nothing.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * How many notices are live, for the Tools screen.
	 *
	 * @return string
	 */
	public function status_line() {
		$live = 0;

		foreach ( WPCPM_Notices::bodies() as $body ) {
			if ( '' !== $body ) {
				++$live;
			}
		}

		if ( ! $live ) {
			return __( 'No notices are showing.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: number of notices. */
			_n( '%s notice is showing.', '%s notices are showing.', $live, 'wpcredits-program-manager' ),
			number_format_i18n( $live )
		);
	}

	/**
	 * Persist the four notices.
	 */
	public function handle_save() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( self::NONCE, self::NONCE );

		$posted = isset( $_POST['wpcpm_notice'] ) && is_array( $_POST['wpcpm_notice'] )
			? wp_unslash( $_POST['wpcpm_notice'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each body is filtered through wp_kses_post() in WPCPM_Notices::save().
			: array();

		WPCPM_Notices::save( (array) $posted );

		// A flash rather than a query argument: `?saved=1` stays in the address bar, so
		// reloading the screen reports a save that did not happen.
		WPCPM_Flash::set( self::FLASH, 'saved' );

		wp_safe_redirect( $this->admin_url() );
		exit;
	}

	/**
	 * Render the tool's screen.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		if ( 'saved' === WPCPM_Flash::take( self::FLASH ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Notices saved.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<div class="wpcpm-card"><p class="description">%s</p></div>',
			esc_html__( 'Empty a notice to stop it showing; there is no separate switch. Anyone in two audiences sees both notices, so an administrator who also mentors gets the mentor notice as well as the administrator one.', 'wpcredits-program-manager' )
		);

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::NONCE ) );
		wp_nonce_field( self::NONCE, self::NONCE );

		$bodies = WPCPM_Notices::bodies();

		foreach ( WPCPM_Notices::audiences() as $slug => $label ) {
			$body = isset( $bodies[ $slug ] ) ? $bodies[ $slug ] : '';

			// The editor ID has to be lowercase letters and underscores only — TinyMCE is
			// initialised by that ID, and a hyphen in it breaks the editor rather than
			// degrading it.
			$editor_id = 'wpcpm_notice_' . $slug;

			echo '<div class="wpcpm-card">';

			// A plain `h2`: the admin stylesheet styles `.wpcpm-card h2`, so a card heading
			// with its own class would go unstyled.
			printf(
				'<h2><label for="%1$s">%2$s</label></h2>',
				esc_attr( $editor_id ),
				esc_html( $label )
			);

			printf(
				'<p class="description">%s</p>',
				'' === $body
					? esc_html__( 'Not showing.', 'wpcredits-program-manager' )
					: '<strong>' . esc_html__( 'Showing now.', 'wpcredits-program-manager' ) . '</strong>'
			);

			wp_editor(
				$body,
				$editor_id,
				array(
					'textarea_name' => 'wpcpm_notice[' . $slug . ']',
					'textarea_rows' => 6,
					'media_buttons' => true,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => true,
				)
			);

			echo '</div>';
		}

		submit_button( __( 'Save notices', 'wpcredits-program-manager' ) );

		echo '</form>';
		echo '</div>';
	}
}
