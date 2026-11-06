<?php
/**
 * Tool - Header notices.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writing the audience notices, on one screen.
 *
 * One editor and one Save button for each audience, each in a form of its own (1.123.58). `wp_editor()` gives each one the classic editor with its
 * media button, so a notice can hold links, images, lists and headings without this screen
 * having to provide any of that itself.
 *
 * The editors are deliberately *not* `teeny`. The small editor drops the media button, and
 * the combination of `teeny` with a custom toolbar cancels both the toolbar and the media
 * button - which would leave a notice unable to hold the images the feature was asked for.
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
		return __( 'Write a notice for one audience - students, mentors, institutions, sponsors or administrators. Each one appears at the top of the page for the people it is addressed to, and for nobody else.', 'wpcredits-program-manager' );
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
	 * and this one does not - a notice is written by hand and reads nothing.
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
	 * Persist one audience's notice, when the page it was written on is still current (1.123.58).
	 *
	 * Capability first, then the audience's own nonce. The form posts the fingerprint of the body as
	 * the editor was filled (`wpcpm_drawn_body`):
	 *
	 * - a body posted back as drawn changed nothing, so nothing is written and the person is told the
	 *   notice is saved, as it stands;
	 * - a body the person changed is written when the stored body is still the one drawn;
	 * - when the stored body changed since, nothing is written, the typing is kept for the redraw and
	 *   the sentence says so.
	 *
	 * The other audiences are never written by this form.
	 */
	public function handle_save() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$audience = isset( $_POST['wpcpm_audience'] ) ? sanitize_key( wp_unslash( $_POST['wpcpm_audience'] ) ) : '';

		if ( ! array_key_exists( $audience, WPCPM_Notices::audiences() ) ) {
			wp_die( esc_html__( 'That is not an audience with a notice.', 'wpcredits-program-manager' ), 400 );
		}

		check_admin_referer( 'wpcpm_notice_' . $audience, self::NONCE . '_' . $audience );

		$posted = isset( $_POST['wpcpm_notice_body'] ) && is_string( $_POST['wpcpm_notice_body'] )
			? wp_unslash( $_POST['wpcpm_notice_body'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The body is filtered through wp_kses_post() in WPCPM_Notices::clean().
			: '';
		$drawn  = isset( $_POST['wpcpm_drawn_body'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcpm_drawn_body'] ) ) : '';
		$form   = self::form( $audience );

		// The refusal of an earlier press whose page never drew the form must not fill the box after this one.
		WPCPM_Flash::forget( $form );

		$outcome = 'notice-saved';

		if ( WPCPM_Settings::fingerprint( WPCPM_Notices::clean( $posted ) ) !== $drawn ) {
			if ( WPCPM_Settings::fingerprint( WPCPM_Notices::body( $audience ) ) === $drawn ) {
				WPCPM_Notices::save_one( $audience, $posted );
			} else {
				WPCPM_Flash::keep( $form, array( 'body' => $posted ) );

				$outcome = 'notice-stale';
			}
		}

		// A flash rather than a query argument: `?saved=1` stays in the address bar, so
		// reloading the screen reports a save that did not happen.
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'outcome'  => $outcome,
				'audience' => $audience,
			)
		);

		wp_safe_redirect( $this->admin_url() );
		exit;
	}

	/**
	 * The kept-values form of one audience's notice.
	 *
	 * @param string $audience Audience slug.
	 * @return string
	 */
	private static function form( $audience ) {
		return 'notice:' . $audience;
	}

	/**
	 * Say what a Save did, in the notice above the editors.
	 *
	 * @param mixed $flash What the Save left for this page.
	 */
	private function render_outcome( $flash ) {
		$audiences = WPCPM_Notices::audiences();

		if ( ! is_array( $flash ) || ! isset( $flash['outcome'], $flash['audience'], $audiences[ $flash['audience'] ] ) ) {
			return;
		}

		$who = $audiences[ $flash['audience'] ];

		if ( 'notice-saved' === $flash['outcome'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: the audience, such as students. */
						__( 'The notice for %s is saved.', 'wpcredits-program-manager' ),
						$who
					)
				)
			);
		} elseif ( 'notice-stale' === $flash['outcome'] ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: the audience, such as students. */
						__( 'The notice for %s was not saved: it was changed elsewhere after this page was opened. Your text is still in the box: check it against the notice as it is now, and save again.', 'wpcredits-program-manager' ),
						$who
					)
				)
			);
		}
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

		$this->render_outcome( WPCPM_Flash::take( self::FLASH ) );

		printf(
			'<div class="wpcpm-card"><p class="description">%s</p></div>',
			esc_html__( 'Empty a notice to stop it showing; there is no separate switch. Anyone in two audiences sees both notices, so an administrator who also mentors gets the mentor notice as well as the administrator one.', 'wpcredits-program-manager' )
		);

		$bodies = WPCPM_Notices::bodies();

		foreach ( WPCPM_Notices::audiences() as $slug => $label ) {
			$body  = isset( $bodies[ $slug ] ) ? $bodies[ $slug ] : '';
			$kept  = WPCPM_Flash::kept( self::form( $slug ) );
			$typed = isset( $kept['values']['body'] ) && is_string( $kept['values']['body'] ) ? $kept['values']['body'] : $body;

			// The editor ID has to be lowercase letters and underscores only - TinyMCE is
			// initialized by that ID, and a hyphen in it breaks the editor rather than
			// degrading it.
			$editor_id = 'wpcpm_notice_' . $slug;

			// One form for each audience, so a Save writes that audience alone. The fingerprint is of the
			// stored body, not of the typing drawn back, so a Save after a refusal is a deliberate one.
			printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::NONCE ) );
			printf( '<input type="hidden" name="wpcpm_audience" value="%s" />', esc_attr( $slug ) );
			printf( '<input type="hidden" name="wpcpm_drawn_body" value="%s" />', esc_attr( WPCPM_Settings::fingerprint( $body ) ) );
			// A field of its own for each form, so the five nonce fields do not share one id.
			wp_nonce_field( 'wpcpm_notice_' . $slug, self::NONCE . '_' . $slug );

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
				$typed,
				$editor_id,
				array(
					'textarea_name' => 'wpcpm_notice_body',
					'textarea_rows' => 6,
					'media_buttons' => true,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => true,
				)
			);

			submit_button( __( 'Save this notice', 'wpcredits-program-manager' ), 'primary', 'submit', true, array( 'id' => 'wpcpm-save-notice-' . $slug ) );

			echo '</div>';
			echo '</form>';
		}

		echo '</div>';
	}
}
