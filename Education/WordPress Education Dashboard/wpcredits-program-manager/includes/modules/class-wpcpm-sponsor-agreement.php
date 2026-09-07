<?php
/**
 * Sponsors module - the sponsor agreement record and the routes it travels.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A sponsor's signed agreement: uploaded, read by a program manager, accepted or returned.
 *
 * The Collaboration Agreement's shape with three things taken out (design spec of 4 September
 * 2026, decision 10 and section 8.2), and each absence is a decision rather than an omission:
 *
 * - **No template.** No agreement exists for the program to hand a sponsor, so there is no
 *   `generated` state and no `template` kind. A document arrives as `own` (uploaded here) or
 *   `legacy` (a copy the program already holds, recorded by a manager with a Drive link).
 * - **No gate.** A sponsor's dashboard works without an agreement, so nothing here reads one
 *   option on every request and nothing here opens or closes an account. `rebuild()` therefore
 *   writes the index's `agreement` block rather than a settled flag: what the screens need is
 *   that the cells the site just wrote to the base are visible before the sponsors sync reads
 *   them back.
 * - **No two-source rule.** With no gate to fail closed, the site's posts are the record of
 *   what happened here and Airtable's `Agreement Status` is the program's record; the card
 *   shows the first and the manager screen shows both.
 *
 * What is kept is everything that makes a file from outside safe to hold: the per-sponsor
 * `add_option()` lock every transition takes, the daily upload ceiling, `WPCPM_Pdf_Check` over
 * the bytes before anything is stored, `WPCPM_Private_Files` for the bytes themselves, and a
 * download that is always an attachment and never named by the uploader.
 */
final class WPCPM_Sponsor_Agreement {

	/** Seventeen characters, under core's twenty, private and invisible. */
	const POST_TYPE = 'wpcpm_sponsor_agr';

	/** The status every agreement post is stored under. */
	const POST_STATUS = 'private';

	/** Post meta: the Sponsors record the document belongs to. The queryable key. */
	const META_SPONSOR = '_wpcpm_sagr_sponsor';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_sagr_state';

	/** Post meta: one of the `KIND_*` values. */
	const META_KIND = '_wpcpm_sagr_kind';

	/** Post meta: `path`, `size`, `sha256` of the stored file; absent on a legacy row. */
	const META_FILE = '_wpcpm_sagr_file';

	/** Post meta: the uploaded file's original name, for display only and never for disk. */
	const META_ORIGINAL_NAME = '_wpcpm_sagr_original_name';

	/** Post meta: what the courtesy scan noticed in an upload. */
	const META_FLAGS = '_wpcpm_sagr_flags';

	/** Post meta: the Drive link a legacy row points at. */
	const META_DRIVE_URL = '_wpcpm_sagr_drive_url';

	/** Post meta: the date the paper copy was signed, Y-m-d. */
	const META_SIGNED_ON = '_wpcpm_sagr_signed_on';

	/** Post meta: a manager's note on a returned or revoked document. */
	const META_NOTE = '_wpcpm_sagr_note';

	/** Post meta: the user who accepted, returned or revoked. */
	const META_DECIDED_BY = '_wpcpm_sagr_decided_by';

	/** Post meta: the date of that decision, Y-m-d. */
	const META_DECIDED_AT = '_wpcpm_sagr_decided_at';

	/** Post meta: set when an Airtable write failed; the next sponsors sync run finishes it. */
	const META_AIRTABLE_PENDING = '_wpcpm_sagr_airtable_pending';

	/** Post meta, repeating: one row per event in the document's life. */
	const META_EVENT = '_wpcpm_sagr_event';

	const STATE_SUBMITTED  = 'submitted';
	const STATE_ACCEPTED   = 'accepted';
	const STATE_RETURNED   = 'returned';
	const STATE_WITHDRAWN  = 'withdrawn';
	const STATE_SUPERSEDED = 'superseded';
	const STATE_REVOKED    = 'revoked';

	const KIND_OWN    = 'own';
	const KIND_LEGACY = 'legacy';

	/** The summary states the card and a manager's row name. */
	const SUMMARY_NONE      = 'none';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';

	/**
	 * The `Agreement Status` choices, spelled as the base spells them.
	 *
	 * `update_records()` sends no `typecast`, so any other spelling is a 422 for the whole
	 * PATCH. Declared here so the spelling is asserted once, in the suite, and a renamed choice
	 * fails a test rather than a write.
	 */
	const AIRTABLE_NOT_STARTED = 'Not started';
	const AIRTABLE_AWAITING    = 'Awaiting review';
	const AIRTABLE_ACCEPTED    = 'Accepted';
	const AIRTABLE_RETURNED    = 'Returned';
	const AIRTABLE_ON_FILE     = 'On file';
	const AIRTABLE_REVOKED     = 'Revoked';

	/** The signed copy arrives here: `admin_post_`, multipart, a member or a manager on behalf. */
	const ACTION_UPLOAD = 'wpcpm_sponsor_agr_upload';

	/** The only route to a stored file's bytes. Registered for logged-out too, to send them to log in. */
	const ACTION_DOWNLOAD = 'wpcpm_sponsor_agr_download';

	/** The five a program manager presses, and the one a sponsor does. */
	const ACTION_ACCEPT    = 'wpcpm_sponsor_agr_accept';
	const ACTION_RETURN    = 'wpcpm_sponsor_agr_return';
	const ACTION_WITHDRAW  = 'wpcpm_sponsor_agr_withdraw';
	const ACTION_REVOKE    = 'wpcpm_sponsor_agr_revoke';
	const ACTION_REINSTATE = 'wpcpm_sponsor_agr_reinstate';
	const ACTION_ON_FILE   = 'wpcpm_sponsor_agr_on_file';

	/** The posted field names. */
	const FIELD_FILE   = 'wpcpm_sponsor_agr_file';
	const FIELD_POST   = 'wpcpm_sponsor_agr_post';
	const FIELD_NOTE   = 'wpcpm_sponsor_agr_note';
	const FIELD_SIGNED = 'wpcpm_sponsor_agr_signed';
	const FIELD_DRIVE  = 'wpcpm_sponsor_agr_drive';

	/** Daily: forget the files of documents nobody is waiting on any more. */
	const CRON_DISCARD = 'wpcpm_sponsor_agreement_discard';

	/** How many documents one discard query reads, and how many such queries a run makes. */
	const DISCARD_BATCH = 200;
	const DISCARD_PAGES = 25;

	/** The per-sponsor transition lock, and the option prefix uninstall sweeps. */
	const OPT_PREFIX  = 'wpcpm_sponsor_agr_';
	const LOCK_PREFIX = 'wpcpm_sponsor_agr_lock_';

	/** Seconds before a held lock is treated as abandoned. */
	const LOCK_TIMEOUT = 300;

	/** The ceiling's key stem. The limit is the `agreement_uploads_per_day` setting. */
	const CEILING = 'sponsor-agreement-upload:';

	/** Mail contexts, for the log. */
	const MAIL_RECEIVED   = 'sponsor-agreement-received';
	const MAIL_ACCEPTED   = 'sponsor-agreement-accepted';
	const MAIL_RETURNED   = 'sponsor-agreement-returned';
	const MAIL_REVOKED    = 'sponsor-agreement-revoked';
	const MAIL_REINSTATED = 'sponsor-agreement-reinstated';

	/** The words the event log prints. */
	const EVENT_UPLOADED   = 'signed copy uploaded';
	const EVENT_ACCEPTED   = 'accepted';
	const EVENT_RETURNED   = 'returned for changes';
	const EVENT_WITHDRAWN  = 'withdrawn';
	const EVENT_SUPERSEDED = 'superseded by a newer document';
	const EVENT_DISCARDED  = 'file discarded';
	const EVENT_REVOKED    = 'revoked';
	const EVENT_REINSTATED = 'reinstated';
	const EVENT_ON_FILE    = 'recorded on file';

	/** Audit kinds this class writes. */
	const LOG_UPLOAD    = 'sponsor_agreement_upload';
	const LOG_ACCEPT    = 'sponsor_agreement_accept';
	const LOG_RETURN    = 'sponsor_agreement_return';
	const LOG_WITHDRAW  = 'sponsor_agreement_withdraw';
	const LOG_REVOKE    = 'sponsor_agreement_revoke';
	const LOG_REINSTATE = 'sponsor_agreement_reinstate';
	const LOG_ON_FILE   = 'sponsor_agreement_on_file';

	/** A manager's note, in characters. */
	const MIN_NOTE = 20;
	const MAX_NOTE = 2000;

	/** Longest original filename kept for display. It is never used on disk or in a header. */
	const MAX_FILENAME = 200;

	/** The hosts a Drive link may point at. Exact, lowercase, no `www.`. */
	const DRIVE_HOSTS = array( 'drive.google.com', 'docs.google.com' );

