<?php
/**
 * The annual sponsor report's PDF and its printed document, and the four routes to them.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One PDF per sponsor report, the report as a document to print, and who may have either.
 *
 * The half of the report that touches the request, kept apart from `WPCPM_Sponsor_Report`, which
 * stores and generates and never learns who is asking, as the semester report's screen is kept
 * apart from its store. This file reads the posted file and the query string, asks the policy who
 * the reader is, and writes nothing to a report but the two keys the store names for its PDF.
 *
 * **The PDF** is a program manager's to attach, replace and remove, and it is checked exactly as a
 * sponsor's signed agreement is (`WPCPM_Sponsor_Agreement::handle_upload()`): WordPress's own
 * name-and-type map, then the bytes (the magic, `finfo`), then the courtesy scan, before a byte is
 * stored. The bytes go into `WPCPM_Private_Files`, encrypted, and the report records what the
 * store answered (`META_FILE`) and, for a manager's eye alone, the name the file came with
 * (`META_FILE_NAME`). A replacement forgets the file it replaced once the new one is recorded, and
 * a removal forgets the file it removed, so neither leaves the report pointing at bytes that are
 * gone. The PDF may be attached, replaced or removed on a final report too: a report is marked
 * final, printed and then attached, and the members' download is the final report's. Only the
 * narratives and the figures wait for a reopen, and every change of the PDF is a row on the trail.
 *
 * **The download** is always an attachment under a name the site builds, with the agreement
 * download's headers: the name a file came with is the one string on this path somebody chose,
 * and a header is where a chosen string must not go.
 *
 * **The document** is the semester report's shape: one standalone HTML page echoed from
 * admin-post.php, the semester report's stylesheet inlined and its print script loaded, the
 * report's sections in the order they print, the generated figures as captioned tables and the
 * narratives as paragraphs. Its body carries the semester report's class for every rule the two
 * documents share, and a class of its own for the few rules only it needs. It carries counts, the
 * mentors' names and the company's own facts, the titles of its events among them, and no student
 * and nobody who joined an event: the snapshot holds nothing else, and `bin/test-sponsor-report.php`
 * walks the document for every student and institution it seeds, and for the people on the events
 * it seeds, the call link and the description.
 *
 * **Who reads either:** a program manager always; a member of the report's sponsor once the
 * report is final and while the tiers are live. The policy answers who
 * (`WPCPM_Sponsor_Policy::ACT_VIEW_REPORT`) and the routes add the state and the switch, which the
 * policy knows nothing of. Both links ask for their nonce before anything is looked up, so no
 * answer tells one id from another. A signed-out reader is sent to the login form, and anyone else
 * refused meets the policy's one refusal.
 */
final class WPCPM_Sponsor_Report_Files {

	/** Attach a PDF to a report, or replace the one it has: `admin_post_`, multipart, a manager. */
	const ACTION_UPLOAD = 'wpcpm_sreport_upload';

	/** Remove a report's PDF: `admin_post_`, a manager. */
	const ACTION_REMOVE = 'wpcpm_sreport_remove';

	/** The only route to a report's PDF: a GET, registered for a signed-out visitor too. */
	const ACTION_DOWNLOAD = 'wpcpm_sreport_pdf';

	/** The report as a document to print: a GET, registered for a signed-out visitor too. */
	const ACTION_PRINT = 'wpcpm_sreport_print';

	/** The posted file. */
	const FIELD_FILE = 'wpcpm_sreport_file';

	/** The report a form posts about. */
	const FIELD_POST = 'wpcpm_sreport_post';

	/** The report a link names, in its query string. */
	const ARG_REPORT = 'report';

	/** The query argument that opens a report in its editor, on the Reports tab: `?wpcpm_sreport=<id>`. */
	const ARG_EDITOR = 'wpcpm_sreport';

	/** The longest name a file came with that is kept for a manager to read. Never used on disk or in a header. */
	const MAX_FILENAME = 200;

