<?php
/**
 * The Sponsor Dashboard's logo card: two optional images, and the base kept in step.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A sponsor replaces the logo Airtable holds with one it uploads.
 *
 * Design spec of 4 September 2026, decision 11 and section 8.1. Two files, each optional: the
 * color logo the dashboard and the Tools section draw, and a white one for a dark ground. Both
 * go through `WPCPM_Image_Upload`, which is the only thing in this plugin allowed to turn
 * somebody else's bytes into a Media Library item: PNG, JPEG or WebP by content, at least 200
 * pixels wide, no side past 4000, re-saved through the editor so nothing the uploader put after
 * the image data survives. SVG is refused, which is decision 11's other half: it is a document
 * that can carry script, and the two sponsors who hold one convert it.
 *
 * **All or nothing.** Two files arrive together and one bad one refuses the pair, the way one
 * rejected field refuses the whole profile save. Half a change is the thing a person cannot see.
 *
 * **The record says who owns the logo.** `wpcpm_sponsor_logo_<record>` carries `source =>
 * site` after an upload, and `WPCPM_Sponsors_Sync::phase_logos()` reads exactly that before it
 * copies anything: a logo the sponsor uploaded here is never replaced by Airtable's, even with
 * the sync running every three hours. Remove empties the base's `Logo` first and clears the
 * record only when that worked, because a cleared record with this site's own URLs still in
 * the base is a logo the sponsor can never remove: the next sponsors sync run copies those
 * URLs back in as Airtable's, and the card stops offering Remove.
 * The files stay in the Media Library either way, so a post that embeds one keeps working.
 *
 * **Airtable's `Logo` is written with the site's public URLs.** The attachment field is replaced
 * whole, color first, so the base shows the picture the site shows and the WPCredits tracker,
 * which reads the base and not this site, keeps working unchanged (spec section 14). A PATCH that
 * fails is said out loud and loses nothing: the site holds the logo either way.
 */
final class WPCPM_Sponsor_Logo {

	/** The card's anchor and flash key on the Sponsor Dashboard. */
	/**
	 * The card the logo lives in: the profile's, since 1.96.6 (owner: one section, "Your company
	 * profile and logo"). The flashes land on it and open it.
	 */
	const CARD = 'profile';

	/** The upload, multipart, a member or a manager on behalf. Nonce keyed to the record. */
	const ACTION_UPLOAD = 'wpcpm_sponsor_logo';

	/** Give the logo back to Airtable's. Nonce keyed to the record. */
	const ACTION_REMOVE = 'wpcpm_sponsor_logo_remove';

	/** The ceiling's key stem, and how many uploads one sponsor gets a day. */
	const CEILING = 'sponsor-logo:';
	const PER_DAY = 5;

	/** The two file fields. */
	const FIELD_COLOR = 'wpcpm_logo_color';
	const FIELD_WHITE = 'wpcpm_logo_white';

	/** Audit kinds this class writes. */
	const LOG_UPLOAD = 'logo_uploaded';
	const LOG_REMOVE = 'logo_removed';