	/**
	 * Hooks.
	 *
	 * Download is the one action with a `nopriv` arm, and it is not an exception to the rule
	 * that every action refuses a stranger: without one, a member following a link from an
	 * email that has signed them out meets `admin-post.php`'s bare `0` instead of a login form
	 * with a way back. The handler refuses a logged-out request in its first three lines; the
	 * arm exists so it can refuse it usefully.
	 *
	 * The cron is hooked in Task 5 of this phase and scheduled by `WPCPM_Sponsors::activate()`,
	 * never here: scheduling belongs to the module's activation, where every other event of
	 * this plugin's is put on the calendar.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_UPLOAD, array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_' . self::ACTION_WITHDRAW, array( __CLASS__, 'handle_withdraw' ) );
		add_action( 'admin_post_' . self::ACTION_ACCEPT, array( __CLASS__, 'handle_accept' ) );
		add_action( 'admin_post_' . self::ACTION_RETURN, array( __CLASS__, 'handle_return' ) );
		add_action( 'admin_post_' . self::ACTION_REVOKE, array( __CLASS__, 'handle_revoke' ) );
		add_action( 'admin_post_' . self::ACTION_REINSTATE, array( __CLASS__, 'handle_reinstate' ) );
		add_action( 'admin_post_' . self::ACTION_ON_FILE, array( __CLASS__, 'handle_on_file' ) );
		add_action( self::CRON_DISCARD, array( __CLASS__, 'discard' ) );
	}

	/**
	 * Register the agreement post type.
	 *
	 * Invisible everywhere by design: not public, not queryable, not in REST, not in search, no
	 * admin UI. These rows name companies and point at signed documents, and the only routes to
	 * them are the card and the manager screen, both of which ask the policy first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor agreements', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor agreement', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'author' ),
				// A capability type nothing is granted, so no role can reach these through any
				// generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_sponsor_agr', 'wpcpm_sponsor_agrs' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * This card's outcomes, in the sponsor's words.
	 *
	 * The two scan refusals are keyed by the slugs `WPCPM_Pdf_Check::SCAN_REFUSALS` names, so
	 * the scanner says what happened once and neither module translates it.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		$max_mb = max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) );

		return array(
			'agreement-uploaded'  => array( 'success', __( 'Your signed agreement is uploaded. A program manager reads it and you will get an email either way.', 'wpcredits-program-manager' ) ),
			'agreement-withdrawn' => array( 'success', __( 'The agreement is withdrawn and its file is deleted. Upload another whenever you are ready.', 'wpcredits-program-manager' ) ),
			'agreement-declare'   => array( 'error', __( 'Nothing was uploaded. Tick the box to say the document you are uploading is the signed one.', 'wpcredits-program-manager' ) ),
			'agreement-too-big'   => array(
				'error',
				sprintf(
					/* translators: %s: the largest upload the site accepts, in megabytes. */
					__( 'Nothing was uploaded. The file is empty or larger than the %s MB this site accepts. A signed agreement scanned as text is well under that; a scan of every page as a photograph is not.', 'wpcredits-program-manager' ),
					number_format_i18n( $max_mb )
				),
			),
			'agreement-not-pdf'   => array( 'error', __( 'Nothing was uploaded. That file is not a PDF. Save or export the signed agreement as a PDF and upload it again.', 'wpcredits-program-manager' ) ),
			'agreement-no-file'   => array( 'error', __( 'Nothing was uploaded. No file arrived with the form. Choose the signed PDF and press the button again.', 'wpcredits-program-manager' ) ),
			'agreement-file'      => array( 'error', __( 'Nothing was uploaded. The file passed every check and this site could not store it, which is this site\'s fault and not yours. Nothing was kept. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'agreement-encrypted' => array( 'error', __( 'Nothing was uploaded. That PDF is password protected, so a program manager could not open it. Save an unprotected copy and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-launch'    => array( 'error', __( 'Nothing was uploaded. That PDF asks to run a program when it is opened, which a signed agreement has no reason to do. Print it to a new PDF and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-in-review' => array( 'error', __( 'Nothing was uploaded. An agreement from your company is already waiting for review. Withdraw it first if it was the wrong file.', 'wpcredits-program-manager' ) ),
			'agreement-busy'      => array( 'error', __( 'Nothing was saved. Either another change to this agreement was in flight, or your company has used up the uploads one day allows. Try again in a moment, and tomorrow if it happens again.', 'wpcredits-program-manager' ) ),
			'agreement-gone'      => array( 'error', __( 'Nothing happened. That document is no longer waiting for review: somebody has accepted, returned or withdrawn it since this page was drawn. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'refused'             => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The outcomes a program manager sees, on the wp-admin Sponsors screen's channel.
	 *
	 * Kept apart from `messages()` because the two audiences are on two flash channels and read
	 * two different pages: a sponsor is told what happened to its document, a manager is told
	 * what the press did and what is left to do. `WPCPM_Sponsors::messages()` merges this map.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function manager_messages() {
		return array(
			'agreement-accepted'           => array( 'success', __( 'The agreement is accepted. Airtable says Accepted with today\'s date, and everybody at the company has been emailed.', 'wpcredits-program-manager' ) ),
			'agreement-returned'           => array( 'success', __( 'The agreement is returned. Everybody at the company has been emailed your note, with your address to reply to.', 'wpcredits-program-manager' ) ),
			'agreement-note'               => array( 'error', __( 'Nothing was returned. The note has to be between 20 and 2000 characters: it is the whole of what the company is told, so it has to say what to change.', 'wpcredits-program-manager' ) ),
			'agreement-gone'               => array( 'error', __( 'Nothing happened. That document is no longer waiting for review: somebody else has accepted, returned or withdrawn it since this page was drawn. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'agreement-busy'               => array( 'error', __( 'Nothing was saved. Another change to this company\'s agreement was in flight. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'agreement-airtable'           => array( 'error', __( 'Airtable could not be updated, so nothing was recorded here either. The base is the program\'s record of this state, and the site does not record a decision the base has not agreed to.', 'wpcredits-program-manager' ) ),
			'agreement-revoked'            => array( 'success', __( 'The agreement is out of force. The company\'s dashboard is unchanged, because it was never gated on an agreement, and everybody there has been emailed your note.', 'wpcredits-program-manager' ) ),
			'agreement-revoke-note'        => array( 'error', __( 'Nothing was revoked. A note is required, between 20 and 2000 characters: it is emailed to the company as the reason, so it has to say something.', 'wpcredits-program-manager' ) ),
			'agreement-not-accepted'       => array( 'error', __( 'Nothing was revoked. There is no accepted agreement to revoke for this company.', 'wpcredits-program-manager' ) ),
			'agreement-not-revoked'        => array( 'error', __( 'Nothing was reinstated. There is no revoked agreement to put back for this company.', 'wpcredits-program-manager' ) ),
			'agreement-reinstate-standing' => array( 'error', __( 'Nothing was reinstated. An accepted agreement already stands, so there is nothing for the revoked one to come back to.', 'wpcredits-program-manager' ) ),
			'agreement-reinstated'         => array( 'success', __( 'The agreement is back in force and everybody at the company has been emailed.', 'wpcredits-program-manager' ) ),
			'agreement-not-saved'          => array( 'error', __( 'Airtable was updated but the record on this site could not be written. Press Record on file again: the base already says On file, and a second press completes it here.', 'wpcredits-program-manager' ) ),
			'agreement-on-file'            => array( 'success', __( 'The agreement is recorded as on file, with the link to the copy in the program\'s Drive.', 'wpcredits-program-manager' ) ),
			'agreement-link'               => array( 'error', __( 'Nothing was recorded. Paste the Drive link to the folder or the file: it has to be an https address on drive.google.com or docs.google.com.', 'wpcredits-program-manager' ) ),
			'agreement-standing'           => array( 'error', __( 'Nothing was recorded. An accepted agreement already stands for this company.', 'wpcredits-program-manager' ) ),
			'agreement-unknown'            => array( 'error', __( 'Nothing was recorded. That company is not in the sponsors index; run a sync and try again.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Every agreement post for a sponsor, newest first.
	 *
	 * @param string $record Sponsors record ID.
	 * @return WP_Post[]
	 */
	public static function posts_for( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => 100,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_SPONSOR,
						'value' => $record,
					),
				),
			)
		);
	}

	/**
	 * What the card and a manager's row say about one sponsor.
	 *
	 * The site half is computed from the posts every time. There is no option to read it back
	 * from, and there does not need to be: a sponsor has at most a handful of documents and
	 * nothing on a request path asks this question.
	 *
	 * @param string $record Sponsors record ID.
	 * @return array{state: string, kind: string, accepted_at: string, agreement_id: int, pending_id: int, drive_url: string, airtable_status: string}
	 */
	public static function summary( $record ) {
		$record = trim( (string) $record );
		$site   = self::site_summary( self::posts_for( $record ) );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$block  = ( is_array( $row ) && isset( $row['agreement'] ) && is_array( $row['agreement'] ) ) ? $row['agreement'] : array();

		return array(
			'state'           => $site['site_state'],
			'kind'            => $site['kind'],
			'accepted_at'     => $site['accepted_at'],
			'agreement_id'    => $site['agreement_id'],
			'pending_id'      => $site['pending_id'],
			'drive_url'       => $site['drive_url'],
			'airtable_status' => isset( $block['status'] ) ? trim( (string) $block['status'] ) : '',
		);
	}

	/**
	 * Put the cells this site just wrote to the base into the index, under the lock.
	 *
	 * The institution class's `rebuild()` computes a gate from two sources; there is no gate
	 * here, so this is the smaller job the sponsor module actually needs: the manager screen
	 * and the card read `WPCPM_Sponsors_Index`, and a manager who accepts an agreement must not
	 * have to wait for the sponsors sync to see the base agree. Every handler calls this inside
	 * its own critical section and says so: releasing the lock and taking it again would leave
	 * a gap for another transition to write the block from state that is about to change. A
	 * caller holding no lock takes it here, and skips when a transition holds it, because that
	 * transition writes the same block itself.
	 *
	 * @param string $record   Sponsors record ID.
	 * @param array  $airtable The cells written, keyed `status`, `accepted_on`, `document`.
	 *                         A key that is absent leaves the held value alone.
	 * @param bool   $locked   Whether this sponsor's lock is already held by the caller. A
	 *                         handler passes true from inside its own critical section; the
	 *                         sponsors sync holds nothing and leaves it false.
	 * @return array The block written, or an empty array when nothing was.
	 */
	public static function rebuild( $record, array $airtable, $locked = false ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		if ( ! $locked && ! self::lock( $record ) ) {
			return array();
		}

		$row   = WPCPM_Sponsors_Index::row( $record );
		$held  = ( is_array( $row ) && isset( $row['agreement'] ) && is_array( $row['agreement'] ) ) ? $row['agreement'] : array();
		$block = array(
			'status'       => array_key_exists( 'status', $airtable )
				? trim( (string) $airtable['status'] )
				: ( isset( $held['status'] ) ? (string) $held['status'] : '' ),
			'accepted_on'  => array_key_exists( 'accepted_on', $airtable )
				? self::date_or_empty( $airtable['accepted_on'] )
				: ( isset( $held['accepted_on'] ) ? (string) $held['accepted_on'] : '' ),
			'has_document' => array_key_exists( 'document', $airtable )
				? ( '' !== trim( (string) $airtable['document'] ) )
				: ! empty( $held['has_document'] ),
		);

		WPCPM_Sponsors_Index::patch( $record, array( 'agreement' => $block ) );

		if ( ! $locked ) {
			self::unlock( $record );
		}

		return $block;
	}

	/**
	 * Every document waiting to be read, across every sponsor, oldest first.
	 *
	 * Oldest first because it is a queue somebody works through, and the company that has
	 * waited longest is the one whose turn it is.
	 *
	 * @param int $limit Most rows to read.
	 * @return int[] Post IDs.
	 */
	public static function awaiting_review( $limit = 200 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => max( 1, (int) $limit ),
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STATE,
						'value' => self::STATE_SUBMITTED,
					),
				),
			)
		);

		$ids = array();

		foreach ( (array) $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$ids[] = (int) $post->ID;
			}
		}

		return $ids;
	}

	/**
	 * Every agreement out of force, oldest revocation first: what the Administrator Dashboard
	 * offers to put back (1.96.1).
	 *
	 * @param int $limit At most this many.
	 * @return int[] Post IDs.
	 */
	public static function revoked_all( $limit = 200 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => max( 1, (int) $limit ),
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'meta_query'       => array(
					array(
						'key'   => self::META_STATE,
						'value' => self::STATE_REVOKED,
					),
				),
			)
		);

		$ids = array();

		foreach ( (array) $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$ids[] = (int) $post->ID;
			}
		}

		return $ids;
	}

	/**
	 * A manager's decision on one document, in the request cards' own shape for the
	 * Administrator Dashboard (1.96.1): Download, Accept, and a folded "Return with a note"
	 * for a document waiting for review; "Put it back in force" for one out of force. The forms
	 * post to the same handlers the wp-admin Sponsors screen uses, with the same nonces; the
	 * return field brings the manager back to this card.
	 *
	 * @param int    $post_id The document.
	 * @param string $return  `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	public static function render_decision( $post_id, $return = '' ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$state = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( ! in_array( $state, array( self::STATE_SUBMITTED, self::STATE_REVOKED ), true ) ) {
			return;
		}

		echo '<div class="wpcpm-request__decide wpcpm-sponsor-agreement__decide">';

		// Only an uploaded copy has a file to download; a legacy row is a Drive link.
		if ( self::KIND_OWN === (string) get_post_meta( $post_id, self::META_KIND, true ) ) {
			printf(
				'<a class="button" href="%1$s">%2$s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action' => self::ACTION_DOWNLOAD,
								'post'   => $post_id,
							),
							admin_url( 'admin-post.php' )
						),
						self::ACTION_DOWNLOAD . '_' . $post_id
					)
				),
				esc_html__( 'Download', 'wpcredits-program-manager' )
			);
		}

		if ( self::STATE_REVOKED === $state ) {
			self::render_decision_form( self::ACTION_REINSTATE, $post_id, __( 'Put it back in force', 'wpcredits-program-manager' ), 'button', $return );
			echo '</div>';
			return;
		}

		self::render_decision_form( self::ACTION_ACCEPT, $post_id, __( 'Accept', 'wpcredits-program-manager' ), 'button button-primary', $return );

		echo '<details class="wpcpm-sponsor-agreement__return">';
		printf( '<summary class="button">%s</summary>', esc_html__( 'Return with a note', 'wpcredits-program-manager' ) );
		printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html__( 'The document goes back to the company, and your note is emailed to everybody there, with your address to reply to.', 'wpcredits-program-manager' ) );
		printf( '<form class="wpcpm-sponsor-agreement__form wpcpm-sponsor-agreement__form--return" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_RETURN . '_' . $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_RETURN ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_POST ), (int) $post_id );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'sponsor-agreements' );
		}

		printf(
			'<label class="screen-reader-text" for="wpcpm-agr-note-%1$d">%2$s</label><textarea id="wpcpm-agr-note-%1$d" name="%3$s" rows="2" minlength="%4$d" maxlength="%5$d" required placeholder="%6$s"></textarea>',
			(int) $post_id,
			esc_html__( 'A note for the company', 'wpcredits-program-manager' ),
			esc_attr( self::FIELD_NOTE ),
			(int) self::MIN_NOTE,
			(int) self::MAX_NOTE,
			esc_attr__( 'What has to change before the program can accept it', 'wpcredits-program-manager' )
		);
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Send back with this note', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</details>';
		echo '</div>';
	}

	/**
	 * One decision form of the dashboard row: the action, the nonce keyed to the document, the
	 * document, the return field and one button.
	 *
	 * @param string $action  The admin-post action.
	 * @param int    $post_id The document.
	 * @param string $label   The button.
	 * @param string $css     The button's classes.
	 * @param string $return  `WPCPM_Return::DASHBOARD` or ''.
	 */
	private static function render_decision_form( $action, $post_id, $label, $css, $return ) {
		printf( '<form class="wpcpm-sponsor-agreement__form" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( $action . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_POST ), (int) $post_id );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $return, 'sponsor-agreements' );
		}

		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $css ), esc_html( $label ) );
		echo '</form>';
	}

	/**
	 * Finish the Airtable writes an earlier request could not make. The sponsors sync's step.
	 *
	 * `META_AIRTABLE_PENDING` is the mark an upload or a withdrawal leaves when the base was
	 * unreachable at the moment the site's own state changed. Neither path fails the sponsor's
	 * action over it, which is only honest if something later finishes the write: this is that
	 * something, and until it existed the mark was a promise nothing kept. A handler that later
	 * writes the cell itself takes that document's mark with it (`clear_pending()`), so a mark
	 * names a write owed by the document that carries it. It does not name one owed by the
	 * record: `handle_on_file()` writes the sponsor's status for a document of its own, and an
	 * older document of the same sponsor keeps its mark, so that run sends one identical
	 * PATCH that costs a call and changes nothing.
	 *
	 * The cell is written from the site's state now rather than from the status the failed
	 * request meant to send. A mark can be hours old, and a document can have been accepted
	 * or returned in between; the base wants what is true rather than what was true.
	 *
	 * Fifty at a time, because one PATCH is one HTTP call and a run that cannot reach the
	 * base at all should spend a bounded amount of a cron run finding that out.
	 *
	 * @return int How many documents were cleared.
	 */
	public static function retry_airtable() {
		$pending = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				// Any status rather than `private` alone: a row somebody moved by hand still
				// owes the base its cell. Core leaves the trash out of `any`, so a trashed one
				// keeps its mark and is not written, which is the right way round.
				'post_status'      => 'any',
				'numberposts'      => 50,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_key'         => self::META_AIRTABLE_PENDING,
			)
		);

		$cleared = 0;

		foreach ( (array) $pending as $post_id ) {
			$post_id = absint( $post_id );
			$record  = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
				// There is no record to write to, so the mark is not a write that is owed. It
				// goes, or this row is read again on every run for as long as the site stands.
				delete_post_meta( $post_id, self::META_AIRTABLE_PENDING );
				continue;
			}

			$status = self::airtable_status_for( $record );

			if ( ! self::patch( $record, array( self::field( 'agr_status' ) => $status ) ) ) {
				// Still unreachable. The mark stays and the next run tries again.
				continue;
			}

			delete_post_meta( $post_id, self::META_AIRTABLE_PENDING );
			self::rebuild( $record, array( 'status' => $status ) );
			++$cleared;
		}

		return $cleared;
	}

	/**
	 * Forget the mark that says one document still owes the base a write.
	 *
	 * `META_AIRTABLE_PENDING` was deleted by `retry_airtable()` alone, so a mark an upload left
	 * behind when the base was unreachable outlived every later decision on that document, and
	 * a later sync run sent one more PATCH hours later on behalf of a request whose state had
	 * moved on. Every handler that writes the cell itself and is answered calls this.
	 *
	 * @param int $post_id The document.
	 */
	private static function clear_pending( $post_id ) {
		delete_post_meta( absint( $post_id ), self::META_AIRTABLE_PENDING );
	}

	/**
	 * The facts a reviewer reads before opening anything.
	 *
	 * The size is here because it is the one fact a reviewer reads first: a two-page agreement
	 * that weighs nine megabytes is a photograph of every page, which is a slower read and a
	 * different conversation from a signed text PDF.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function review_facts( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return array();
		}

		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$file   = self::file_of( $post_id );
		$author = $post->post_author ? get_userdata( (int) $post->post_author ) : false;
		$flags  = get_post_meta( $post_id, self::META_FLAGS, true );

		return array(
			'post_id'       => $post_id,
			'state'         => (string) get_post_meta( $post_id, self::META_STATE, true ),
			'kind'          => (string) get_post_meta( $post_id, self::META_KIND, true ),
			'sponsor'       => $record,
			'sponsor_name'  => ( is_array( $row ) && '' !== trim( (string) $row['name'] ) ) ? trim( (string) $row['name'] ) : $record,
			'uploaded_by'   => $author instanceof WP_User ? $author->display_name : '',
			'uploaded_at'   => substr( (string) $post->post_date, 0, 10 ),
			'original_name' => (string) get_post_meta( $post_id, self::META_ORIGINAL_NAME, true ),
			'flags'         => is_array( $flags ) ? array_values( $flags ) : array(),
			'size'          => null === $file ? 0 : (int) $file['size'],
			'members'       => count( WPCPM_Sponsor_Members::members_of( $record ) ),
		);
	}

	/**
	 * Take a signed agreement.
	 *
	 * The order is the institution upload's, with the sponsor module's fence in place of the
	 * institution policy's: the nonce keyed to the record, the roster's claim (which decides
	 * `ACT_AGREEMENT` and meters a refusal), the daily ceiling, the declaration, the lock, the
	 * in-review question under it, then the file. The ceiling sits above the file because a
	 * runaway script must be refused before ten megabytes are read into this process.
	 *
	 * An Airtable failure does not fail the upload. The document is on this site and the
	 * sponsor did what was asked; the post carries `META_AIRTABLE_PENDING` and the sponsors
	 * sync's `retry_airtable()` writes the cell. Refusing here would ask the sponsor to upload
	 * again for a fault that is not theirs and not this site's.
	 */
	public static function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to upload an agreement.', 'wpcredits-program-manager' ), 403 );
		}

		$asked = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		check_admin_referer( self::ACTION_UPLOAD . '_' . $asked );

		$claim = WPCPM_Sponsor_Roster::claim( $asked, WPCPM_Sponsor_Policy::ACT_AGREEMENT );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];

		if ( ! WPCPM_Ceiling::claim( self::CEILING . $record, self::upload_limit(), DAY_IN_SECONDS ) ) {
			self::leave( 'agreement-busy', $record );
		}

		// The declaration is a hidden `0` and a checkbox `1`, so an unticked box posts `0`
		// rather than nothing and "I forgot to tick it" and "my browser dropped the field" read
		// the same to the person, which is what they are.
		if ( '1' !== WPCPM_Request::posted_text( self::FIELD_SIGNED ) ) {
			self::leave( 'agreement-declare', $record );
		}

		// One document in review at a time, held by a lock and not only by a read: two presses
		// would otherwise both find none waiting and both insert one, and "the submitted
		// document" would mean two things to accept, return and withdraw.
		if ( ! self::lock( $record ) ) {
			self::leave( 'agreement-busy', $record );
		}

		$summary = self::summary( $record );

		if ( ! empty( $summary['pending_id'] ) ) {
			self::unlock( $record );
			self::leave( 'agreement-in-review', $record );
		}

		$file    = self::uploaded_file();
		$maximum = self::upload_bytes();

		if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] || $file['size'] > $maximum ) {
			self::unlock( $record );
			self::leave( 'agreement-too-big', $record );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
			self::unlock( $record );
			self::leave( 'agreement-no-file', $record );
		}

		// The size PHP reported and the size on disk are the same number on any request that
		// went as it should. The one on disk decides, because it is the one about to be read
		// into this process's memory a few lines below.
		if ( self::disk_size( $file['tmp_name'] ) > $maximum ) {
			self::unlock( $record );
			self::leave( 'agreement-too-big', $record );
		}

		// The name first, then the bytes. `named_pdf()` answers about the name; the two tests
		// below answer about the contents, which is what a renamed executable fails.
		if ( ! WPCPM_Pdf_Check::named_pdf( $file['tmp_name'], $file['name'] ) ) {
			self::unlock( $record );
			self::leave( 'agreement-not-pdf', $record );
		}

		$bytes = self::file_bytes( $file['tmp_name'] );
		$mime  = WPCPM_Pdf_Check::mime_of( $file['tmp_name'] );

		if ( ! WPCPM_Pdf_Check::has_magic( $bytes ) || ! WPCPM_Pdf_Check::mime_agrees( $mime ) ) {
			self::unlock( $record );
			self::leave( 'agreement-not-pdf', $record );
		}

		$scan = WPCPM_Pdf_Check::inspect( $bytes );

		if ( empty( $scan['ok'] ) ) {
			self::unlock( $record );
			self::leave( $scan['reason'], $record );
		}

		$stored = WPCPM_Private_Files::store( $bytes, 'pdf' );

		if ( is_wp_error( $stored ) ) {
			self::unlock( $record );
			self::leave( 'agreement-file', $record );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the sponsor. */
					__( 'Signed sponsor agreement (%s)', 'wpcredits-program-manager' ),
					$record
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			// The bytes are already in the store and nothing points at them, so they go here
			// rather than staying as a file no route could ever reach again.
			WPCPM_Private_Files::forget( $stored['path'] );
			self::unlock( $record );
			self::leave( 'agreement-file', $record );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SPONSOR, $record );
		update_post_meta( $post_id, self::META_STATE, self::STATE_SUBMITTED );
		update_post_meta( $post_id, self::META_KIND, self::KIND_OWN );
		update_post_meta( $post_id, self::META_FILE, $stored );
		update_post_meta( $post_id, self::META_ORIGINAL_NAME, $file['name'] );
		update_post_meta( $post_id, self::META_FLAGS, $scan['flags'] );

		self::add_event( $post_id, self::EVENT_UPLOADED );

		// A replacement uploaded while an accepted agreement stands says nothing about the
		// status: the copy in force is still the copy in force until this one is accepted.
		$landed = true;

		if ( empty( $summary['agreement_id'] ) ) {
			$landed = self::patch( $record, array( self::field( 'agr_status' ) => self::AIRTABLE_AWAITING ) );

			if ( ! $landed ) {
				// The mark the sponsors sync's `retry_airtable()` reads: the document is here
				// and the cell is owed, so the write is finished on the next run instead.
				update_post_meta( $post_id, self::META_AIRTABLE_PENDING, 1 );
			}

			self::rebuild( $record, $landed ? array( 'status' => self::AIRTABLE_AWAITING ) : array(), true );
		}

		self::unlock( $record );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_UPLOAD,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => (string) $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'A signed sponsor agreement was uploaded and is waiting for a program manager to read it.', 'wpcredits-program-manager' ),
				'data'     => array(
					'size'     => (int) $stored['size'],
					'sha256'   => (string) $stored['sha256'],
					'flags'    => $scan['flags'],
					'airtable' => $landed ? 'written' : 'pending',
				),
			)
		);

		self::mail_received( $record, $post_id );

		self::leave( 'agreement-uploaded', $record );
	}

	/**
	 * Hand one stored document to somebody entitled to it.
	 *
	 * Never inline, and the reason is the reviewer as much as anyone: a PDF has a scripting
	 * model, and a program manager's browser should pass the file to a viewer of their choosing
	 * rather than open it in the tab their session lives in. The filename is built from the
	 * company and the date the site holds, never from the uploaded name, because that name is
	 * the one string on this path an outsider chose.
	 *
	 * A legacy row has no file: the answer is 404, the same as a post that does not exist, so
	 * that guessing IDs tells a stranger nothing about which ones are real.
	 */
	public static function handle_download() {
		if ( ! is_user_logged_in() ) {
			// The link is in an email and an email outlives a session. A bare refusal here
			// would read as "you may not have this" to somebody who may.
			wp_safe_redirect( wp_login_url( self::current_url() ) );
			exit;
		}

		$post_id = self::requested_document();

		check_admin_referer( self::ACTION_DOWNLOAD . '_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'That document is not one this site holds.', 'wpcredits-program-manager' ), 404 );
		}

		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$file = self::file_of( $post_id );

		if ( null === $file ) {
			wp_die( esc_html__( 'That document has no file on this site.', 'wpcredits-program-manager' ), 404 );
		}

		$bytes = WPCPM_Private_Files::read( $file['path'] );

		if ( is_wp_error( $bytes ) ) {
			wp_die( esc_html__( 'That file could not be read. Tell a program manager.', 'wpcredits-program-manager' ), 500 );
		}

		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . self::download_name( $post ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Cache-Control: private, no-store' );
		header( 'Content-Length: ' . strlen( $bytes ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The decrypted PDF itself, sent as the body of an attachment response under its own Content-Type; escaping it would corrupt the file.
		echo $bytes;
		exit;
	}

	/**
	 * Take a submitted document back before anybody has read it.
	 *
	 * A member's control, and a manager's on their behalf. The file goes at once rather than on
	 * the discard cron's schedule: withdrawing is what somebody does when they realize they
	 * attached the wrong document, and "it will be deleted within thirty days" is not the
	 * answer that person wants.
	 *
	 * The base is told, unlike the institutions' withdraw. The upload wrote `Awaiting review`
	 * and nothing else, so leaving it would put a manager in front of a queue entry with no
	 * document behind it. What is sent is `airtable_status_for()`, the status this site holds
	 * once the withdrawn document is out of the way, and never a literal: a company whose
	 * earlier document a manager sent back is still a company whose document was sent back.
	 * A failed PATCH does not fail the withdrawal, because the file is already gone: the post
	 * carries the pending mark instead and the sponsors sync's `retry_airtable()` writes the
	 * cell.
	 */
	public static function handle_withdraw() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to withdraw an agreement.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_WITHDRAW . '_' . $post_id );

		$post = self::submitted_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::leave( 'agreement-gone', '' );
		}

		// The subject is the document's own sponsor stamp, never a posted record: the one
		// refusal this handler has to make is a member of B withdrawing A's document, and a
		// posted field is exactly what such a request would carry.
		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

		// The same lock accept and return take, for the same reason and from the other side: a
		// member pressing Withdraw in the second a manager presses Accept must lose or win
		// outright, never delete the file out from under an acceptance that is mid-flight.
		if ( ! self::lock( $record ) ) {
			self::leave( 'agreement-busy', $record );
		}

		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::leave( 'agreement-gone', $record );
		}

		$summary = self::summary( $record );

		update_post_meta( $post_id, self::META_STATE, self::STATE_WITHDRAWN );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, wp_date( 'Y-m-d' ) );

		self::forget_file( $post_id );
		self::add_event( $post_id, self::EVENT_WITHDRAWN );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_WITHDRAW,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => (string) $decision['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'  => __( 'A submitted sponsor agreement was withdrawn and its file deleted.', 'wpcredits-program-manager' ),
				'data'     => array(),
			)
		);

		if ( empty( $summary['agreement_id'] ) ) {
			// Read after the state above is written, so the document being withdrawn is not
			// counted. The `Not started` literal that stood here answered "is anything
			// accepted" and nothing else, so a withdrawal wrote backwards over the `Returned`
			// a manager had set, and the sponsors screen then printed "This site: the last
			// document was returned. Airtable: Not started." with nothing to repair it.
			$status = self::airtable_status_for( $record );

			if ( ! self::patch( $record, array( self::field( 'agr_status' ) => $status ) ) ) {
				// The file is already gone, so the withdrawal stands whatever the base says;
				// the mark is what the sponsors sync's `retry_airtable()` finishes.
				update_post_meta( $post_id, self::META_AIRTABLE_PENDING, 1 );
			} else {
				self::clear_pending( $post_id );
				self::rebuild( $record, array( 'status' => $status ), true );
			}
		}

		self::unlock( $record );

		self::leave( 'agreement-withdrawn', $record );
	}

	/**
	 * Accept one submitted document.
	 *
	 * **Airtable first, and the whole thing refused when it fails.** The base is the program's
	 * record of this state, and a site that recorded an acceptance the base never heard of
	 * would put a manager in front of two screens that disagree with no way to tell which is
	 * right. One PATCH, not two: the status and the date together, because a second call would
	 * be a second chance to half-succeed.
	 *
	 * Nothing is opened by this. A sponsor's dashboard works without an agreement (decision 10),
	 * so acceptance is a record of a fact and not a grant, and that is the whole difference from
	 * the Collaboration Agreement's accept.
	 */
	public static function handle_accept() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_ACCEPT . '_' . $post_id );

		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::bounce( 'agreement-gone' );
		}

		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_REVIEW_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		// Read again, now that nothing else can be writing. The state was checked before the
		// lock was taken, and a withdrawal from the card in that gap would otherwise be
		// accepted over: a document whose file has already been deleted, recorded as the
		// agreement in force.
		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-gone' );
		}

		$today    = wp_date( 'Y-m-d' );
		$previous = self::summary( $record );
		$cells    = array(
			self::field( 'agr_status' )      => self::AIRTABLE_ACCEPTED,
			self::field( 'agr_accepted_on' ) => $today,
		);

		// The copy being superseded is a legacy row, so `agr_document` holds the Drive link to
		// a paper copy that is no longer the agreement. The cell is emptied in the same PATCH:
		// the document in force from here is the one on this site, which the base cannot link.
		$over_legacy = ! empty( $previous['agreement_id'] ) && self::KIND_LEGACY === $previous['kind'];

		if ( $over_legacy ) {
			$cells[ self::field( 'agr_document' ) ] = '';
		}

		if ( ! self::patch( $record, $cells ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		// The cell is written, so an upload's failed write is no longer owed on this document.
		self::clear_pending( $post_id );

		if ( ! empty( $previous['agreement_id'] ) ) {
			self::supersede( (int) $previous['agreement_id'] );
		}

		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		self::add_event( $post_id, self::EVENT_ACCEPTED );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_ACCEPT,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'  => __( 'A signed sponsor agreement was accepted.', 'wpcredits-program-manager' ),
				'data'     => array( 'replaced' => empty( $previous['agreement_id'] ) ? 0 : 1 ),
			)
		);

		$written = array(
			'status'      => self::AIRTABLE_ACCEPTED,
			'accepted_on' => $today,
		);

		if ( $over_legacy ) {
			$written['document'] = '';
		}

		// Inside the critical section, so nothing writes the block between the PATCH and the
		// index. Nothing is left unguarded once the lock goes: the post is accepted now, so a
		// second acceptance is refused by the state test rather than by the lock.
		self::rebuild( $record, $written, true );

		self::unlock( $record );

		self::mail_accepted( $record, $post_id );

		self::bounce( 'agreement-accepted' );
	}

	/**
	 * Send one submitted document back with a note.
	 *
	 * The note is required and mailed verbatim, with reply-to the manager who wrote it: a
	 * company told only that its agreement came back learns nothing, and the person who can
	 * answer the question it will ask is the one who sent it.
	 */
	public static function handle_return() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_RETURN . '_' . $post_id );

		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::bounce( 'agreement-gone' );
		}

		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_REVIEW_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$note = self::posted_note();

		// Ahead of the lock, because this refusal needs nothing but the posted string, and a
		// manager who pressed the button with an empty box must not find the company's record
		// locked for five minutes by their typo.
		if ( ! self::note_fits( $note ) ) {
			self::bounce( 'agreement-note' );
		}

		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-gone' );
		}

		$summary = self::summary( $record );

		// The status is `Returned` unless an accepted agreement stands. Sending a replacement
		// back must not tell the base that the copy in force was returned.
		if ( empty( $summary['agreement_id'] ) && ! self::patch( $record, array( self::field( 'agr_status' ) => self::AIRTABLE_RETURNED ) ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$today = wp_date( 'Y-m-d' );

		update_post_meta( $post_id, self::META_STATE, self::STATE_RETURNED );
		update_post_meta( $post_id, self::META_NOTE, $note );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		self::add_event( $post_id, self::EVENT_RETURNED );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_RETURN,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'  => __( 'A signed sponsor agreement was returned to the company with a note.', 'wpcredits-program-manager' ),
				// The fact that there was a note, never the note: it is the manager's words to
				// one company and the log is read by every manager.
				'data'     => array( 'replacement' => empty( $summary['agreement_id'] ) ? 0 : 1 ),
			)
		);

		if ( empty( $summary['agreement_id'] ) ) {
			// This branch is the one that wrote the cell, so it is the one that settles the mark.
			self::clear_pending( $post_id );
			self::rebuild( $record, array( 'status' => self::AIRTABLE_RETURNED ), true );
		}

		self::unlock( $record );

		self::mail_returned( $record, $note );

		self::bounce( 'agreement-returned' );
	}

	/**
	 * Take an accepted agreement out of force, with a note.
	 *
	 * Airtable first and the whole thing refused when it fails, exactly as accept: the base is
	 * the program's record and a document out of force here while the base says `Accepted` is a
	 * disagreement nobody at the program can see.
	 *
	 * Nothing is taken away. This is the one place the sponsor module reads differently from
	 * the institutions': there, revoking closes an account, and the dialog has to say so. Here
	 * a sponsor's dashboard was never gated on an agreement, so what changes is the record of
	 * which document is in force, and the message says exactly that rather than implying a
	 * consequence that does not follow.
	 */
	public static function handle_revoke() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_REVOKE . '_' . $post_id );

		if ( ! self::document_in_state( $post_id, self::STATE_ACCEPTED ) instanceof WP_Post ) {
			self::bounce( 'agreement-not-accepted' );
		}

		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_REVIEW_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$note = self::posted_note();

		if ( ! self::note_fits( $note ) ) {
			self::bounce( 'agreement-revoke-note' );
		}

		// Read from the document's own meta and never from the form. The nonce is keyed to the
		// document, and a posted record would be this form's claim about what it is acting on
		// rather than the state the site is holding.
		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		if ( ! self::document_in_state( $post_id, self::STATE_ACCEPTED ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-accepted' );
		}

		// The status, and nothing else. Not the date, not the document link. The agreement is
		// still the one the program accepted and still says what it said; what changed is that
		// it is no longer in force.
		if ( ! self::patch( $record, array( self::field( 'agr_status' ) => self::AIRTABLE_REVOKED ) ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		self::clear_pending( $post_id );

		update_post_meta( $post_id, self::META_STATE, self::STATE_REVOKED );
		update_post_meta( $post_id, self::META_NOTE, $note );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, wp_date( 'Y-m-d' ) );

		self::add_event( $post_id, self::EVENT_REVOKED );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_REVOKE,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'  => __( 'An accepted sponsor agreement was taken out of force.', 'wpcredits-program-manager' ),
				'data'     => array( 'kind' => (string) get_post_meta( $post_id, self::META_KIND, true ) ),
			)
		);

		self::rebuild( $record, array( 'status' => self::AIRTABLE_REVOKED ), true );

		self::unlock( $record );

		self::mail_revoked( $record, $note );

		self::bounce( 'agreement-revoked' );
	}

	/**
	 * Put the most recently revoked agreement back in force.
	 *
	 * The abort that makes revoking a safe click. Two things have to be true, and both are read
	 * from stored state rather than from the form: this is the company's most recently revoked
	 * document, and no accepted one stands. The second is what keeps "an accepted one stands"
	 * meaning one document; the first is what stops a manager reinstating last year's agreement
	 * from a page drawn before this year's was revoked.
	 */
	public static function handle_reinstate() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( self::FIELD_POST );

		check_admin_referer( self::ACTION_REINSTATE . '_' . $post_id );

		if ( ! self::document_in_state( $post_id, self::STATE_REVOKED ) instanceof WP_Post ) {
			self::bounce( 'agreement-not-revoked' );
		}

		$decision = self::may_act( $post_id, WPCPM_Sponsor_Policy::ACT_REVIEW_AGREEMENT );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_SPONSOR, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		if ( ! self::document_in_state( $post_id, self::STATE_REVOKED ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-revoked' );
		}

		// Both halves of the "from" column, and both under the lock: the test and the write
		// that acts on it are one decision.
		if ( ! empty( self::summary( $record )['agreement_id'] ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-reinstate-standing' );
		}

		if ( self::latest_revoked( $record ) !== (int) $post_id ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-revoked' );
		}

		$kind   = (string) get_post_meta( $post_id, self::META_KIND, true );
		$status = self::KIND_LEGACY === $kind ? self::AIRTABLE_ON_FILE : self::AIRTABLE_ACCEPTED;

		if ( ! self::patch( $record, array( self::field( 'agr_status' ) => $status ) ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		self::clear_pending( $post_id );

		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );

		// The revocation's reason goes with the revocation: an agreement in force carries no note.
		delete_post_meta( $post_id, self::META_NOTE );

		// Today, not the day it was first accepted. The revocation already overwrote that date
		// with its own, because a document carries one decision's note and date at a time, and
		// the honest answer to "in force since when" for a document that spent a fortnight out
		// of force is the day it came back. The event rows keep every step.
		update_post_meta( $post_id, self::META_DECIDED_AT, wp_date( 'Y-m-d' ) );

		self::add_event( $post_id, self::EVENT_REINSTATED );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_REINSTATE,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'  => __( 'A revoked sponsor agreement was put back in force.', 'wpcredits-program-manager' ),
				'data'     => array(
					'kind'   => $kind,
					'status' => $status,
				),
			)
		);

		self::rebuild( $record, array( 'status' => $status ), true );

		self::unlock( $record );

		self::mail_reinstated( $record );

		self::bounce( 'agreement-reinstated' );
	}

	/**
	 * Record an agreement the program already holds, by hand, with a link.
	 *
	 * The route for the companies that signed before this site existed. The order is accept's:
	 * capability, then the nonce keyed to the company, then the policy, then the link refused
	 * by name when it is not one, then Airtable, then the post, then the index. A recorded
	 * agreement with no link is a claim nobody can check later, which is why the link is
	 * required and checked against two hosts.
	 *
	 * No mail. Nobody at the company asked for this and nothing about it is news to them.
	 */
	public static function handle_on_file() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = trim( WPCPM_Request::posted_text( 'wpcpm_sponsor' ) );

		check_admin_referer( self::ACTION_ON_FILE . '_' . $record );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_REVIEW_AGREEMENT, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		if ( ! WPCPM_Sponsors_Index::has( $record ) ) {
			self::bounce( 'agreement-unknown' );
		}

		$drive = WPCPM_Request::posted_text( self::FIELD_DRIVE );

		if ( ! self::is_drive_link( $drive ) ) {
			self::bounce( 'agreement-link' );
		}

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		if ( ! empty( self::summary( $record )['agreement_id'] ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-standing' );
		}

		$today = wp_date( 'Y-m-d' );

		if ( ! self::patch(
			$record,
			array(
				self::field( 'agr_status' )      => self::AIRTABLE_ON_FILE,
				self::field( 'agr_accepted_on' ) => $today,
				self::field( 'agr_document' )    => $drive,
			)
		) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the sponsor. */
					__( 'Sponsor agreement on file (%s)', 'wpcredits-program-manager' ),
					$record
				),
			),
			true
		);

		// The base already says On file: the flash has to say so, or the manager reads that
		// nothing happened anywhere and files the same link twice by another route.
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-saved' );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SPONSOR, $record );
		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_KIND, self::KIND_LEGACY );
		update_post_meta( $post_id, self::META_DRIVE_URL, esc_url_raw( $drive ) );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		$signed = self::date_or_empty( WPCPM_Request::posted_text( 'wpcpm_sponsor_agr_signed_on' ) );

		if ( '' !== $signed ) {
			update_post_meta( $post_id, self::META_SIGNED_ON, $signed );
		}

		self::add_event( $post_id, self::EVENT_ON_FILE );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_ON_FILE,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'  => __( 'An agreement the program already holds was recorded as on file.', 'wpcredits-program-manager' ),
				'data'     => array( 'signed_on' => $signed ),
			)
		);

		self::rebuild(
			$record,
			array(
				'status'      => self::AIRTABLE_ON_FILE,
				'accepted_on' => $today,
				'document'    => $drive,
			),
			true
		);

		self::unlock( $record );

		self::bounce( 'agreement-on-file' );
	}

	/**
	 * Forget the files of documents nobody is waiting on any more. The daily cron body.
	 *
	 * Withdrawn and returned only. An accepted document is the agreement and is kept; a
	 * superseded one is the copy that was in force before it and is the answer to "what did we
	 * agree to last year"; a legacy row has no file at all. What is left is the two states that
	 * mean "this file was a mistake or is being replaced", and holding somebody's signed
	 * paperwork for longer than there is a reason to is the thing being avoided.
	 *
	 * The post is kept in every case: the history of a company's agreement is what the next
	 * manager reads, and a deleted row would make a return look like it never happened.
	 *
	 * Oldest first, documents that still have a file only, and paged until the query runs dry:
	 * the cursor counts the rows left in place rather than the rows read, because forgetting a
	 * file takes that document out of the query's own answer.
	 *
	 * @return int How many files were forgotten.
	 */
	public static function discard() {
		$days   = (int) WPCPM_Settings::get_value( 'agreement_discard_days', 30 );
		$cut    = time() - ( max( 1, $days ) * DAY_IN_SECONDS );
		$gone   = 0;
		$offset = 0;

		for ( $page = 0; $page < self::DISCARD_PAGES; $page++ ) {
			$posts = (array) get_posts(
				array(
					'post_type'        => self::POST_TYPE,
					'post_status'      => self::POST_STATUS,
					'numberposts'      => self::DISCARD_BATCH,
					'offset'           => $offset,
					'orderby'          => array(
						'date' => 'ASC',
						'ID'   => 'ASC',
					),
					'suppress_filters' => false,
					'meta_query'       => array(
						array(
							'key'     => self::META_STATE,
							'value'   => array( self::STATE_WITHDRAWN, self::STATE_RETURNED ),
							'compare' => 'IN',
						),
						array(
							'key'     => self::META_FILE,
							'compare' => 'EXISTS',
						),
					),
				)
			);

			foreach ( $posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					++$offset;
					continue;
				}

				// A state this pass did not ask for means the query answered more broadly than
				// it was asked to, and an accepted agreement must never lose its file to a cron.
				$state = (string) get_post_meta( $post->ID, self::META_STATE, true );
				$when  = self::decided_at_of( $post );

				// Fail closed: a document with no decided-at date and no readable post_date
				// reads as zero, or as some other value that is never "recent enough to keep",
				// and a cron that deletes files must treat "I don't know when this was decided"
				// as a reason to leave the file alone, not as permission to remove it.
				if ( ! in_array( $state, array( self::STATE_WITHDRAWN, self::STATE_RETURNED ), true ) || $when <= 0 || $when > $cut ) {
					++$offset;
					continue;
				}

				if ( ! self::forget_file( $post->ID ) ) {
					++$offset;
					continue;
				}

				self::add_event( $post->ID, self::EVENT_DISCARDED, 0 );

				++$gone;
			}

			if ( count( $posts ) < self::DISCARD_BATCH ) {
				break;
			}
		}

		return $gone;
	}

	/**
	 * One row per file this module leaves behind, for the manifest the institutions module mails.
	 *
	 * One mailed inventory for both modules (design spec section 8.2): a site owner who
	 * uninstalls the plugin gets one message naming every signed document still on disk, not
	 * one per module. The columns are the institutions manifest's, in its order, so the rows
	 * merge into that list without a second shape.
	 *
	 * Called before `delete_all()`, while the posts still name the files.
	 *
	 * @return array<int, string[]> `company, record, state, decided, path, sha256`.
	 */
	public static function kept_rows() {
		$rows  = array();
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( (array) $posts as $post_id ) {
			$file = get_post_meta( (int) $post_id, self::META_FILE, true );

			if ( ! is_array( $file ) || empty( $file['path'] ) ) {
				continue;
			}

			$record = (string) get_post_meta( (int) $post_id, self::META_SPONSOR, true );
			$row    = class_exists( 'WPCPM_Sponsors_Index' ) ? WPCPM_Sponsors_Index::row( $record ) : null;

			$rows[] = array(
				( is_array( $row ) && '' !== trim( (string) $row['name'] ) ) ? trim( (string) $row['name'] ) : '',
				$record,
				(string) get_post_meta( (int) $post_id, self::META_STATE, true ),
				(string) get_post_meta( (int) $post_id, self::META_DECIDED_AT, true ),
				(string) $file['path'],
				isset( $file['sha256'] ) ? (string) $file['sha256'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Delete every post and every option of this class. Called on uninstall.
	 *
	 * The files and the key are not deleted, on purpose and for the reason the institutions
	 * module gives: they are the program's legal records, and a plugin removal is not the moment
	 * to shred a signed document nobody has another copy of. `kept_rows()` has already told the
	 * site owner where they are.
	 */
	public static function delete_all() {
		global $wpdb;

		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			// The locks share the option prefix, so one pattern removes both.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Options are addressable only by exact name; there is one lock per sponsor. Uninstall only.
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPT_PREFIX ) . '%' ) );

			foreach ( (array) $names as $name ) {
				delete_option( (string) $name );
			}
		} elseif ( isset( $GLOBALS['opts'] ) && is_array( $GLOBALS['opts'] ) ) {
			// The suites have no database: walk the options they can see.
			foreach ( array_keys( $GLOBALS['opts'] ) as $key ) {
				if ( 0 === strpos( (string) $key, self::OPT_PREFIX ) ) {
					delete_option( (string) $key );
				}
			}
		}

		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( (array) $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}

	/**
	 * The unix time a decision was made, for the retention comparison.
	 *
	 * @param WP_Post $post The document.
	 * @return int
	 */
	private static function decided_at_of( WP_Post $post ) {
		$decided = self::date_or_empty( get_post_meta( $post->ID, self::META_DECIDED_AT, true ) );

		if ( '' !== $decided ) {
			return (int) strtotime( $decided . ' 00:00:00 UTC' );
		}

		return self::post_time( $post );
	}

	/**
	 * The unix time a document was created.
	 *
	 * Read from `post_date` rather than `post_date_gmt` because every comparison this feeds is
	 * in days: an offset of a few hours cannot move an item across a thirty-day line in a way
	 * that matters to anybody.
	 *
	 * @param WP_Post $post The document.
	 * @return int
	 */
	private static function post_time( WP_Post $post ) {
		$time = strtotime( (string) $post->post_date . ' UTC' );

		return false === $time ? 0 : (int) $time;
	}

	/*
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The full policy decision for one document, by the document's own sponsor stamp.
	 *
	 * `WPCPM_Sponsor_Policy::subject_post()` reads the sponsor *posts* stamp from Phase S3 and
	 * would answer "no sponsor" for every agreement, so the subject is built from this class's
	 * own meta instead. The whole decision travels back rather than a bare bool, so a caller
	 * that has to log this request's ground is not left deciding it a second time.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  An `ACT_*` constant.
	 * @return array The policy's decision (`allowed`, `ground`, `sponsor`, `why`), or an empty
	 *               array when nothing could be decided.
	 */
	private static function may_act( $post_id, $action ) {
		$record = (string) get_post_meta( absint( $post_id ), self::META_SPONSOR, true );

		return WPCPM_Sponsor_Policy::decide( $action, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );
	}

	/**
	 * One Airtable column's name, by the key the sync's map uses.
	 *
	 * @param string $key A key of `WPCPM_Sponsors_Sync::fields()`.
	 * @return string
	 */
	private static function field( $key ) {
		$fields = WPCPM_Sponsors_Sync::fields();

		return isset( $fields[ $key ] ) ? (string) $fields[ $key ] : '';
	}

	/**
	 * One PATCH of one sponsor's cells.
	 *
	 * @param string $record Sponsors record ID.
	 * @param array  $cells  Column name to value.
	 * @return bool Whether the base took it. An empty answer is a refusal too: `update_records()`
	 *              drops a record it cannot send and answers with the ones it did.
	 */
	private static function patch( $record, array $cells ) {
		if ( empty( $cells ) ) {
			return true;
		}

		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		return ! is_wp_error( $written ) && ! empty( $written );
	}

	/**
	 * What the posts say about one sponsor.
	 *
	 * @param WP_Post[] $posts Newest first.
	 * @return array
	 */
	private static function site_summary( array $posts ) {
		$accepted = null;
		$pending  = 0;
		$latest   = '';

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$state = (string) get_post_meta( $post->ID, self::META_STATE, true );

			if ( self::STATE_ACCEPTED === $state && null === $accepted ) {
				$accepted = $post;
			} elseif ( self::STATE_SUBMITTED === $state && ! $pending ) {
				$pending = (int) $post->ID;
			}

			if ( '' === $latest && in_array( $state, array( self::STATE_RETURNED, self::STATE_REVOKED ), true ) ) {
				$latest = $state;
			}
		}

		$out = array(
			'site_state'   => self::SUMMARY_NONE,
			'kind'         => '',
			'agreement_id' => 0,
			'pending_id'   => $pending,
			'accepted_at'  => '',
			'drive_url'    => '',
		);

		if ( $accepted instanceof WP_Post ) {
			$kind  = (string) get_post_meta( $accepted->ID, self::META_KIND, true );
			$drive = (string) get_post_meta( $accepted->ID, self::META_DRIVE_URL, true );

			$out['site_state']   = self::KIND_LEGACY === $kind ? self::SUMMARY_ON_FILE : self::SUMMARY_ACCEPTED;
			$out['kind']         = $kind;
			$out['agreement_id'] = (int) $accepted->ID;
			$out['accepted_at']  = self::accepted_at_of( $accepted );
			$out['drive_url']    = self::is_drive_link( $drive ) ? $drive : '';

			return $out;
		}

		if ( $pending ) {
			$out['site_state'] = self::SUMMARY_SUBMITTED;
		} elseif ( '' !== $latest ) {
			// The two states spell the same as their summaries.
			$out['site_state'] = $latest;
		}

		return $out;
	}

	/**
	 * The `Agreement Status` the base should be holding for one sponsor, read off this site.
	 *
	 * The same source of truth `summary()` and `rebuild()` work from, mapped the way each
	 * handler maps it: what the site would have written had the base been reachable when the
	 * state changed. Two callers: `retry_airtable()`, which needs the status that is true
	 * now rather than the one a failed request meant to send, and `handle_withdraw()`,
	 * which needs the status that is true once the withdrawn document is out of the way.
	 *
	 * @param string $record Sponsors record ID.
	 * @return string One of the `AIRTABLE_*` choices.
	 */
	private static function airtable_status_for( $record ) {
		switch ( self::site_summary( self::posts_for( $record ) )['site_state'] ) {
			case self::SUMMARY_ACCEPTED:
				return self::AIRTABLE_ACCEPTED;
			case self::SUMMARY_ON_FILE:
				return self::AIRTABLE_ON_FILE;
			case self::SUMMARY_SUBMITTED:
				return self::AIRTABLE_AWAITING;
			case self::SUMMARY_RETURNED:
				return self::AIRTABLE_RETURNED;
			case self::SUMMARY_REVOKED:
				return self::AIRTABLE_REVOKED;
		}

		// Nothing accepted, nothing waiting and nothing sent back: the base holds no status of
		// this site's making, which is what `Not started` says.
		return self::AIRTABLE_NOT_STARTED;
	}

	/**
	 * The date an accepted post was accepted, Y-m-d.
	 *
	 * @param WP_Post $post An accepted post.
	 * @return string
	 */
	private static function accepted_at_of( WP_Post $post ) {
		$decided = self::date_or_empty( get_post_meta( $post->ID, self::META_DECIDED_AT, true ) );

		if ( '' !== $decided ) {
			return $decided;
		}

		return self::date_or_empty( substr( (string) $post->post_date, 0, 10 ) );
	}

	/**
	 * A Y-m-d date, or ''.
	 *
	 * @param mixed $value Anything.
	 * @return string
	 */
	private static function date_or_empty( $value ) {
		$value = substr( trim( (string) $value ), 0, 10 );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}

	/**
	 * Whether a URL is a link into the program's Drive.
	 *
	 * Copied rather than borrowed from `WPCPM_Institution_Agreement`: the spec lists the
	 * extractions this phase makes by name and this is not one of them, and that class's own
	 * host list is not this module's to read.
	 *
	 * @param string $url Anything a manager typed.
	 * @return bool
	 */
	public static function is_drive_link( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		return in_array( strtolower( $parts['host'] ), self::DRIVE_HOSTS, true );
	}

	/**
	 * The lock name for one sponsor.
	 *
	 * @param string $record Sponsors record ID.
	 * @return string
	 */
	private static function lock_name( $record ) {
		return self::LOCK_PREFIX . trim( (string) $record );
	}

	/**
	 * Take the transition lock for a sponsor.
	 *
	 * `add_option()` is the test-and-set: it returns false when the row already exists, and it
	 * is one INSERT, so two presses in the same instant cannot both write. A lock older than
	 * `LOCK_TIMEOUT` belonged to a request that died between taking and releasing it, and is
	 * cleared, since otherwise that one sponsor could never be written again.
	 *
	 * @param string $record Sponsors record ID.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $record ) {
		$key  = self::lock_name( $record );
		$held = get_option( $key );

		if ( false !== $held && ( time() - (int) $held ) > self::LOCK_TIMEOUT ) {
			delete_option( $key );
		}

		return (bool) add_option( $key, time(), '', false );
	}

	/**
	 * Release the transition lock.
	 *
	 * @param string $record Sponsors record ID.
	 */
	private static function unlock( $record ) {
		delete_option( self::lock_name( $record ) );
	}

	/**
	 * An agreement post that is ours, private and waiting for review, or null.
	 *
	 * The one shape accept, return and withdraw all act on, so "is this still in review" is
	 * asked in one place and a second click on any of the three answers the same way.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	private static function submitted_post( $post_id ) {
		return self::document_in_state( $post_id, self::STATE_SUBMITTED );
	}

	/**
	 * One of this class's documents in the state a handler expects to find it, or null.
	 *
	 * Three questions, and each has to be asked again here. Another post type is somebody
	 * else's row; a state that has moved since the page was drawn is a second manager having
	 * pressed the button first; and a sponsor meta that is not a record ID is a document this
	 * handler must not carry into a PATCH, because that string is what names the row Airtable
	 * would write.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $state   The `STATE_*` value the caller requires.
	 * @return WP_Post|null
	 */
	private static function document_in_state( $post_id, $state ) {
		$post = get_post( absint( $post_id ) );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		if ( (string) get_post_meta( $post->ID, self::META_STATE, true ) !== (string) $state ) {
			return null;
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( get_post_meta( $post->ID, self::META_SPONSOR, true ) ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * The company's most recently revoked document, or 0.
	 *
	 * `posts_for()` is newest first, so the first revoked row it hands back is the one the
	 * manager's Reinstate button is drawn against. Reinstating any other would put an older
	 * agreement back in force under a newer one's revocation, which is a state no screen in this
	 * module knows how to describe.
	 *
	 * @param string $record Sponsors record ID.
	 * @return int
	 */
	private static function latest_revoked( $record ) {
		foreach ( self::posts_for( $record ) as $post ) {
			if ( self::STATE_REVOKED === (string) get_post_meta( $post->ID, self::META_STATE, true ) ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}

	/**
	 * The note a manager typed, with its line breaks.
	 *
	 * `WPCPM_Request::posted_lines()` is the textarea reader: the same cleaning as a text field
	 * with the paragraphs left alone, which a note that is mailed verbatim needs.
	 *
	 * @return string
	 */
	private static function posted_note() {
		return WPCPM_Request::posted_lines( self::FIELD_NOTE );
	}

	/**
	 * Whether a note is long enough to be worth mailing and short enough to be one.
	 *
	 * @param string $note The note, as typed.
	 * @return bool
	 */
	private static function note_fits( $note ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $note ) : strlen( $note );

		return $length >= self::MIN_NOTE && $length <= self::MAX_NOTE;
	}

	/**
	 * The stored file a document points at, or null when it has none.
	 *
	 * A legacy row has none, and that is legitimate; the caller decides whether that is a 404
	 * or a fact for the reviewer.
	 *
	 * @param int $post_id Post ID.
	 * @return array{path: string, size: int, sha256: string}|null
	 */
	private static function file_of( $post_id ) {
		$file = get_post_meta( absint( $post_id ), self::META_FILE, true );

		if ( ! is_array( $file ) || empty( $file['path'] ) ) {
			return null;
		}

		return array(
			'path'   => (string) $file['path'],
			'size'   => isset( $file['size'] ) ? (int) $file['size'] : 0,
			'sha256' => isset( $file['sha256'] ) ? (string) $file['sha256'] : '',
		);
	}

	/**
	 * Delete a document's file and the meta that pointed at it.
	 *
	 * Both, in that order, and the meta whether or not the delete reported success: a row
	 * pointing at bytes that are not there any more is what would make the card offer a
	 * download that 500s.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether there was a file to forget.
	 */
	private static function forget_file( $post_id ) {
		$file = self::file_of( $post_id );

		if ( null === $file ) {
			return false;
		}

		WPCPM_Private_Files::forget( $file['path'] );
		delete_post_meta( (int) $post_id, self::META_FILE );

		return true;
	}

	/**
	 * Add one row to a document's event log.
	 *
	 * @param int      $post_id Post ID.
	 * @param string   $event   What happened, in the words the log prints.
	 * @param int|null $actor   Who did it; null for the current user, 0 for the system.
	 */
	private static function add_event( $post_id, $event, $actor = null ) {
		add_post_meta(
			(int) $post_id,
			self::META_EVENT,
			array(
				'event' => (string) $event,
				'at'    => time(),
				'actor' => null === $actor ? get_current_user_id() : (int) $actor,
			)
		);
	}

	/**
	 * Retire one document, with a row saying so.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function supersede( $post_id ) {
		update_post_meta( (int) $post_id, self::META_STATE, self::STATE_SUPERSEDED );
		self::add_event( (int) $post_id, self::EVENT_SUPERSEDED );
	}

	/**
	 * How many uploads one sponsor gets a day.
	 *
	 * @return int
	 */
	private static function upload_limit() {
		return max( 1, (int) WPCPM_Settings::get_value( 'agreement_uploads_per_day', 5 ) );
	}

	/**
	 * The largest file this site accepts, in bytes.
	 *
	 * @return int
	 */
	private static function upload_bytes() {
		return max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) ) * MB_IN_BYTES;
	}

	/**
	 * The posted file, every member typed, nothing trusted.
	 *
	 * `$_FILES` is the one superglobal a form field cannot be read out of with `WPCPM_Request`,
	 * so it is read here, once, and each member is cast on the way out. The name is sanitized
	 * and capped because it is kept for display beside the reviewer's facts; it is never used
	 * on disk and never put in a header.
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
	 * Whether this path is a file PHP received on this request.
	 *
	 * `is_uploaded_file()` is what stops a posted string naming `wp-config.php` from being read
	 * out of the filesystem and stored as somebody's agreement. It answers from PHP's own list,
	 * which is empty under CLI, where the suites run and where no `admin_post_` action can fire.
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
	 * How many bytes a temporary file actually holds.
	 *
	 * @param string $path Temporary path.
	 * @return int
	 */
	private static function disk_size( $path ) {
		$size = filesize( $path );

		return false === $size ? 0 : (int) $size;
	}

	/**
	 * Read an uploaded temporary file into memory, once.
	 *
	 * The magic bytes, the courtesy scan and `store()` all work on this one copy rather than
	 * opening the file three times and risking three different answers.
	 *
	 * @param string $path Temporary path.
	 * @return string The bytes, or '' when they could not be read.
	 */
	private static function file_bytes( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP's own temporary copy of the upload, by the absolute path PHP gave us; `WP_Filesystem` would ask for credentials this request has none to give.
		$bytes = file_get_contents( $path );

		return is_string( $bytes ) ? $bytes : '';
	}

	/**
	 * Which document a download link names.
	 *
	 * The download is a `wp_nonce_url()` link rather than a form, so its arguments arrive in the
	 * query string and `WPCPM_Request::id()` is the right reader for them.
	 *
	 * @return int
	 */
	private static function requested_document() {
		return WPCPM_Request::id( 'post' );
	}

	/**
	 * The download link this request was making, for the login form to come back to.
	 *
	 * Rebuilt from the three arguments the route takes rather than read out of `REQUEST_URI`,
	 * so the host in it is this site's and not a header somebody sent.
	 *
	 * @return string
	 */
	private static function current_url() {
		return add_query_arg(
			array(
				'action'   => self::ACTION_DOWNLOAD,
				'post'     => self::requested_document(),
				'_wpnonce' => WPCPM_Request::key( '_wpnonce' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The filename a download is offered under.
	 *
	 * The company and the date, from what this site holds. Never `META_ORIGINAL_NAME`: that
	 * string is the one thing on this path an outsider chose, and a header is exactly where a
	 * chosen string should not end up.
	 *
	 * @param WP_Post $post The document.
	 * @return string
	 */
	private static function download_name( WP_Post $post ) {
		$record = (string) get_post_meta( $post->ID, self::META_SPONSOR, true );
		$row    = WPCPM_Sponsors_Index::row( $record );
		$name   = ( is_array( $row ) && '' !== trim( (string) $row['name'] ) ) ? trim( (string) $row['name'] ) : $record;
		$slug   = sanitize_title( $name );

		if ( '' === $slug ) {
			$slug = 'sponsor-agreement';
		}

		return sanitize_file_name( $slug . '-' . substr( (string) $post->post_date, 0, 10 ) ) . '.pdf';
	}

	/**
	 * Send one message to every live account of a sponsor.
	 *
	 * Every account at every step, because equal members should not learn from a colleague that
	 * the agreement was accepted, or that it was not.
	 *
	 * @param string   $record  Sponsors record ID.
	 * @param string   $context Mail context, for the log.
	 * @param callable $build   Builder in `WPCPM_Mail::send()`'s shape.
	 * @return int How many were sent.
	 */
	private static function mail_members( $record, $context, $build ) {
		$sent = 0;

		foreach ( WPCPM_Sponsor_Members::members_of( $record ) as $member ) {
			$sent += WPCPM_Mail::send( $member, $context, $build ) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * Tell the sponsor's assigned program manager that a signed agreement is waiting.
	 *
	 * One message, to the manager and not to the sponsor: the sponsor pressed the button a
	 * second ago and read the outcome on the page, and the person who has something to do is
	 * the manager. `mail_manager()` falls back to the `sponsor_notify` addresses and then to
	 * every program manager, so an unassigned sponsor is not a document nobody hears about.
	 *
	 * @param string $record  Sponsors record ID.
	 * @param int    $post_id The submitted document.
	 * @return int How many messages went out.
	 */
	private static function mail_received( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site  = WPCPM_Mail::site_name();
		$days  = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$queue = admin_url( 'admin.php?page=wpcpm-sponsors' );

		$build = function () use ( $site, $facts, $days, $queue ) {
			$lines = array(
				sprintf(
					/* translators: 1: company name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					$facts['sponsor_name'],
					$facts['uploaded_at'],
					'' === $facts['uploaded_by'] ? __( 'somebody at the company', 'wpcredits-program-manager' ) : $facts['uploaded_by']
				),
				sprintf(
					/* translators: %s: number of working days. */
					_n(
						'The program aims to answer within %s working day.',
						'The program aims to answer within %s working days.',
						$days,
						'wpcredits-program-manager'
					),
					number_format_i18n( $days )
				),
				__( 'Read it and accept or return it from the Sponsors screen:', 'wpcredits-program-manager' ),
				$queue,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: company name. */
					__( '[%1$s] %2$s uploaded a signed Collaboration Agreement', 'wpcredits-program-manager' ),
					$site,
					$facts['sponsor_name']
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return (int) WPCPM_Sponsor_Interests::mail_manager( $record, $build, self::MAIL_RECEIVED );
	}

	/**
	 * Tell the company its agreement is accepted.
	 *
	 * @param string $record  Sponsors record ID.
	 * @param int    $post_id The accepted document.
	 * @return int How many accounts were mailed.
	 */
	private static function mail_accepted( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Sponsors_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Nothing about your dashboard changes: it never depended on an agreement.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'You can download the copy we hold from the agreement card on your dashboard.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is accepted', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_ACCEPTED, $build );
	}

	/**
	 * Send the company the manager's note, word for word.
	 *
	 * @param string $record Sponsors record ID.
	 * @param string $note   The note, as typed.
	 * @return int How many accounts were mailed.
	 */
	private static function mail_returned( $record, $note ) {
		$site    = WPCPM_Mail::site_name();
		$manager = wp_get_current_user();
		$named   = $manager instanceof WP_User ? $manager->display_name : '';
		$page    = WPCPM_Sponsors_Dashboard::page_url();

		$build = function () use ( $site, $note, $named, $page, $manager ) {
			$lines = array(
				'' === $named
					? __( 'A program manager read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the manager's name. */
						__( '%s read your Collaboration Agreement and sent it back, with this note:', 'wpcredits-program-manager' ),
						$named
					),
				$note,
				__( 'Upload the corrected document from the agreement card on your dashboard, or reply to this message.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement came back with a note', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ),
			);
		};

		return self::mail_members( $record, self::MAIL_RETURNED, $build );
	}

	/**
	 * Tell the company one document is out of force, and why.
	 *
	 * @param string $record Sponsors record ID.
	 * @param string $note   The note, as typed.
	 * @return int How many accounts were mailed.
	 */
	private static function mail_revoked( $record, $note ) {
		$site    = WPCPM_Mail::site_name();
		$manager = wp_get_current_user();

		$build = function () use ( $site, $note, $manager ) {
			$lines = array(
				__( 'Your Collaboration Agreement is no longer in force, with this note from the program:', 'wpcredits-program-manager' ),
				$note,
				__( 'Your dashboard, your offers and your codes are unchanged: they never depended on an agreement. Reply to this message if you would like to talk about a new one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is no longer in force', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null ),
			);
		};

		return self::mail_members( $record, self::MAIL_REVOKED, $build );
	}

	/**
	 * Tell the company the agreement is back.
	 *
	 * @param string $record Sponsors record ID.
	 * @return int How many accounts were mailed.
	 */
	private static function mail_reinstated( $record ) {
		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $when ) {
			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your Collaboration Agreement is back in force', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => sprintf(
					/* translators: %s: today's date. */
					__( 'Your Collaboration Agreement is in force again as of %s. Nothing else changed.', 'wpcredits-program-manager' ),
					$when
				),
			);
		};

		return self::mail_members( $record, self::MAIL_REINSTATED, $build );
	}

	/**
	 * Return from a manager's transition with a one-shot outcome, and stop.
	 *
	 * The forms are drawn on the wp-admin Sponsors screen and Phase S6 will draw them on the
	 * Administrator Dashboard too, so the destination is where the request came from rather than
	 * one fixed screen; `wp_safe_redirect()` keeps that to this site, and a request with no
	 * referer lands on the Sponsors screen, which is where the row is.
	 *
	 * @param string $status A key of `manager_messages()`.
	 */
	private static function bounce( $status ) {
		// A decision taken on the Administrator Dashboard goes back there, its sentence on that
		// page's channel (1.96.1); every other press returns to the wp-admin Sponsors screen.
		if ( class_exists( 'WPCPM_Return' ) && WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );
			wp_safe_redirect( WPCPM_Return::url( home_url( '/' ) ) );
			exit;
		}

		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $status );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=wpcpm-sponsors' ) );
		exit;
	}

	/**
	 * Flash and go back to the Sponsor Dashboard, at the agreement card.
	 *
	 * Through the dashboard's own method by array callable, as the profile card does. The
	 * manager transitions do not come through here: they are pressed on the wp-admin Sponsors
	 * screen and land back on it through `bounce()`.
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 * @param string $detail A sentence after the status's own, or ''.
	 */
	private static function leave( $status, $record, $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, 'agreement', $record, $detail );
		exit;
	}
}