	/**
	 * Hooks.
	 *
	 * The two links have a `nopriv` arm, and it refuses a stranger like every other route: without
	 * one, a member whose session ended with the Sponsor Dashboard open would press a link and meet
	 * admin-post.php's empty 400 page instead of the login form.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_UPLOAD, array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( __CLASS__, 'handle_remove' ) );
		add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_' . self::ACTION_PRINT, array( __CLASS__, 'handle_print' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_PRINT, array( __CLASS__, 'handle_print' ) );
	}

	/**
	 * The two forms' outcomes, in a program manager's words, on the Sponsors screen's channel.
	 *
	 * `WPCPM_Sponsors::messages()` merges this map, as it merges the agreement's: the forms are
	 * pressed on a report the Sponsors screen draws, and land back on it.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice type and sentence.
	 */
	public static function manager_messages() {
		$max_mb = max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) );

		return array(
			'sreport-pdf-attached'  => array( 'success', __( 'The PDF is attached to the report.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-replaced'  => array( 'success', __( 'The PDF is replaced, and the file it replaced is deleted.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-removed'   => array( 'success', __( 'The PDF is removed from the report and deleted.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-too-big'   => array(
				'error',
				sprintf(
					/* translators: %s: the largest upload the site accepts, in megabytes. */
					__( 'Nothing was attached. The file is larger than the %s MB this site accepts.', 'wpcredits-program-manager' ),
					number_format_i18n( $max_mb )
				),
			),
			'sreport-pdf-no-file'   => array( 'error', __( 'Nothing was attached. No file arrived with the form. Choose the PDF and press the button again.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-not-pdf'   => array( 'error', __( 'Nothing was attached. That file is not a PDF. Save or export the report as a PDF and attach it again.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-encrypted' => array( 'error', __( 'Nothing was attached. That PDF is password protected, so the sponsor could not open it. Save an unprotected copy and attach that instead.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-launch'    => array( 'error', __( 'Nothing was attached. That PDF asks to run a program when it is opened, which a report has no reason to do. Print it to a new PDF and attach that instead.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-file'      => array( 'error', __( 'Nothing was attached, and the report keeps what it had. The file passed every check and this site could not store it. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-none'      => array( 'info', __( 'Nothing was removed. That report has no PDF.', 'wpcredits-program-manager' ) ),
			'sreport-pdf-gone'      => array( 'error', __( 'Nothing was changed. That report is not on this site anymore.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * The two forms
	 * --------------------------------------------------------------------
	 */

	/**
	 * Attach a PDF to a report, or replace the one it has.
	 *
	 * The agreement upload's order, with the program's capability in place of the sponsor's claim:
	 * the capability, the nonce keyed to the report, the report, then the file, its size before a
	 * byte is read, its name, its bytes, the scan, and only then the store. A final report takes a
	 * PDF like a draft: it is marked final, printed, and then attached. A replaced file is forgotten
	 * only once the new record is written, never before: a store that fails, or a record the
	 * database refuses, leaves the report the PDF it had, and at no moment does the record point at
	 * bytes that are gone.
	 */
	public static function handle_upload() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_UPLOAD . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::bounce( 'sreport-pdf-gone', 0 );
		}

		$file    = self::uploaded_file();
		$maximum = self::upload_bytes();

		if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] || $file['size'] > $maximum ) {
			self::bounce( 'sreport-pdf-too-big', $post_id );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
			self::bounce( 'sreport-pdf-no-file', $post_id );
		}

		// The size on disk decides, because it is the one about to be read into this process.
		if ( self::disk_size( $file['tmp_name'] ) > $maximum ) {
			self::bounce( 'sreport-pdf-too-big', $post_id );
		}

		// The name first, then the bytes, which are what a renamed executable fails.
		if ( ! WPCPM_Pdf_Check::named_pdf( $file['tmp_name'], $file['name'] ) ) {
			self::bounce( 'sreport-pdf-not-pdf', $post_id );
		}

		$bytes = self::file_bytes( $file['tmp_name'] );

		if ( ! WPCPM_Pdf_Check::has_magic( $bytes ) || ! WPCPM_Pdf_Check::mime_agrees( WPCPM_Pdf_Check::mime_of( $file['tmp_name'] ) ) ) {
			self::bounce( 'sreport-pdf-not-pdf', $post_id );
		}

		$scan = WPCPM_Pdf_Check::inspect( $bytes );

		if ( empty( $scan['ok'] ) ) {
			self::bounce( self::scan_outcome( isset( $scan['reason'] ) ? (string) $scan['reason'] : '' ), $post_id );
		}

		$stored = WPCPM_Private_Files::store( $bytes, 'pdf' );

		if ( is_wp_error( $stored ) ) {
			self::bounce( 'sreport-pdf-file', $post_id );
		}

		$record = array(
			'path'   => (string) $stored['path'],
			'sha256' => (string) $stored['sha256'],
			'size'   => (int) $stored['size'],
		);
		$old    = self::file_of( $post );

		// The store names every file afresh, so the record always changes and a false answer is a
		// write the database refused: the new file goes, and the report keeps the PDF it had.
		if ( false === update_post_meta( $post_id, WPCPM_Sponsor_Report::META_FILE, wp_slash( $record ) ) ) {
			WPCPM_Private_Files::forget( $record['path'] );
			self::bounce( 'sreport-pdf-file', $post_id );
		}

		update_post_meta( $post_id, WPCPM_Sponsor_Report::META_FILE_NAME, wp_slash( $file['name'] ) );

		if ( null !== $old && $old['path'] !== $record['path'] ) {
			WPCPM_Private_Files::forget( $old['path'] );
		}

		$until = self::day( WPCPM_Sponsor_Report::until_of( $post ) );

		self::audit(
			$post,
			null === $old
				/* translators: %s: the last day of the sponsor's term, in the site's date format. */
				? sprintf( __( 'A PDF was attached to the sponsor report for the term ending %s.', 'wpcredits-program-manager' ), $until )
				/* translators: %s: the last day of the sponsor's term, in the site's date format. */
				: sprintf( __( 'The PDF of the sponsor report for the term ending %s was replaced, and the file it replaced was deleted.', 'wpcredits-program-manager' ), $until ),
			$record
		);

		self::bounce( null === $old ? 'sreport-pdf-attached' : 'sreport-pdf-replaced', $post_id );
	}

	/**
	 * Remove a report's PDF: the file first, then both keys whether or not the store reported the
	 * file gone, since a record pointing at bytes that are not there is what would offer a download
	 * that fails.
	 */
	public static function handle_remove() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_REMOVE . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			self::bounce( 'sreport-pdf-gone', 0 );
		}

		$file = self::file_of( $post );

		if ( null === $file ) {
			self::bounce( 'sreport-pdf-none', $post_id );
		}

		WPCPM_Private_Files::forget( $file['path'] );
		delete_post_meta( $post_id, WPCPM_Sponsor_Report::META_FILE );
		delete_post_meta( $post_id, WPCPM_Sponsor_Report::META_FILE_NAME );

		self::audit(
			$post,
			sprintf(
				/* translators: %s: the last day of the sponsor's term, in the site's date format. */
				__( 'The PDF of the sponsor report for the term ending %s was removed and deleted.', 'wpcredits-program-manager' ),
				self::day( WPCPM_Sponsor_Report::until_of( $post ) )
			),
			$file
		);

		self::bounce( 'sreport-pdf-removed', $post_id );
	}

	/*
	 * --------------------------------------------------------------------
	 * The two links
	 * --------------------------------------------------------------------
	 */

	/**
	 * Hand a report's PDF to somebody entitled to it.
	 *
	 * The agreement download's order and its headers: signed in, the nonce keyed to the report,
	 * the report, the reader, the file. The nonce comes before anything is looked up: answered 404
	 * for a post that is not a report and 403 for another sponsor's, a request without it would tell
	 * a signed-in stranger which ids are reports, and how many there are. Never inline: a PDF has a
	 * scripting model, and a reader's browser should hand the file to a viewer of their choosing
	 * rather than open it in the tab their session lives in.
	 */
	public static function handle_download() {
		if ( ! is_user_logged_in() ) {
			// A member whose session ended with the Sponsor Dashboard open meets the login form rather
			// than a bare refusal, which would read as "you may not have this" to somebody who may.
			wp_safe_redirect( wp_login_url( self::return_url( self::ACTION_DOWNLOAD ) ) );
			exit;
		}

		$post_id = self::requested_report();

		check_admin_referer( self::ACTION_DOWNLOAD . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			wp_die( esc_html__( 'That report is not one this site holds.', 'wpcredits-program-manager' ), 404 );
		}

		if ( ! self::may_read( $post ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$file = self::file_of( $post );

		if ( null === $file ) {
			wp_die( esc_html__( 'That report has no PDF on this site.', 'wpcredits-program-manager' ), 404 );
		}

		$bytes = WPCPM_Private_Files::read( $file['path'] );

		if ( is_wp_error( $bytes ) ) {
			wp_die( esc_html__( 'That PDF could not be read. An Administrator can attach it again.', 'wpcredits-program-manager' ), 500 );
		}

		self::send(
			array(
				'Content-Type: application/pdf',
				'Content-Disposition: attachment; filename="' . self::download_name( $post ) . '"',
				'X-Content-Type-Options: nosniff',
				"Content-Security-Policy: default-src 'none'; sandbox",
				'Cache-Control: private, no-store',
				'Content-Length: ' . strlen( $bytes ),
			),
			$bytes
		);
	}

	/**
	 * Print a report as a standalone document, and stop.
	 *
	 * The download's order and the download's readers, and no PDF needed: the document is drawn
	 * from the report's own figures and narratives, whether or not a manager has attached one.
	 */
	public static function handle_print() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::return_url( self::ACTION_PRINT ) ) );
			exit;
		}

		$post_id = self::requested_report();

		check_admin_referer( self::ACTION_PRINT . '_' . $post_id );

		$post = self::report( $post_id );

		if ( null === $post ) {
			wp_die( esc_html__( 'That report is not one this site holds.', 'wpcredits-program-manager' ), 404 );
		}

		if ( ! self::may_read( $post ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		self::send(
			array(
				'Content-Type: text/html; charset=utf-8',
				'X-Content-Type-Options: nosniff',
			),
			self::document( $post )
		);
	}

	/**
	 * The nonced address of a report's PDF, for the account signed in now.
	 *
	 * @param WP_Post $post The report.
	 * @return string Escaped for the page it is drawn on, as `wp_nonce_url()` answers.
	 */
	public static function download_url( WP_Post $post ) {
		return self::route_url( self::ACTION_DOWNLOAD, (int) $post->ID );
	}

	/**
	 * The nonced address of a report's printed document, for the account signed in now: what the
	 * report's Preview and a sponsor's Open press.
	 *
	 * @param WP_Post $post The report.
	 * @return string Escaped for the page it is drawn on, as `wp_nonce_url()` answers.
	 */
	public static function print_url( WP_Post $post ) {
		return self::route_url( self::ACTION_PRINT, (int) $post->ID );
	}

	/**
	 * The PDF a report records, or null when it has none.
	 *
	 * @param WP_Post $post The report.
	 * @return array{path: string, sha256: string, size: int}|null
	 */
	public static function file_of( WP_Post $post ) {
		if ( WPCPM_Sponsor_Report::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$file = get_post_meta( (int) $post->ID, WPCPM_Sponsor_Report::META_FILE, true );

		if ( ! is_array( $file ) || empty( $file['path'] ) || ! is_scalar( $file['path'] ) ) {
			return null;
		}

		return array(
			'path'   => (string) $file['path'],
			'sha256' => isset( $file['sha256'] ) && is_scalar( $file['sha256'] ) ? (string) $file['sha256'] : '',
			'size'   => isset( $file['size'] ) ? (int) $file['size'] : 0,
		);
	}

	/**
	 * The name a report's PDF is downloaded under: `wordpress-credits-report-<slug>-<until>.pdf`.
	 *
	 * The sponsor's slug and the period's last day, from what the site holds, and never the name
	 * the file came with. Core's cleaner has the last word, so a slug core stored percent-encoded
	 * reaches the header without its percent signs; a sponsor the store no longer holds leaves the
	 * day alone.
	 *
	 * @param WP_Post $post The report.
	 * @return string
	 */
	public static function download_name( WP_Post $post ) {
		$row   = WPCPM_Sponsors_Index::row( WPCPM_Sponsor_Report::sponsor_of( $post ) );
		$slug  = is_array( $row ) && isset( $row['slug'] ) && is_scalar( $row['slug'] ) ? trim( (string) $row['slug'] ) : '';
		$parts = array( 'wordpress-credits-report', $slug, WPCPM_Sponsor_Report::until_of( $post ) );

		return sanitize_file_name( implode( '-', array_filter( $parts, 'strlen' ) ) . '.pdf' );
	}

	/*
	 * --------------------------------------------------------------------
	 * The document
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report as one standalone HTML document, to print or to save as a PDF.
	 *
	 * No theme part, no `wp_head()`, no enqueue pass: it is echoed from admin-post.php with the
	 * semester report's stylesheet inlined, as the semester report is, since a theme's header on a
	 * report a company files would put this site's navigation on its first page. Public, so that a
	 * report's Preview can show exactly what the route prints.
	 *
	 * @param WP_Post $post The report.
	 * @return string A complete HTML document; every value in it is escaped.
	 */
	public static function document( WP_Post $post ) {
		$title = (string) $post->post_title;

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8" />' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";

		// One company's figures and the mentors it sponsors, for one reader: nothing of it belongs
		// in an index.
		$html .= '<meta name="robots" content="noindex, nofollow" />' . "\n";

		// The browser proposes the title as the name of the PDF it saves.
		$html .= '<title>' . esc_html( $title ) . '</title>' . "\n";
		$html .= '<style>' . "\n" . self::stylesheet() . "\n" . '</style>' . "\n";
		$html .= '</head>' . "\n";
		// The semester report's body class, for every rule the two documents share, and this one's
		// own, for the rules only it needs (`assets/css/report-print.css`).
		$html .= '<body class="wpcpm-report-print wpcpm-report-print--sponsor">' . "\n";
		$html .= self::article( $post, $title ) . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Echoed from admin-post.php with no theme, no `wp_head()` and no `wp_footer()`: there is no enqueue pass to print a handle, and the URL is the one the semester report's print script is registered under.
		$html .= '<script src="' . esc_url( WPCPM_Semester_Report_Screen::script_url() ) . '"></script>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * The report itself: the heading, the period, the sections in their order, the day the
	 * figures were read.
	 *
	 * @param WP_Post $post  The report.
	 * @param string  $title Its title, the heading.
	 * @return string
	 */
	private static function article( WP_Post $post, $title ) {
		$snapshot = WPCPM_Sponsor_Report::snapshot( $post );

		$out  = '<article class="wpcpm-report-doc">';
		$out .= '<h1 class="wpcpm-report-doc__title">' . esc_html( $title ) . '</h1>';

		// A snapshot at a version this code does not know would be a document with the wrong things
		// in it, so the page says it cannot be read and draws nothing else.
		if ( empty( $snapshot ) ) {
			$out .= '<p class="wpcpm-report-doc__unreadable">' . esc_html__( 'This report\'s figures cannot be read, so the report is not shown. An Administrator can generate it again.', 'wpcredits-program-manager' ) . '</p>';

			return $out . '</article>';
		}

		$out .= '<p class="wpcpm-report-doc__cohort">' . esc_html(
			sprintf(
				/* translators: 1: the first day of the report's period, 2: its last day, both in the site's date format. */
				__( 'The twelve months from %1$s to %2$s', 'wpcredits-program-manager' ),
				self::day( $snapshot['from'] ),
				self::day( $snapshot['until'] )
			)
		) . '</p>';

		$narratives = WPCPM_Sponsor_Report::narratives( $post );

		foreach ( WPCPM_Sponsor_Report::sections_for( $snapshot['sponsorship']['tier'], $snapshot['tracks'] ) as $key => $section ) {
			$out .= '<section class="wpcpm-report-doc__section wpcpm-report-doc__section--' . esc_attr( sanitize_html_class( (string) $key ) ) . '">';
			$out .= '<h2 class="wpcpm-report-doc__heading">' . esc_html( $section['title'] ) . '</h2>';
			$out .= self::section_body( (string) $key, $section, $snapshot, $narratives );
			$out .= '</section>';
		}

		if ( '' !== $snapshot['as_of'] ) {
			$out .= '<p class="wpcpm-report-doc__read">' . esc_html(
				sprintf(
					/* translators: %s: the day the report's figures were read, in the site's date format. */
					__( 'These figures were read from the program\'s records on %s.', 'wpcredits-program-manager' ),
					self::day( $snapshot['as_of'] )
				)
			) . '</p>';
		}

		return $out . '</article>';
	}

	/**
	 * What one section draws under its heading: its figures, its narrative, or both once for each
	 * track it is drawn for.
	 *
	 * @param string $key        The section's key.
	 * @param array  $section    As `WPCPM_Sponsor_Report::sections_for()` answers it.
	 * @param array  $snapshot   As `WPCPM_Sponsor_Report::snapshot()` answers it.
	 * @param array  $narratives As `WPCPM_Sponsor_Report::narratives()` answers them.
	 * @return string
	 */
	private static function section_body( $key, array $section, array $snapshot, array $narratives ) {
		if ( WPCPM_Sponsor_Report::SECTION_PROGRAM === $key ) {
			return self::figures( __( 'The program in figures', 'wpcredits-program-manager' ), $snapshot['program'], WPCPM_Sponsor_Report_Numbers::COUNTS );
		}

		if ( WPCPM_Sponsor_Report::SECTION_SPONSORSHIP === $key ) {
			return self::sponsorship( $snapshot['sponsorship'] );
		}

		if ( ! empty( $section['per_track'] ) ) {
			$figures = array();

			foreach ( $snapshot['tracks'] as $track ) {
				$figures[ $track['key'] ] = $track;
			}

			$out = '';

			foreach ( $section['per_track'] as $track => $label ) {
				$out .= '<h3 class="wpcpm-report-doc__subheading">' . esc_html( $label ) . '</h3>';

				if ( isset( $figures[ $track ] ) ) {
					/* translators: %s: a track's name. */
					$out .= self::figures( sprintf( __( '%s in figures', 'wpcredits-program-manager' ), $label ), $figures[ $track ], WPCPM_Sponsor_Report_Numbers::TRACK_COUNTS );
				}

				$narrative = $key . '_' . $track;
				$out      .= self::narrative( isset( $narratives[ $narrative ] ) ? $narratives[ $narrative ] : '' );
			}

			return $out;
		}

		if ( ! empty( $section['narrative'] ) ) {
			return self::narrative( isset( $narratives[ $key ] ) ? $narratives[ $key ] : '' );
		}

		return '';
	}

	/**
	 * The sponsorship: the brief (tier, term, what it funded, posts, events), the events held in the
	 * term, the mentors, and the claims by calendar month.
	 *
	 * @param array $sponsorship The snapshot's sponsorship.
	 * @return string
	 */
	private static function sponsorship( array $sponsorship ) {
		$rows = self::sponsorship_rows( $sponsorship );
		$out  = self::table( __( 'The sponsorship in brief', 'wpcredits-program-manager' ), array(), $rows['brief'] );

		// Only when one was held, and no sentence in its place: a tier invited to no event would read
		// "none held" as a reproach, and a report drafted before its event takes place would say it
		// of an event still to come.
		if ( ! empty( $rows['events']['rows'] ) ) {
			$out .= self::table( __( 'Events held in this term', 'wpcredits-program-manager' ), $rows['events']['head'], $rows['events']['rows'] );
		}

		if ( empty( $rows['mentors']['rows'] ) ) {
			$out .= self::note( __( 'No mentor was linked to your sponsorship when this report was generated.', 'wpcredits-program-manager' ) );
		} else {
			$out .= self::table( __( 'The mentors you sponsored, and the students who started with each this year', 'wpcredits-program-manager' ), $rows['mentors']['head'], $rows['mentors']['rows'] );
		}

		// The snapshot lists an offer only when it drew a claim in the period, so no offer is a
		// period in which none was claimed, said in a sentence rather than a table of zeros.
		if ( empty( $rows['claims']['rows'] ) ) {
			return $out . self::note( __( 'None of your offers was claimed in these months.', 'wpcredits-program-manager' ) );
		}

		// By calendar month, and said so: a claim is dated to its month and nothing finer, so the
		// first and the last month of a period that does not start on the first are counted whole.
		return $out . self::table( __( 'Offers and claims by calendar month', 'wpcredits-program-manager' ), $rows['claims']['head'], $rows['claims']['rows'] );
	}

	/**
	 * The sponsorship's figures as the rows of its four tables, each cell as this document prints
	 * it: the brief, the events held, the mentors, and the claims by month.
	 *
	 * Public for the Sponsors screen's editor, which draws the same rows under captions of its own, so
	 * the manager reads the sponsorship as the sponsor will. Which rows there are is decided here:
	 * the brief names what it funded only when the snapshot holds a line, which it does from Backer
	 * up, the ladder's `funded_line` rule, and the events only when the tier includes any. An event held is its
	 * day, its title and how many people were on it, none of whom the snapshot names.
	 *
	 * @param array $sponsorship The snapshot's sponsorship.
	 * @return array{brief: array[], events: array{head: string[], rows: array[]}, mentors: array{head: string[], rows: array[]}, claims: array{head: string[], rows: array[]}}
	 *         Each row a list of cells, a profile as `array( 'link' => $url )`; the events' rows empty
	 *         when none was held, the mentors' rows empty when no mentor was linked, the claims' head
	 *         and rows empty when no offer drew a claim in the period.
	 */
	public static function sponsorship_rows( array $sponsorship ) {
		$brief = array(
			array( __( 'Tier', 'wpcredits-program-manager' ), $sponsorship['tier_label'] ),
			array(
				__( 'Term', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the first day of the sponsor's term, 2: its last day, both in the site's date format. */
					__( '%1$s to %2$s', 'wpcredits-program-manager' ),
					self::day( $sponsorship['from'] ),
					self::day( $sponsorship['until'] )
				),
			),
		);

		// The snapshot holds a funded line only for a tier that shows one, the ladder's funded_line rule.
		if ( '' !== $sponsorship['funded_line'] ) {
			$brief[] = array( __( 'What it funded', 'wpcredits-program-manager' ), $sponsorship['funded_line'] );
		}

		$brief[] = array( __( 'Posts published', 'wpcredits-program-manager' ), number_format_i18n( $sponsorship['posts'] ) );

		$events = array_values( array_filter( array_map( array( 'WPCPM_Sponsor_Tiers', 'event_label' ), $sponsorship['events'] ), 'strlen' ) );

		if ( ! empty( $events ) ) {
			/* translators: what separates two events in the list of the events a tier includes. */
			$brief[] = array( __( 'Events included', 'wpcredits-program-manager' ), implode( __( '; ', 'wpcredits-program-manager' ), $events ) );
		}

		$held = array();

		foreach ( $sponsorship['events_held'] as $event ) {
			$held[] = array( self::day_at( $event['start'] ), $event['title'], number_format_i18n( $event['joined'] ) );
		}

		$mentors = array();

		foreach ( $sponsorship['mentors'] as $mentor ) {
			$mentors[] = array( $mentor['name'], array( 'link' => $mentor['profile'] ), number_format_i18n( $mentor['students'] ) );
		}

		$head   = array();
		$claims = array();

		if ( ! empty( $sponsorship['offers'] ) ) {
			$head = array( __( 'Month', 'wpcredits-program-manager' ) );

			foreach ( $sponsorship['offers'] as $offer ) {
				$head[] = '' !== $offer['title'] ? $offer['title'] : __( 'An untitled offer', 'wpcredits-program-manager' );
			}

			$head[] = __( 'All offers', 'wpcredits-program-manager' );

			foreach ( $sponsorship['months'] as $month => $count ) {
				$row = array( self::month( (string) $month ) );

				foreach ( $sponsorship['offers'] as $offer ) {
					$row[] = number_format_i18n( isset( $offer['series'][ $month ] ) ? (int) $offer['series'][ $month ] : 0 );
				}

				$row[]    = number_format_i18n( (int) $count );
				$claims[] = $row;
			}
		}

		return array(
			'brief'   => $brief,
			'events'  => array(
				'head' => array( __( 'Date', 'wpcredits-program-manager' ), __( 'Event', 'wpcredits-program-manager' ), __( 'Joined', 'wpcredits-program-manager' ) ),
				'rows' => $held,
			),
			'mentors' => array(
				// The caption says what the count is, so its column's heading can be one word: the last
				// column of three is the narrow one, and a long heading there stands a word to a line.
				'head' => array( __( 'Mentor', 'wpcredits-program-manager' ), __( 'WordPress.org profile', 'wpcredits-program-manager' ), __( 'Students', 'wpcredits-program-manager' ) ),
				'rows' => $mentors,
			),
			'claims'  => array(
				'head' => $head,
				'rows' => $claims,
			),
		);
	}

	/**
	 * A table of counts, one labeled row a figure, in the order the list names them.
	 *
	 * @param string   $caption The table's caption.
	 * @param array    $counts  The figures, keyed as `WPCPM_Sponsor_Report_Numbers::COUNTS` keys them.
	 * @param string[] $keys    Which, in order: `COUNTS` or `TRACK_COUNTS`.
	 * @return string
	 */
	private static function figures( $caption, array $counts, array $keys ) {
		return self::table( $caption, array(), self::figure_rows( $counts, $keys ) );
	}

	/**
	 * The report's figures as the rows of a table: each figure's name and its value, both as this
	 * document prints them.
	 *
	 * Public for the Sponsors screen's editor, which draws the same rows, so a program manager writing
	 * the narratives reads each figure named and counted as the sponsor will read it.
	 *
	 * @param array    $counts The figures, keyed as `WPCPM_Sponsor_Report_Numbers::COUNTS` keys them.
	 * @param string[] $keys   Which, in order: `COUNTS` or `TRACK_COUNTS`.
	 * @return array[] One a figure, in the list's order: its name, then its value, both text.
	 */
	public static function figure_rows( array $counts, array $keys ) {
		$labels = array(
			'started'       => __( 'Students who started this year', 'wpcredits-program-manager' ),
			'finished'      => __( 'Students who finished this year', 'wpcredits-program-manager' ),
			'active'        => __( 'Students active this year', 'wpcredits-program-manager' ),
			// Each active student's hours to date, not the hours logged inside the period: the label
			// says so, as the numbers' own rule does, so a reader does not take it for the year's.
			'hours'         => __( 'Hours logged to date by the students active this year', 'wpcredits-program-manager' ),
			'contributions' => __( 'Students active this year who contributed to a WordPress team', 'wpcredits-program-manager' ),
			'teams'         => __( 'WordPress teams they contributed to', 'wpcredits-program-manager' ),
			'institutions'  => __( 'Institutions the students active this year study at', 'wpcredits-program-manager' ),
			'countries'     => __( 'Countries those institutions are in', 'wpcredits-program-manager' ),
		);
		$rows   = array();

		foreach ( $keys as $figure ) {
			$value  = isset( $counts[ $figure ] ) ? $counts[ $figure ] : 0;
			$rows[] = array(
				isset( $labels[ $figure ] ) ? $labels[ $figure ] : (string) $figure,
				'hours' === $figure ? WPCPM_Sponsor_Report_Numbers::format_hours( $value ) : number_format_i18n( (int) $value ),
			);
		}

		return $rows;
	}

	/**
	 * One table: a caption, a header row when there are columns to name, and rows whose first cell
	 * names the row.
	 *
	 * Every cell is text and is escaped here, but a link, given as `array( 'link' => $url )`, which
	 * prints its own address as its text: a report is read on paper, where a link that says "here"
	 * is gone.
	 *
	 * @param string $caption The caption.
	 * @param array  $head    The columns' names, or none.
	 * @param array  $rows    Each a list of cells.
	 * @return string
	 */
	private static function table( $caption, array $head, array $rows ) {
		$out = '<table class="wpcpm-report-doc__table"><caption>' . esc_html( $caption ) . '</caption>';

		if ( ! empty( $head ) ) {
			$out .= '<thead><tr>';

			foreach ( $head as $name ) {
				$out .= '<th scope="col">' . esc_html( $name ) . '</th>';
			}

			$out .= '</tr></thead>';
		}

		$out .= '<tbody>';

		foreach ( $rows as $cells ) {
			$out .= '<tr>';

			foreach ( array_values( $cells ) as $index => $cell ) {
				$out .= 0 === $index ? '<th scope="row">' . self::cell( $cell ) . '</th>' : '<td>' . self::cell( $cell ) . '</td>';
			}

			$out .= '</tr>';
		}

		return $out . '</tbody></table>';
	}

	/**
	 * One cell's markup: its text escaped, or a link whose text is its address.
	 *
	 * @param mixed $cell A string, or `array( 'link' => $url )`.
	 * @return string
	 */
	private static function cell( $cell ) {
		if ( is_array( $cell ) ) {
			$url = isset( $cell['link'] ) && is_scalar( $cell['link'] ) ? (string) $cell['link'] : '';

			return '' === $url ? '' : '<a class="wpcpm-report-doc__link" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
		}

		return esc_html( is_scalar( $cell ) ? (string) $cell : '' );
	}

	/**
	 * A manager's narrative as paragraphs, or the sentence that says nothing is written yet.
	 *
	 * Escaped first and paragraphed after: a narrative is plain text typed into a box, so the worst
	 * a pasted tag can do is print itself. The class is the semester report's paragraph class, which
	 * lets a pasted address wrap rather than run off the page.
	 *
	 * @param string $text The narrative.
	 * @return string
	 */
	private static function narrative( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return self::note( __( 'Nothing written yet.', 'wpcredits-program-manager' ) );
		}

		return str_replace( '<p>', '<p class="wpcpm-report-doc__p">', wpautop( esc_html( $text ) ) );
	}

	/**
	 * One quiet sentence.
	 *
	 * @param string $text The sentence.
	 * @return string
	 */
	private static function note( $text ) {
		return '<p class="wpcpm-report-doc__note">' . esc_html( $text ) . '</p>';
	}

	/**
	 * A `Y-m-d` day as the site writes one, or '' for a value that is not a day.
	 *
	 * @param string $ymd A day.
	 * @return string
	 */
	private static function day( $ymd ) {
		return WPCPM_Sponsor_Tiers::is_date( $ymd ) ? WPCPM_Sponsor_Tiers::format_day( $ymd ) : '';
	}

	/**
	 * The day a moment falls on, on the site's clock, as the site writes a day: an event held is placed
	 * in a term by its end's day on that clock, so the day printed beside it, its start's, is read on
	 * the same one, whatever clock its planner typed it on.
	 *
	 * @param int $timestamp A UTC timestamp.
	 * @return string '' for no moment.
	 */
	private static function day_at( $timestamp ) {
		return (int) $timestamp > 0 ? (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $timestamp ) : '';
	}

	/**
	 * A `Y-m` month as the site names one, read at noon in UTC on its first day so that no timezone
	 * moves it into its neighbor.
	 *
	 * @param string $key `Y-m`.
	 * @return string The key itself when it is not a month.
	 */
	private static function month( $key ) {
		if ( 1 !== preg_match( '/\A\d{4}-\d{2}\z/', $key ) || ! WPCPM_Sponsor_Tiers::is_date( $key . '-01' ) ) {
			return $key;
		}

		return (string) wp_date( 'F Y', (int) strtotime( $key . '-01 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The semester report's stylesheet, ready to inline.
	 *
	 * Read off disk, so that the semester report and this document are dressed by one file, and
	 * dropped whole if it ever carries the two characters that end a tag, in that order, which
	 * would close the style element early and print the rest of the file as text: an unstyled
	 * report is still the whole report, and a page that broke halfway down is not.
	 *
	 * @return string
	 */
	private static function stylesheet() {
		$path = WPCPM_PLUGIN_DIR . WPCPM_Semester_Report_Screen::STYLE_FILE;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The plugin's own stylesheet, read by an absolute path inside its own directory; WP_Filesystem would ask for credentials on a request that has none to give.
		$css = (string) file_get_contents( $path );

		if ( false !== strpos( $css, '</' ) ) {
			return '';
		}

		return trim( $css );
	}

	/*
	 * --------------------------------------------------------------------
	 * Internals
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the account signed in now may read a report: a manager always; a member of the
	 * report's sponsor once it is final and while the tiers are live.
	 *
	 * The subject is the report's own sponsor stamp, never a request's: a member of one sponsor
	 * asking for another's report is exactly what a request would carry.
	 *
	 * @param WP_Post $post The report.
	 * @return bool
	 */
	private static function may_read( WP_Post $post ) {
		$decision = WPCPM_Sponsor_Policy::decide(
			WPCPM_Sponsor_Policy::ACT_VIEW_REPORT,
			WPCPM_Sponsor_Policy::subject_sponsor( WPCPM_Sponsor_Report::sponsor_of( $post ) )
		);

		if ( empty( $decision['allowed'] ) ) {
			return false;
		}

		if ( isset( $decision['ground'] ) && WPCPM_Sponsor_Policy::GROUND_MANAGER === $decision['ground'] ) {
			return true;
		}

		return WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post ) && WPCPM_Sponsor_Tiers::live();
	}

	/**
	 * Send one response and stop: the bytes somebody left in a buffer out of the way, the headers
	 * WordPress sends to keep a page out of every cache, then the response's own, then its body.
	 *
	 * The badge route's rule for buffers. Bytes a theme or a plugin left in an open buffer would go
	 * out ahead of the body, past a PDF's own Content-Length or ahead of the document's doctype, so a
	 * buffer that holds any is emptied and closed. The first empty buffer stops the loop, and it and
	 * every buffer outside it stay open: a buffer holding nothing belongs to a handler, compression or
	 * a cache, that has to see the response go by. A buffer that refuses to go ends the loop rather
	 * than holding the request in it. Never cached: a report is one company's, built for one reader,
	 * and a shared cache holding it would hand it to the next.
	 *
	 * @param string[] $headers Whole header lines.
	 * @param string   $body    A PDF's bytes, or a document `document()` built.
	 */
	private static function send( array $headers, $body ) {
		while ( ob_get_level() > 0 && ob_get_length() > 0 ) {
			if ( ! ob_end_clean() ) {
				break;
			}
		}

		nocache_headers();

		foreach ( $headers as $header ) {
			header( $header );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A PDF's bytes under their own Content-Type, which escaping would corrupt, or a whole HTML document document() built from escaped parts.
		echo $body;
		exit;
	}

	/**
	 * A report by its ID, or null: a post that is not a report, is not in the store's own status,
	 * or is not there at all.
	 *
	 * @param int $post_id A post ID.
	 * @return WP_Post|null
	 */
	private static function report( $post_id ) {
		$post = (int) $post_id > 0 ? get_post( (int) $post_id ) : null;

		if ( ! $post instanceof WP_Post || WPCPM_Sponsor_Report::POST_TYPE !== $post->post_type || 'private' !== $post->post_status ) {
			return null;
		}

		return $post;
	}

	/**
	 * The nonced admin-post address of one route for one report.
	 *
	 * @param string $action  The route.
	 * @param int    $post_id The report.
	 * @return string
	 */
	private static function route_url( $action, $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'         => $action,
					self::ARG_REPORT => (int) $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . (int) $post_id
		);
	}

	/**
	 * Which report a link names.
	 *
	 * The two links are `wp_nonce_url()` links rather than forms, so their arguments arrive in the
	 * query string, and they are read here, apart from the handlers, for the reason
	 * `bin/check-references.php` records.
	 *
	 * @return int
	 */
	private static function requested_report() {
		return WPCPM_Request::id( self::ARG_REPORT );
	}

	/**
	 * The link a signed-out request was following, as the login form's way back: the house's pattern
	 * for a signed-out reader.
	 *
	 * The way back cannot open the report itself. A nonce belongs to the session that drew the link,
	 * so once signed in again the link meets core's expired-link page, and a member opens the report
	 * from the Sponsor Dashboard's card, which draws a fresh one. Rebuilt from the three arguments the
	 * route takes rather than read out of `REQUEST_URI`, so the host in it is this site's and not a
	 * header somebody sent.
	 *
	 * @param string $action The route.
	 * @return string
	 */
	private static function return_url( $action ) {
		return add_query_arg(
			array(
				'action'         => $action,
				self::ARG_REPORT => self::requested_report(),
				'_wpnonce'       => WPCPM_Request::key( '_wpnonce' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Back to the report's editor, where both forms are pressed, with the outcome flashed on the
	 * Sponsors screen's channel: the Reports tab's view of one report, at the address the report
	 * screen builds for it (`WPCPM_Sponsor_Report_Screen::editor_url()`); to the Reports tab itself
	 * when there is no report to go back to.
	 *
	 * @param string $status  A key of `manager_messages()`.
	 * @param int    $post_id The report, or 0.
	 */
	private static function bounce( $status, $post_id ) {
		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $status );

		wp_safe_redirect( (int) $post_id > 0 ? WPCPM_Sponsor_Report_Screen::editor_url( (int) $post_id ) : admin_url( 'admin.php?page=wpcpm-sponsors&tab=reports' ) );
		exit;
	}

	/**
	 * One row on the sponsors module's trail about a report's PDF: its size and its hash, and never
	 * the name it came with, which is somebody's choice and no fact about the file.
	 *
	 * @param WP_Post $post    The report.
	 * @param string  $message One sentence.
	 * @param array   $file    The file attached or removed: `sha256` and `size`.
	 */
	private static function audit( WP_Post $post, $message, array $file ) {
		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => WPCPM_Sponsor_Report::LOG_PDF,
				'sponsor'  => WPCPM_Sponsor_Report::sponsor_of( $post ),
				'subject'  => (string) $post->ID,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $message,
				'data'     => array(
					'size'   => (int) $file['size'],
					'sha256' => (string) $file['sha256'],
				),
			)
		);
	}

	/**
	 * The outcome one of the courtesy scan's two refusals flashes here, looked up by the PDF name
	 * the scanner refused, so the scanner's own slugs, which are the agreement's words, stay its.
	 *
	 * @param string $reason What `WPCPM_Pdf_Check::inspect()` gave as its reason.
	 * @return string A key of `manager_messages()`.
	 */
	private static function scan_outcome( $reason ) {
		$outcomes = array(
			'/Encrypt' => 'sreport-pdf-encrypted',
			'/Launch'  => 'sreport-pdf-launch',
		);

		foreach ( WPCPM_Pdf_Check::SCAN_REFUSALS as $name => $slug ) {
			if ( $slug === $reason && isset( $outcomes[ $name ] ) ) {
				return $outcomes[ $name ];
			}
		}

		return 'sreport-pdf-not-pdf';
	}

	/**
	 * The posted file, every member typed and nothing trusted: the agreement upload's reader.
	 *
	 * The one superglobal `WPCPM_Request` cannot read a field out of is read here, once, and each
	 * member is cast on the way out. The name is cleaned and capped because it is kept for a
	 * manager to read; it is never used on disk and never put in a header.
	 *
	 * @return array{error: int, size: int, tmp_name: string, name: string}
	 */
	private static function uploaded_file() {
		$empty = array(
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
			'tmp_name' => '',
			'name'     => '',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- `handle_upload()` verifies the nonce before it calls this.
		if ( ! isset( $_FILES[ self::FIELD_FILE ] ) || ! is_array( $_FILES[ self::FIELD_FILE ] ) ) {
			return $empty;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above for the nonce; every member is cast or sanitized on the lines below, which is the only sanitizing an upload admits. What makes the bytes safe is the checks `handle_upload()` runs over them.
		$raw = wp_unslash( $_FILES[ self::FIELD_FILE ] );

		return array(
			'error'    => isset( $raw['error'] ) ? (int) $raw['error'] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $raw['size'] ) ? (int) $raw['size'] : 0,
			'tmp_name' => isset( $raw['tmp_name'] ) ? (string) $raw['tmp_name'] : '',
			'name'     => isset( $raw['name'] ) ? substr( sanitize_file_name( (string) $raw['name'] ), 0, self::MAX_FILENAME ) : '',
		);
	}

	/**
	 * Whether a path is a file PHP received on this request.
	 *
	 * `is_uploaded_file()` is what stops a posted string naming `wp-config.php` from being read out
	 * of the filesystem and stored as a report's PDF. It answers from PHP's own list, which is empty
	 * under the CLI, where the suites run and where no `admin_post_` action can fire.
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
	 * How many bytes a temporary file holds.
	 *
	 * @param string $path Temporary path.
	 * @return int
	 */
	private static function disk_size( $path ) {
		$size = filesize( $path );

		return false === $size ? 0 : (int) $size;
	}

	/**
	 * Read an uploaded temporary file into memory, once, so that the magic bytes, the scan and the
	 * store all read one copy.
	 *
	 * @param string $path Temporary path.
	 * @return string The bytes, or '' when they could not be read.
	 */
	private static function file_bytes( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP's own temporary copy of the upload, by the absolute path PHP gave; WP_Filesystem would ask for credentials this request has none to give.
		$bytes = file_get_contents( $path );

		return is_string( $bytes ) ? $bytes : '';
	}

	/**
	 * The largest PDF this site accepts, in bytes: the one bound the settings hold for a PDF, the
	 * one the agreement uploads read.
	 *
	 * @return int
	 */
	private static function upload_bytes() {
		return max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) ) * MB_IN_BYTES;
	}
}