	/**
	 * The handlers.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_UPLOAD, array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( __CLASS__, 'handle_remove' ) );
	}

	/**
	 * This card's outcomes, in the reader's words.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'logo-saved'            => array( 'success', __( 'Your logo is saved, here and in the program records.', 'wpcredits-program-manager' ) ),
			'logo-removed'          => array( 'success', __( 'Your logo is removed here and from the program records. Upload a new one whenever you like.', 'wpcredits-program-manager' ) ),
			'logo-removed-airtable' => array( 'error', __( 'Nothing was removed: the program records could not be told just now. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'logo-none'             => array( 'error', __( 'Nothing was saved. Choose a color logo, a white one, or both.', 'wpcredits-program-manager' ) ),
			'logo-not-site'         => array( 'error', __( 'There is no uploaded logo to remove here. The logo shown comes from Airtable; a program manager can change it there.', 'wpcredits-program-manager' ) ),
			'logo-refused'          => array( 'error', __( 'Nothing was saved. A logo has to be a PNG, JPEG or WebP image, at least 200 pixels wide and no more than 4000 on a side. SVG is not accepted: export the logo as a PNG.', 'wpcredits-program-manager' ) ),
			'logo-busy'             => array( 'error', __( 'Nothing was saved. This sponsor has used up the logo uploads one day allows. Try again tomorrow.', 'wpcredits-program-manager' ) ),
			'logo-failed'           => array( 'error', __( 'Nothing was saved. This site could not store the image, which is this site\'s fault and not yours. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'logo-airtable'         => array( 'warning', __( 'Your logo is saved on the site, and the program records could not be told just now. The site shows the new logo; the records catch up on the next attempt.', 'wpcredits-program-manager' ) ),
			'refused'               => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Take one or both logos.
	 *
	 * The order: the nonce keyed to the record, then the roster's claim (which decides
	 * `ACT_UPLOAD_LOGO` and meters a refusal), then whether any file arrived at all, then the
	 * daily ceiling, then the bytes. The ceiling is above the bytes on purpose, as the agreement
	 * upload's is: a runaway script must be refused before a megabyte is read into this process.
	 * It sits below the "did anything arrive" question because it used to sit above it, and five
	 * presses of Save with both fields empty then locked every colleague out of the day's five
	 * uploads without a single file having been sent (FSPON-4). The ceiling has no release.
	 */
	public static function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to upload a logo.', 'wpcredits-program-manager' ), 403 );
		}

		$asked = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_UPLOAD . '_' . $asked );

		$claim = WPCPM_Sponsor_Roster::claim( $asked, WPCPM_Sponsor_Policy::ACT_UPLOAD_LOGO );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];

		// An index the site has not read holds no company name to title the attachment with,
		// and no row for the card to draw afterwards.
		if ( ! is_array( $claim['row'] ) ) {
			self::leave( 'logo-failed', $record );
		}

		$company  = '' !== trim( (string) $claim['row']['name'] ) ? trim( (string) $claim['row']['name'] ) : $record;
		$incoming = array();

		$fields = array(
			'colour' => self::FIELD_COLOR,
			'white'  => self::FIELD_WHITE,
		);

		foreach ( $fields as $half => $field ) {
			$file = self::uploaded( $field );

			if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
				continue;
			}

			$incoming[ $half ] = $file;
		}

		if ( empty( $incoming ) ) {
			self::leave( 'logo-none', $record );
		}

		// A file did arrive, so this press takes one of the day's five, and takes it before a
		// byte of the file is read.
		if ( ! WPCPM_Ceiling::claim( self::CEILING . $record, self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::leave( 'logo-busy', $record );
		}

		// Both files are accepted before either is stored: one bad half refuses the pair, so a
		// sponsor never ends up with a new color logo beside the old white one and no way to
		// tell which press produced which.
		$accepted = array();

		foreach ( $incoming as $half => $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
				self::clean_up( $accepted );

				// PHP's own verdict first: a file the server dropped for its size, or one that did
				// not finish, is not "nothing arrived", and the sponsor would look for the wrong fault.
				if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
					self::leave( 'logo-refused', $record, __( 'The file is larger than the server accepts.', 'wpcredits-program-manager' ) );
				}

				if ( UPLOAD_ERR_PARTIAL === (int) $file['error'] ) {
					self::leave( 'logo-refused', $record, __( 'The upload did not finish; try again.', 'wpcredits-program-manager' ) );
				}

				self::leave( 'logo-refused', $record, __( 'No file arrived with the form.', 'wpcredits-program-manager' ) );
			}

			$one = WPCPM_Image_Upload::accept( $file['tmp_name'], array( 'name' => $file['name'] ) );

			if ( is_wp_error( $one ) ) {
				self::clean_up( $accepted );
				self::leave( 'logo-refused', $record, $one->get_error_message() );
			}

			$accepted[ $half ] = $one;
		}

		$write = array(
			'source'      => 'site',
			'airtable_id' => '',
		);

		$stored = array();

		foreach ( $accepted as $half => $one ) {
			$id = WPCPM_Image_Upload::store(
				$one,
				$company . ' logo',
				get_current_user_id(),
				'colour' === $half
					? sprintf(
						/* translators: %s: company name. */
						__( '%s logo (color)', 'wpcredits-program-manager' ),
						$company
					)
					: sprintf(
						/* translators: %s: company name. */
						__( '%s logo (white)', 'wpcredits-program-manager' ),
						$company
					)
			);

			if ( is_wp_error( $id ) ) {
				// `store()` deletes the copy it was handed whether it succeeded or not, so
				// what is left to clean up is the halves this loop has not reached.
				self::clean_up( array_diff_key( $accepted, $stored, array( $half => true ) ) );
				self::leave( 'logo-failed', $record );
			}

			$stored[ $half ] = (int) $id;
			$write[ $half ]  = (int) $id;
		}

		WPCPM_Sponsors_Index::write_logo_record( $record, $write );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_UPLOAD,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => get_current_user_id(),
				'ground'   => (string) $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'A logo was uploaded on the Sponsor Dashboard and written to the program records.', 'wpcredits-program-manager' ),
				// Which halves changed, never a path: the file is public and the log is not
				// the place to keep a second address for it.
				'data'     => array( 'halves' => array_keys( $accepted ) ),
			)
		);

		if ( ! self::write_airtable( $record ) ) {
			self::leave( 'logo-airtable', $record );
		}

		self::leave( 'logo-saved', $record );
	}

	/**
	 * Take the uploaded logo out of the site and out of the program records.
	 *
	 * **The base first, and nothing here changes when it refuses.** The upload replaced the
	 * base's `Logo` with this site's public URLs, so there is no original left to come back to:
	 * a site record cleared while those URLs stand would have the next sponsors sync run copy the
	 * site's own picture back in as Airtable's, under a record the sponsor no longer owns, and
	 * the Remove button (drawn only for a logo this site owns) would be gone from the card.
	 *
	 * Nothing is deleted from the Media Library. A published sponsor post that embeds the image
	 * keeps working, and a manager who wants the file gone deletes it in wp-admin, where the
	 * consequences of deleting an attachment are visible.
	 *
	 * **Only a logo this site owns.** The premise named above - that the upload already replaced
	 * the base's `Logo` with this site's public URLs - is now checked here instead of assumed
	 * from the button being drawn: a record that fails the check refuses with `logo-not-site`,
	 * and nothing is PATCHed (FSPON-5).
	 */
	public static function handle_remove() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to change a logo.', 'wpcredits-program-manager' ), 403 );
		}

		$asked = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_REMOVE . '_' . $asked );

		$claim = WPCPM_Sponsor_Roster::claim( $asked, WPCPM_Sponsor_Policy::ACT_UPLOAD_LOGO );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];

		// The card draws Remove only for a logo this site owns, and this asks the same question
		// of the same record rather than trusting that it did. The nonce minted in that branch
		// stays valid for its whole life, and the sponsors sync can set the source back to
		// airtable in between; without this the PATCH below would empty the Logo of a sponsor
		// whose picture came from the base, destroying the only copy the program holds, under an
		// audit row saying it was removed from the program records (FSPON-5).
		if ( 'site' !== (string) WPCPM_Sponsors_Index::logo_record( $record )['source'] ) {
			self::leave( 'logo-not-site', $record );
		}

		// The base's Logo was replaced by the upload, so nothing of the original is left to
		// come back. It is emptied first: a site record cleared ahead of a PATCH that then
		// failed would leave the site's own URLs in the base with nothing here saying so.
		$told = self::clear_airtable( $record );

		if ( $told ) {
			WPCPM_Sponsors_Index::write_logo_record(
				$record,
				array(
					'colour'      => 0,
					'white'       => 0,
					'source'      => '',
					'airtable_id' => '',
				)
			);

			WPCPM_Sponsors_Index::patch( $record, array( 'logo' => array() ) );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_REMOVE,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => get_current_user_id(),
				'ground'   => (string) $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $told
					? __( 'The logo was removed here and from the program records.', 'wpcredits-program-manager' )
					: __( 'The logo was left in place: the program records could not be told.', 'wpcredits-program-manager' ),
				'data'     => array( 'airtable' => (bool) $told ),
			)
		);

		self::leave( $told ? 'logo-removed' : 'logo-removed-airtable', $record );
	}

	/**
	 * The card.
	 *
	 * A manager sees exactly what a member sees, for whichever sponsor the switcher is on:
	 * uploading a logo on a sponsor's behalf is one of the things managers do, and a card that
	 * hid the form from them would send them to Airtable to do it a slower way.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$open = isset( $context['open'] ) && 'logo' === $context['open'];

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-logo" class="wpcpm-group wpcpm-group__disclosure"%s>', $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Your logo', 'wpcredits-program-manager' )
		);
		echo '<div class="wpcpm-group__body">';
		self::render_inner( $record );
		echo '</div></details></section>';
	}

	/**
	 * The logo block without a card of its own: the note, the two tiles, the upload form and
	 * Remove. The profile card prints it under its form (1.96.6); `render()` above keeps the
	 * standalone card for a site that wants one.
	 *
	 * @param string $record Airtable record ID.
	 */
	public static function render_inner( $record ) {
		$logo   = WPCPM_Sponsors_Index::logo_record( $record );
		$base   = 'wpcpm-logo-' . sanitize_html_class( $record );
		$halves = array(
			'colour' => __( 'Color', 'wpcredits-program-manager' ),
			'white'  => __( 'White, for a dark background', 'wpcredits-program-manager' ),
		);

		echo '<div class="wpcpm-logo">';

		echo '<p class="wpcpm-student__note">' . esc_html__( 'The picture students and mentors see beside your offer. A PNG, JPEG or WebP image, at least 200 pixels wide. SVG is not accepted, so export it as a PNG. What you upload here replaces the logo in the program records.', 'wpcredits-program-manager' ) . '</p>';

		echo '<ul class="wpcpm-logo__tiles">';

		foreach ( $halves as $half => $label ) {
			$id = (int) $logo[ $half ];

			echo '<li>';
			printf( '<span class="wpcpm-logo__label">%s</span>', esc_html( $label ) );

			if ( $id > 0 ) {
				printf(
					'<span class="wpcpm-logo__tile wpcpm-logo__tile--%1$s">%2$s</span>',
					esc_attr( 'colour' === $half ? 'color' : 'white' ),
					wp_get_attachment_image( $id, 'medium', false, array( 'alt' => '' ) )
				);
			} else {
				printf( '<span class="wpcpm-logo__empty">%s</span>', esc_html__( 'Nothing uploaded yet.', 'wpcredits-program-manager' ) );
			}

			echo '</li>';
		}

		echo '</ul>';

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form" enctype="multipart/form-data" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Uploading', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_UPLOAD . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_UPLOAD ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );

		printf(
			'<p class="wpcpm-sponsor__field"><label for="%1$s-color">%2$s</label> <input type="file" id="%1$s-color" name="%3$s" accept="image/png,image/jpeg,image/webp" /></p>',
			esc_attr( $base ),
			esc_html__( 'Color logo', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_COLOR )
		);
		printf(
			'<p class="wpcpm-sponsor__field"><label for="%1$s-white">%2$s</label> <input type="file" id="%1$s-white" name="%3$s" accept="image/png,image/jpeg,image/webp" /></p>',
			esc_attr( $base ),
			esc_html__( 'White logo, optional', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_WHITE )
		);

		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save the logo', 'wpcredits-program-manager' ) );
		echo '</form>';

		if ( 'site' === (string) $logo['source'] ) {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-sponsor__form" data-wpcpm-once data-wpcpm-busy="%2$s" onsubmit="return confirm(%3$s);">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Removing', 'wpcredits-program-manager' ),
				esc_attr( wp_json_encode( __( 'Remove the logo you uploaded? It goes from this site and from the program records. The files stay in the Media Library, so anything that already shows them keeps working.', 'wpcredits-program-manager' ) ) )
			);
			wp_nonce_field( self::ACTION_REMOVE . '_' . $record );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REMOVE ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Remove it', 'wpcredits-program-manager' ) );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * One posted file, every member typed, nothing trusted.
	 *
	 * `$_FILES` is the one superglobal a form field cannot be read out of with `WPCPM_Request`,
	 * so it is read here, once, for both fields, and each member is cast on the way out. What
	 * makes the bytes safe is `WPCPM_Image_Upload`, not a filter on this array.
	 *
	 * @param string $field The field name.
	 * @return array{error: int, size: int, tmp_name: string, name: string}
	 */
	private static function uploaded( $field ) {
		$empty = array(
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
			'tmp_name' => '',
			'name'     => '',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- `handle_upload()` verifies the nonce before it calls this.
		if ( ! isset( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
			return $empty;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above for the nonce; every member is cast or sanitized on the lines below, which is the only sanitizing an upload admits.
		$raw = wp_unslash( $_FILES[ $field ] );

		return array(
			'error'    => isset( $raw['error'] ) ? (int) $raw['error'] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $raw['size'] ) ? (int) $raw['size'] : 0,
			'tmp_name' => isset( $raw['tmp_name'] ) ? (string) $raw['tmp_name'] : '',
			'name'     => isset( $raw['name'] ) ? substr( sanitize_file_name( (string) $raw['name'] ), 0, 200 ) : '',
		);
	}

	/**
	 * Whether this path is a file PHP received on this request.
	 *
	 * `is_uploaded_file()` is what stops a posted string naming a file on disk from being read
	 * out of the filesystem and published as somebody's logo. It answers from PHP's own list,
	 * which is empty under CLI, where the suites run and where no `admin_post_` action can
	 * fire at all.
	 *
	 * @param string $path The temporary path the upload arrived at.
	 * @return bool
	 */
	private static function arrived_by_post( $path ) {
		if ( 'cli' === PHP_SAPI ) {
			return is_readable( $path );
		}

		return is_uploaded_file( $path );
	}

	/**
	 * Delete the re-saved copies of files that were accepted before a later one refused.
	 *
	 * `accept()` hands back a temporary path that is the caller's to store or delete, and a
	 * refusal after the first half was accepted would otherwise leave one behind for nothing
	 * to clean up.
	 *
	 * @param array $accepted What `accept()` returned, keyed by half.
	 */
	private static function clean_up( array $accepted ) {
		foreach ( $accepted as $one ) {
			if ( is_array( $one ) && ! empty( $one['path'] ) ) {
				wp_delete_file( (string) $one['path'] );
			}
		}
	}

	/**
	 * Write the sponsor's logo attachments to Airtable's `Logo`, color first.
	 *
	 * The attachment field is replaced whole, which is Airtable's only mode for one: sending
	 * the halves the site holds is what keeps the base showing what the dashboard shows. The
	 * URLs are the Media Library's public ones, because Airtable fetches them itself.
	 *
	 * @param string $record Sponsor record ID.
	 * @return bool Whether the base took it.
	 */
	private static function write_airtable( $record ) {
		$logo  = WPCPM_Sponsors_Index::logo_record( $record );
		$cells = array();

		foreach ( array( 'colour', 'white' ) as $half ) {
			$id = (int) $logo[ $half ];

			if ( $id < 1 ) {
				continue;
			}

			$url = wp_get_attachment_url( $id );

			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$cells[] = array(
				'url'      => $url,
				'filename' => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			);
		}

		// Nothing to send is not success: the flash that follows says the records were told.
		if ( empty( $cells ) ) {
			return false;
		}

		return self::patch_logo( $record, $cells );
	}

	/**
	 * Empty the base's `Logo`: Remove takes the picture out of the program records as well.
	 *
	 * @param string $record Airtable record ID.
	 * @return bool Whether the PATCH went through.
	 */
	private static function clear_airtable( $record ) {
		return self::patch_logo( $record, array() );
	}

	/**
	 * One PATCH of the `Logo` attachment field.
	 *
	 * @param string $record Airtable record ID.
	 * @param array  $cells  The attachments, `url` plus `filename` each, or an empty array.
	 * @return bool
	 */
	private static function patch_logo( $record, array $cells ) {
		$fields   = WPCPM_Sponsors_Sync::fields();
		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $record,
					'fields' => array( $fields['logo'] => $cells ),
				),
			)
		);

		return ! is_wp_error( $written ) && ! empty( $written );
	}

	/**
	 * Flash and go back to the dashboard, at this card.
	 *
	 * Through the dashboard's own method by array callable, as the profile card does: the two
	 * classes land in the same release and `bin/check-references.php` would otherwise flag a
	 * call to a method it cannot see declared yet.
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 * @param string $detail A sentence after the status's own, or ''.
	 */
	private static function leave( $status, $record, $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail );
		exit;
	}
}
