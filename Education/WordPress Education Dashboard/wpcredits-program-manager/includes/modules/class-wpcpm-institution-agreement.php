<?php
/**
 * Institutions module - the Collaboration Agreement record and the gate it opens.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether an institution's Collaboration Agreement is settled, and the record that says so.
 *
 * Every agreement document is a `wpcpm_agreement` post: a template the institution generated,
 * a signed copy it uploaded, or a legacy row standing for a copy that lives in the program's
 * Drive folder. The state of those posts is one half of the answer. The other half is the
 * `Agreement Status` column in the Airtable base, which the program treats as the system of
 * record. The gate opens only when both halves agree, and the predicate that decides it,
 * `is_settled()`, reads one non-autoloaded option per institution and nothing else, so it can
 * sit inside the policy's member ground on every request without a query or an HTTP call.
 *
 * **Why two sources, and why fail closed both ways.** A manager who types `Revoked` into the
 * grid expects the account to lock on the next sync; a manager who revokes on the site expects
 * the next sync not to undo it. Requiring agreement means either side can close the gate and
 * neither can open it alone. An absent option, a malformed one, one at the wrong version, or
 * one whose two sides disagree is therefore locked, and the manager screen lists the
 * disagreements naming both sides through `discrepancies()`.
 *
 * **Why the option is rebuilt rather than edited.** `rebuild()` is T12 in the design: given
 * the Airtable agreement block for a record and this site's posts for it, it computes the
 * option from scratch under a per-institution lock. The sync's `records` phase and the Refresh
 * button call it for every row, and every later site-side transition will call it for its
 * own row after writing its post. One function producing the option from the two sources is
 * what makes "an accepted one stands" have a single meaning everywhere.
 *
 * **Why a legacy post is materialised.** Every real Confirmed institution signed years ago,
 * so the fastest route to a settled row is a manager typing `On file` and the Drive link into
 * the grid. The rebuild turns such a row into a legacy post (author 0, event `recorded in
 * Airtable`) so the site side agrees with the grid side, and so the upload, return and replace
 * paths of later phases can ask "does an accepted post stand?" without a special case for
 * agreements that were never uploaded here.
 *
 * Phase 1 shipped the predicate, the summary and the reconcile path; Phase 2 added the on-file
 * route. Phase 3 added the transitions an institution and a reviewer make between them: upload
 * (T3, T10), download, withdraw (T4), accept (T5) and return (T6), with the daily discard
 * (T11) and the reminder digest. Phase 4 adds the two that take the account away and give it
 * back: revoke (T8), the one action on this page that removes access, and reinstate (T9),
 * which is what makes revoking a safe click. `STAGE_ORDER` and `TERMINAL_STAGES` are declared
 * here so the base's spelling is asserted once, in the fixture, and `handle_accept()` is the
 * one write that reads them: a revoke leaves the stage alone, because the plugin does not
 * guess `Not Moving Forward`.
 */
class WPCPM_Institution_Agreement {

	const POST_TYPE = 'wpcpm_agreement';

	/**
	 * The status every agreement post is stored under.
	 *
	 * The type is invisible everywhere, so the status carries no visibility of its own; it is
	 * pinned so that every query and every insert, in this phase and the next, agree on it.
	 */
	const POST_STATUS = 'private';

	/** Post meta: the Institutions record the document belongs to. The queryable key. */
	const META_INSTITUTION = '_wpcpm_agr_institution';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_agr_state';

	/** Post meta: one of the `KIND_*` values. */
	const META_KIND = '_wpcpm_agr_kind';

	/** Post meta: the template language a generated document used. */
	const META_LANGUAGE = '_wpcpm_agr_language';

	/** Post meta: the template version a generated or accepted document carries. */
	const META_TEMPLATE_VERSION = '_wpcpm_agr_template_version';

	/** Post meta: the institution's name as it printed on the generated document. */
	const META_NAME_ON_DOCUMENT = '_wpcpm_agr_name_on_document';

	/** Post meta: `path`, `size`, `sha256` of the stored file; absent on generated and legacy rows. */
	const META_FILE = '_wpcpm_agr_file';

	/** Post meta: the uploaded file's original name, for display only and never for disk. */
	const META_ORIGINAL_NAME = '_wpcpm_agr_original_name';

	/** Post meta: what the courtesy scan noticed in an upload. */
	const META_FLAGS = '_wpcpm_agr_flags';

	/** Post meta: the Drive link a legacy row points at. */
	const META_DRIVE_URL = '_wpcpm_agr_drive_url';

	/** Post meta: the date the paper copy was signed, Y-m-d. */
	const META_SIGNED_ON = '_wpcpm_agr_signed_on';

	/** Post meta: a manager's note on a returned or revoked document. */
	const META_NOTE = '_wpcpm_agr_note';

	/** Post meta: the user who accepted, returned or revoked; 0 when the base did. */
	const META_DECIDED_BY = '_wpcpm_agr_decided_by';

	/** Post meta: the date of that decision, Y-m-d. */
	const META_DECIDED_AT = '_wpcpm_agr_decided_at';

	/** Post meta: set when an Airtable write failed and the sync should retry it. */
	const META_AIRTABLE_PENDING = '_wpcpm_agr_airtable_pending';

	/** Post meta, repeating: one row per event in the document's life. */
	const META_EVENT = '_wpcpm_agr_event';

	const STATE_GENERATED  = 'generated';
	const STATE_SUBMITTED  = 'submitted';
	const STATE_ACCEPTED   = 'accepted';
	const STATE_RETURNED   = 'returned';
	const STATE_WITHDRAWN  = 'withdrawn';
	const STATE_SUPERSEDED = 'superseded';
	const STATE_REVOKED    = 'revoked';

	const KIND_TEMPLATE = 'template';
	const KIND_OWN      = 'own';
	const KIND_LEGACY   = 'legacy';

	/** The summary states an institution's panel and a manager's row name. */
	const SUMMARY_NONE      = 'none';
	const SUMMARY_GENERATED = 'generated';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED  = 'returned';
	const SUMMARY_REVOKED   = 'revoked';
	const SUMMARY_ACCEPTED  = 'accepted';
	const SUMMARY_ON_FILE   = 'on_file';

	/** The two summary states that can settle a row, and only when Airtable agrees. */
	const SETTLED_STATES = array( 'accepted', 'on_file' );

	/**
	 * The pipeline stages a stage write may move along, forward only and never off the end.
	 *
	 * `Student` is left alone by every write. Declared here rather than read from the base so
	 * the spelling is asserted once, against the fixture, and a renamed choice fails a test
	 * instead of a PATCH. Stage writes themselves are a later phase.
	 */
	const STAGE_ORDER = array(
		'First Contact Made',
		'Call Scheduled',
		'Info Sent',
		'Waiting on Reply',
		'Under Review',
		'Agreement Sent',
		'Confirmed',
		'Student',
	);

	/**
	 * The stages that refuse acceptance by name.
	 *
	 * Acceptance is the program saying yes, and it must not say yes to a record it has said no
	 * to: a manager changes the stage in Airtable first. Enforced by the accept handler of a
	 * later phase; declared here beside `STAGE_ORDER` so the two lists are read together.
	 */
	const TERMINAL_STAGES = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );

	/** The `Agreement Status` values under which the base considers the agreement in force. */
	const AIRTABLE_SETTLED = array( 'Accepted', 'On file' );

	/** The `Agreement Status` value a grid-recorded legacy agreement carries. */
	const AIRTABLE_ON_FILE = 'On file';

	/** The event a materialised legacy post carries, and the only way one is told from a manager's. */
	const EVENT_RECORDED = 'recorded in Airtable';

	/** The hosts a Drive link may point at. Exact, lowercase, no `www.`. */
	const DRIVE_HOSTS = array( 'drive.google.com', 'docs.google.com' );

	/** The `admin_post_` action the manager's "agreement on file" form posts to (T7). */
	const ACTION_ON_FILE = 'wpcpm_agreement_on_file';

	/** The `Agreement Kind` choice a legacy row carries in the base, spelled as the fixture holds it. */
	const AIRTABLE_KIND_LEGACY = 'Legacy';

	/** The event a post recorded through this site's on-file form carries, so the route reads `site`. */
	const EVENT_ON_FILE = 'recorded on file';

	/** The audit kind a recording on file writes, in the shape `WPCPM_Institution_Roster::LOG_REFUSED` uses. */
	const LOG_ON_FILE = 'agreement_on_file';

	/** The bulk form of the on-file route: every Confirmed institution with nothing recorded, one link. */
	const ACTION_ON_FILE_ALL = 'wpcpm_agreement_on_file_all';

	/** Where the last bulk recording's tally is kept, for the screen to read back. Non-autoloaded. */
	const OPTION_ON_FILE_ALL = 'wpcpm_agreement_on_file_all';

	/** Seconds a bulk recording spends before it stops and says how far it got. */
	const ON_FILE_ALL_BUDGET = 20;

	/** Longest location note the on-file form keeps, in characters. */
	const MAX_LOCATION = 200;

	const OPTION_PREFIX = 'wpcpm_agreement_';
	const LOCK_PREFIX   = 'wpcpm_agreement_lock_';
	const VERSION       = 1;

	/** Seconds before a held rebuild lock is treated as abandoned. */
	const LOCK_TIMEOUT = 300;

	/** The signed copy arrives here: `admin_post_`, multipart, a member or a manager on behalf. */
	const ACTION_UPLOAD = 'wpcpm_agreement_upload';

	/** The only route to a stored file's bytes. Registered for logged-out too, to send them to log in. */
	const ACTION_DOWNLOAD = 'wpcpm_agreement_download';

	/** A manager accepts one submitted document (T5). */
	const ACTION_ACCEPT = 'wpcpm_agreement_accept';

	/** A manager sends one submitted document back with a note (T6). */
	const ACTION_RETURN = 'wpcpm_agreement_return';

	/** A member or a manager takes a submitted document back (T4). */
	const ACTION_WITHDRAW = 'wpcpm_agreement_withdraw';

	/** A manager takes an accepted agreement out of force, with a note (T8). */
	const ACTION_REVOKE = 'wpcpm_agreement_revoke';

	/** A manager puts the most recently revoked one back (T9). */
	const ACTION_REINSTATE = 'wpcpm_agreement_reinstate';

	/** Daily: forget the files of documents nobody is waiting on any more (T11). */
	const CRON_DISCARD = 'wpcpm_agreement_discard';

	/** How many documents one discard query reads, and how many such queries a run makes. */
	const DISCARD_BATCH = 200;
	const DISCARD_PAGES = 25;

	/** Daily: the review queue's reminder digest. */
	const CRON_REMINDERS = 'wpcpm_agreement_reminders';

	/** `Agreement Status` for a document waiting to be read. */
	const AIRTABLE_AWAITING = 'Awaiting review';

	/** `Agreement Status` for a document a manager sent back. */
	const AIRTABLE_RETURNED = 'Returned';

	/** `Agreement Status` for a document in force. */
	const AIRTABLE_ACCEPTED = 'Accepted';

	/** `Agreement Status` for an agreement the program has taken out of force (T8). */
	const AIRTABLE_REVOKED = 'Revoked';

	/** `Agreement Kind` for a signed copy of the program's own template. */
	const AIRTABLE_KIND_TEMPLATE = 'Program template';

	/** `Agreement Kind` for a document the institution wrote itself. */
	const AIRTABLE_KIND_OWN = 'Institution-specific';

	/** The one stage an acceptance may set, and only forward along `STAGE_ORDER`. */
	const STAGE_CONFIRMED = 'Confirmed';

	/** Mail contexts, so every send is in the log under a name a manager can search for. */
	const MAIL_RECEIVED = 'agreement-received';
	const MAIL_ACCEPTED = 'agreement-accepted';
	const MAIL_RETURNED = 'agreement-returned';
	const MAIL_LANDED   = 'agreement-landed';
	const MAIL_REMINDER = 'agreement-reminder';
	const MAIL_REVOKED  = 'agreement-revoked';

	/**
	 * What the event rows on a document say, in the same voice as the two Phase 2 rows.
	 *
	 * Prose rather than slugs, because the panel and the manager's review block print them
	 * and the log is read by people, not matched on by code. `EVENT_RECORDED` is the one
	 * exception and it is matched on, which is why it is the only one with a predicate.
	 */
	const EVENT_UPLOADED   = 'signed copy uploaded';
	const EVENT_ACCEPTED   = 'accepted';
	const EVENT_RETURNED   = 'returned for changes';
	const EVENT_WITHDRAWN  = 'withdrawn';
	const EVENT_SUPERSEDED = 'superseded by a newer document';
	const EVENT_DISCARDED  = 'file discarded';
	const EVENT_REVOKED    = 'revoked, and the account closed';
	const EVENT_REINSTATED = 'reinstated, and the account opened again';

	/** Audit kinds, one per transition that changes state. */
	const LOG_UPLOAD    = 'agreement_upload';
	const LOG_ACCEPT    = 'agreement_accept';
	const LOG_RETURN    = 'agreement_return';
	const LOG_WITHDRAW  = 'agreement_withdraw';
	const LOG_REVOKE    = 'agreement_revoke';
	const LOG_REINSTATE = 'agreement_reinstate';

	/** A returned document's note, in characters. Long enough to say why, short enough to read. */
	const MIN_NOTE = 20;
	const MAX_NOTE = 2000;

	/** Longest original filename kept for display. It is never used on disk or in a header. */
	const MAX_FILENAME = 200;

	/** The five bytes every PDF begins with. */
	const PDF_MAGIC = '%PDF-';

	/**
	 * Names whose presence refuses the file outright, and the outcome each one flashes.
	 *
	 * Two, and only two. `/Encrypt` because a document nobody can open is not a document the
	 * program can keep, and `/Launch` because it is an action whose only purpose is to run
	 * something on the reader's machine. Everything else the scan finds is a flag: see
	 * `inspect_pdf()` for why the line is drawn there and not further along.
	 */
	const SCAN_REFUSALS = array(
		'/Encrypt' => 'agreement-encrypted',
		'/Launch'  => 'agreement-launch',
	);

	/** Names recorded for the reviewer and never a refusal. */
	const SCAN_FLAGS = array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' );

	/**
	 * How much of a file the courtesy scan will inflate.
	 *
	 * A few hundred bytes of zlib can expand into gigabytes, and a scan that helps an
	 * attacker exhaust the site's memory would be worse than no scan at all. Per stream and
	 * in total, both, because either alone is a way round the other.
	 */
	const SCAN_MAX_STREAMS = 200;
	const SCAN_MAX_STREAM  = 2097152;
	const SCAN_MAX_TOTAL   = 8388608;

	/** Remembers the day the reminder digest went out, so it goes out once. */
	const OPTION_REMINDED = 'wpcpm_agreement_reminded';

	/**
	 * Hooks.
	 *
	 * The on-file handler is registered here, beside the method it names, because that is
	 * the one arrangement `bin/test-institution-policy.php` can read: it resolves every
	 * `admin_post_` registration in the institution classes to a method body in the same
	 * file, and asserts that the body decides before it touches Airtable.
	 *
	 * Download is the one action with a `nopriv` arm, and it is not an exception to that
	 * rule: without one, a member following a link from an email that has signed them out
	 * meets `admin-post.php`'s bare `0` instead of a login form with a way back. The handler
	 * refuses a logged-out request in its first three lines; the arm exists so it can refuse
	 * it usefully. Every other action would tell a stranger something by existing.
	 *
	 * Both crons are hooked here and neither is scheduled here: scheduling belongs to the
	 * module's activation, where every other event of this plugin's is put on the calendar.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_ON_FILE, array( __CLASS__, 'handle_on_file' ) );
		add_action( 'admin_post_' . self::ACTION_ON_FILE_ALL, array( __CLASS__, 'handle_on_file_all' ) );
		add_action( 'admin_post_' . self::ACTION_UPLOAD, array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_DOWNLOAD, array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_' . self::ACTION_ACCEPT, array( __CLASS__, 'handle_accept' ) );
		add_action( 'admin_post_' . self::ACTION_RETURN, array( __CLASS__, 'handle_return' ) );
		add_action( 'admin_post_' . self::ACTION_WITHDRAW, array( __CLASS__, 'handle_withdraw' ) );
		add_action( 'admin_post_' . self::ACTION_REVOKE, array( __CLASS__, 'handle_revoke' ) );
		add_action( 'admin_post_' . self::ACTION_REINSTATE, array( __CLASS__, 'handle_reinstate' ) );
		add_action( self::CRON_DISCARD, array( __CLASS__, 'discard' ) );
		add_action( self::CRON_REMINDERS, array( __CLASS__, 'remind' ) );
	}

	/**
	 * Register the agreement post type.
	 *
	 * Invisible everywhere by design: not public, not queryable, not in REST, not in search,
	 * no admin UI. These rows name institutions and point at signed documents, and the only
	 * routes to them are the institution panel and the manager screen, both of which ask the
	 * policy first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Collaboration Agreements', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Collaboration Agreement', 'wpcredits-program-manager' ),
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
				// A capability type nothing is granted, so no role can reach these through
				// any generic post screen even if one were exposed.
				'capability_type'     => array( 'wpcpm_agreement', 'wpcpm_agreements' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The option holding one institution's settled state.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	public static function option_name( $record_id ) {
		return self::OPTION_PREFIX . trim( (string) $record_id );
	}

	/**
	 * The lock an option is rewritten under.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	public static function lock_name( $record_id ) {
		return self::LOCK_PREFIX . trim( (string) $record_id );
	}

	/**
	 * One institution's stored row, or null when there is nothing the predicate may trust.
	 *
	 * Null covers an absent row, a row that is not an array, one at another version, one
	 * missing a field, and one whose `settled` flag contradicts its own two sides. The last
	 * matters because the flag is what `is_settled()` returns: a row that says "settled" while
	 * naming a `Revoked` grid status is exactly the corruption the two-source rule exists to
	 * catch, so it is treated as unreadable rather than believed.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array|null
	 */
	public static function option( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return null;
		}

		return self::validate( get_option( self::option_name( $record_id ) ) );
	}

	/**
	 * Whether this institution's Collaboration Agreement is settled right now.
	 *
	 * Reads one non-autoloaded option, `wpcpm_agreement_<record>`, and nothing else: no HTTP,
	 * no post query, so it can sit inside the policy's member ground on every request. True
	 * only when BOTH sources agree: an accepted post stands on the site, and Airtable's
	 * `Agreement Status` as read by the last sync (or written by the last site transition) is
	 * `Accepted` or `On file`. Anything else, including a row this class cannot read, is
	 * locked. Never throws.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return bool
	 */
	public static function is_settled( $record_id ) {
		$row = self::option( $record_id );

		return null !== $row && true === $row['settled'];
	}

	/**
	 * Every agreement post for an institution, newest first.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return WP_Post[]
	 */
	public static function posts_for( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
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
						'key'   => self::META_INSTITUTION,
						'value' => $record_id,
					),
				),
			)
		);
	}

	/**
	 * What the panel and the manager's row say about one institution.
	 *
	 * The site half is computed from the posts, not read back from the option, because the
	 * option is deleted by a revoke and may be absent or unreadable after a lost write, and a
	 * panel must still be able to say "revoked" or "returned" then. The Airtable half comes
	 * from the option, since the posts know nothing about the base. `route` says which side
	 * produced the accepted row: `grid` for a legacy post the rebuild materialised from an
	 * `On file` row, `site` for anything a person did here, empty when nothing is accepted.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array{state: string, kind: string, accepted_at: string, agreement_id: int, pending_id: int, generated_id: int, airtable_status: string, route: string}
	 */
	public static function summary( $record_id ) {
		$record_id = trim( (string) $record_id );

		$site   = self::site_summary( self::posts_for( $record_id ) );
		$option = self::option( $record_id );

		return array(
			'state'           => $site['site_state'],
			'kind'            => $site['kind'],
			'accepted_at'     => $site['accepted_at'],
			'agreement_id'    => $site['agreement_id'],
			'pending_id'      => $site['pending_id'],
			'generated_id'    => $site['generated_id'],
			'airtable_status' => null === $option ? '' : $option['airtable_status'],
			'route'           => $site['route'],
		);
	}

	/**
	 * Rebuild one institution's option from the base and the posts (T12).
	 *
	 * The Airtable block is what the sync read for the record: `status`, `kind`, `accepted_on`,
	 * `signed_on`, `accepted_by`, `document` (the Drive link or empty), `submitted_on` and
	 * `template_version`, every one a string. Missing keys read as empty, so a caller with a
	 * partial block gets a locked row rather than a warning.
	 *
	 * Order matters. The lock is taken first: a held lock means another rebuild or a site
	 * transition is mid-write, and the right move is to skip and let the next run catch up,
	 * never to wait or overwrite. The legacy post is materialised before the site half is
	 * computed, so an `On file` row with a Drive link settles in the same call that notices
	 * it. The option is written last and the lock released after it.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $airtable  The agreement block as read from the base.
	 * @return array The option written, or an empty array when nothing was (a bad ID or a held lock).
	 */
	public static function rebuild( $record_id, array $airtable ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return array();
		}

		$airtable = self::normalise( $airtable );

		if ( ! self::lock( $record_id ) ) {
			return array();
		}

		$site = self::site_summary( self::posts_for( $record_id ) );

		if ( self::AIRTABLE_ON_FILE === $airtable['status'] && ! $site['agreement_id'] && self::is_drive_link( $airtable['document'] ) ) {
			if ( self::materialise_legacy( $record_id, $airtable ) ) {
				$site = self::site_summary( self::posts_for( $record_id ) );
			}
		}

		$drive_url = $site['drive_url'];

		// The link is worth keeping even when no post stands yet: it is what the manager row
		// shows beside a row that says `On file` and did not settle.
		if ( '' === $drive_url && self::is_drive_link( $airtable['document'] ) ) {
			$drive_url = $airtable['document'];
		}

		$option = array(
			'v'               => self::VERSION,
			'settled'         => self::settles( $site['site_state'], $airtable['status'] ),
			'site_state'      => $site['site_state'],
			'airtable_status' => $airtable['status'],
			'kind'            => $site['kind'],
			'agreement_id'    => $site['agreement_id'],
			'pending_id'      => $site['pending_id'],
			'generated_id'    => $site['generated_id'],
			'accepted_at'     => $site['accepted_at'],
			'drive_url'       => $drive_url,
			'updated'         => time(),
		);

		update_option( self::option_name( $record_id ), $option, false );

		self::unlock( $record_id );

		return $option;
	}

	/**
	 * Rebuild every option the sync has a block for.
	 *
	 * @param array $airtable_by_record Agreement blocks keyed by Institutions record ID.
	 * @return int How many options were written.
	 */
	public static function rebuild_all( array $airtable_by_record ) {
		$written = 0;

		foreach ( $airtable_by_record as $record_id => $airtable ) {
			if ( ! is_array( $airtable ) ) {
				continue;
			}

			if ( ! empty( self::rebuild( $record_id, $airtable ) ) ) {
				++$written;
			}
		}

		return $written;
	}

	/**
	 * Every stored row whose two sources disagree, naming both sides.
	 *
	 * A disagreement is one side settled and the other not: an `Accepted` grid row with no
	 * accepted post, an accepted post under a grid row that now reads `Revoked`. Two sides
	 * that agree on "not yet" are not listed; that is a queue, not a fault. A row this class
	 * cannot read is listed with whatever strings it holds, or empty ones, because the
	 * predicate treats it as locked and the manager should be told why.
	 *
	 * @return array Keyed by record ID: `site_state` and `airtable_status`.
	 */
	public static function discrepancies() {
		$out = array();

		foreach ( self::stored_records() as $record_id ) {
			$stored = get_option( self::option_name( $record_id ) );
			$row    = self::validate( $stored );

			if ( null === $row ) {
				$out[ $record_id ] = array(
					'site_state'      => is_array( $stored ) && isset( $stored['site_state'] ) ? (string) $stored['site_state'] : '',
					'airtable_status' => is_array( $stored ) && isset( $stored['airtable_status'] ) ? (string) $stored['airtable_status'] : '',
				);
				continue;
			}

			$site = in_array( $row['site_state'], self::SETTLED_STATES, true );
			$grid = in_array( $row['airtable_status'], self::AIRTABLE_SETTLED, true );

			if ( $site !== $grid ) {
				$out[ $record_id ] = array(
					'site_state'      => $row['site_state'],
					'airtable_status' => $row['airtable_status'],
				);
			}
		}

		return $out;
	}

	/**
	 * Whether a URL is a Drive link the on-file route accepts.
	 *
	 * `https` and one of two exact hosts. A Dropbox link, a `www.` prefix or a plain `http`
	 * are refused: the manager is asked to paste the Drive link to the folder or the file,
	 * and a grid row carrying anything else stays unsettled rather than pointing a reviewer
	 * somewhere the program does not keep documents.
	 *
	 * @param string $url The link to test.
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
	 * Record an agreement the program already holds, by hand, with a link (T7).
	 *
	 * The route every real pilot candidate needs. All 42 Confirmed institutions signed
	 * before this site existed, so the first thing the module has to be able to say is "the
	 * program already holds this one", and the account has to open without asking a partner
	 * of several years to sign again.
	 *
	 * The order is the whole point, and it is the same order acceptance will use in Phase 3:
	 *
	 * 1. capability, then nonce, then the policy. The capability is the cheap refusal, the
	 *    nonce is keyed to the institution so a form for one record cannot be replayed at
	 *    another, and `decide()` is the fence every write in this module goes through even
	 *    when the capability has already answered.
	 * 2. the Drive link, refused by name when it is not one. A recorded agreement with no
	 *    link is a claim nobody can check later.
	 * 3. **Airtable first, and the whole thing refused when it fails.** The base is the
	 *    program's record of this state. An institution opened on the site while the base
	 *    still says `Not started` is exactly the shape this design exists to prevent, and a
	 *    site that wrote its own record first would produce it on every failed PATCH.
	 * 4. then the post, then the option through `rebuild()`. If the process dies between
	 *    them, the next reconcile (T12) materialises the post from the `On file` row it now
	 *    finds and settles the institution: the same end state, one sync later.
	 *
	 * No mail. Nobody at the institution asked for this and nothing about it is news to
	 * them; what they notice is that the dashboard is there on the next page load.
	 */
	public static function handle_on_file() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		// Read before the nonce is checked because the nonce is keyed to it; nothing is done
		// with it until `check_admin_referer()` below has passed and the index has been asked
		// whether it is a record at all.
		$record = WPCPM_Request::posted_text( 'wpcpm_agreement_record' );

		check_admin_referer( self::ACTION_ON_FILE . '_' . $record );

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		if ( ! WPCPM_Institutions_Index::has( $record ) ) {
			self::bounce_on_file( 'agreement-unknown' );
		}

		$drive = WPCPM_Request::posted_text( 'wpcpm_agreement_drive' );

		// Ahead of the lock, because this refusal needs nothing but the posted string: a
		// link that is not a Drive link must not make an option row at all, not even one
		// that is deleted on the next line.
		if ( ! self::is_drive_link( $drive ) ) {
			self::bounce_on_file( 'agreement-link' );
		}

		$signed = self::date_or_empty( WPCPM_Request::posted_text( 'wpcpm_agreement_signed_on' ) );
		$where  = trim( mb_substr( WPCPM_Request::posted_text( 'wpcpm_agreement_where' ), 0, self::MAX_LOCATION ) );

		self::bounce_on_file( self::record_on_file( $record, $drive, $signed, $where, $decision ) );
	}

	/**
	 * Record one institution's agreement as on file, and open its account.
	 *
	 * The write behind both on-file forms: the single one, which has already read and
	 * checked its form, and the bulk one, which loops this over every Confirmed institution
	 * with nothing recorded. Airtable first and refused outright on failure, then the legacy
	 * post, then the option: an institution opened on the site while the base still says
	 * otherwise is the shape this design exists to prevent. Answers with the outcome slug the
	 * caller flashes, and never redirects itself, which is what lets it be looped.
	 *
	 * @param string $record   Institutions record ID, already known to the index.
	 * @param string $drive    A Drive link, already validated.
	 * @param string $signed   Date signed, `Y-m-d` or ''.
	 * @param string $where    Where the paper is, in the manager's words, or ''.
	 * @param array  $decision The policy's decision that allowed this.
	 * @return string One of the outcome slugs `WPCPM_Institution_Panel::messages()` names.
	 */
	private static function record_on_file( $record, $drive, $signed, $where, array $decision ) {
		// The transition lock, taken here for the reason T5, T8 and T9 take theirs: the "no
		// accepted post" test below and the insert that acts on it are one decision, and two
		// managers pressing Record it in the same second would otherwise both find none and
		// both insert one, leaving an institution with two accepted agreements and the base
		// patched twice. `add_option()` is the test-and-set, and `lock()` clears one older
		// than LOCK_TIMEOUT so a request that died holding it cannot lock a record forever.
		// Every exit from here on releases it.
		if ( ! self::lock( $record ) ) {
			return 'agreement-busy';
		}

		// T7's "from" column: no accepted post. Replacing one is what upload and accept are
		// for, and a second legacy row beside an accepted one would make "an accepted one
		// stands" mean two different things to T3, T6 and T10.
		$summary = self::summary( $record );

		if ( ! empty( $summary['agreement_id'] ) ) {
			self::unlock( $record );
			return 'agreement-standing';
		}

		$today    = wp_date( 'Y-m-d' );
		$actor    = wp_get_current_user();
		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();

		// Named through the sync's field map so the base's spelling is asserted in one place
		// and against one fixture. `update_records()` sends no `typecast`, so a choice spelled
		// any other way is a 422 for the whole record.
		$cells = array(
			$fields['agr_status']      => self::AIRTABLE_ON_FILE,
			$fields['agr_kind']        => self::AIRTABLE_KIND_LEGACY,
			$fields['agr_document']    => $drive,
			$fields['agr_accepted_on'] => $today,
			$fields['agr_accepted_by'] => $actor instanceof WP_User ? $actor->display_name : '',
		);

		if ( '' !== $signed ) {
			$cells[ $fields['agr_signed_on'] ] = $signed;
		}

		$airtable = new WPCPM_Airtable( $settings );
		$written  = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		// An empty result is a refusal too: `update_records()` drops a record it cannot send
		// and answers with the records it did, so "nothing was updated" must not read as
		// success on the one path where success opens an account.
		if ( is_wp_error( $written ) || empty( $written ) ) {
			self::unlock( $record );
			return 'agreement-airtable';
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the institution. */
					__( 'Collaboration Agreement on file (%s)', 'wpcredits-program-manager' ),
					$record
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::unlock( $record );
			return 'agreement-not-saved';
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_INSTITUTION, $record );
		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_KIND, self::KIND_LEGACY );
		update_post_meta( $post_id, self::META_DRIVE_URL, esc_url_raw( $drive ) );
		update_post_meta( $post_id, self::META_SIGNED_ON, $signed );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		// Where the paper is, in the manager's own words. Section 9 keeps one note field per
		// document and this is the only note a legacy row can carry, so it goes there rather
		// than earning a meta key of its own; nothing an institution sees ever prints it.
		if ( '' !== $where ) {
			update_post_meta( $post_id, self::META_NOTE, $where );
		}

		add_post_meta(
			$post_id,
			self::META_EVENT,
			array(
				'event' => self::EVENT_ON_FILE,
				'at'    => time(),
				'actor' => get_current_user_id(),
			)
		);

		// Design spec 5.6: membership and agreement events are rows of the same type, and
		// every row carries the ground the act was allowed on. This was the one write on the
		// institution side that left none. `attach()`, `detach()` and the live claim all
		// write one, so an account could open here with nothing in the log saying who opened
		// it or on what basis, which is the one question a manager asks of an agreement
		// months later. The ground comes from the decision rather than from the capability
		// checked at the top, because the decision is what allowed it; the subject is the
		// document, so the row survives the option being rebuilt or deleted.
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_ON_FILE,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => __( 'A Collaboration Agreement the program already held was recorded as on file, and the account opened.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'      => self::KIND_LEGACY,
					'drive'     => $drive,
					'signed_on' => $signed,
				),
			)
		);

		// Released before the rebuild, which takes the same lock for its own critical
		// section: a handler still holding it would make every successful recording report
		// that the state was being rebuilt. Nothing is left unguarded by letting go here,
		// because the accepted post now exists and the next request to take the lock is
		// refused by the standing-agreement test rather than by the lock.
		self::unlock( $record );

		// The block as the base now holds it, minus the name in `Agreement Accepted By`: the
		// option is read on every request by the policy and holds no prose about people.
		$option = self::rebuild(
			$record,
			array(
				'status'           => self::AIRTABLE_ON_FILE,
				'kind'             => self::AIRTABLE_KIND_LEGACY,
				'accepted_on'      => $today,
				'signed_on'        => $signed,
				'accepted_by'      => '',
				'document'         => $drive,
				'submitted_on'     => '',
				'template_version' => '',
			)
		);

		// A held lock means a sync was rebuilding this very record; the two writes that
		// matter have landed and the next run finishes the job, which is what the manager is
		// told rather than "done" over a gate that is still closed.
		return empty( $option ) ? 'agreement-later' : 'agreement-on-file';
	}

	/**
	 * Record every Confirmed institution with nothing recorded as signed, with one link.
	 *
	 * Every institution the pipeline holds at Confirmed signed a Collaboration Agreement: that
	 * is what the stage means, and it was true before this site existed to generate or upload
	 * one. Decision 24 keeps them out of the gate by having a program manager record each as
	 * on file, and with thirty-eight of them, "each" by hand is the kind of chore that gets
	 * done for the pilot institution and nobody else, leaving the bulk button shut for
	 * everyone. So this is that route applied to all of them at once, with one link: the
	 * program's folder of signed agreements, which is where they actually are.
	 *
	 * The same write as the single route, per institution, in the same order, with the same
	 * lock and the same audit row. Nothing is treated as signed by fiat: each institution gets
	 * its own recorded agreement, its own row in the log naming who recorded it and on what
	 * basis, and its own Airtable cells, so the base and the site agree afterwards. An
	 * institution that fails is named and left for the single route; the run stops after
	 * ON_FILE_ALL_BUDGET seconds and says how far it got, because a request that times out
	 * halfway is one that has recorded some and cannot say which.
	 */
	public static function handle_on_file_all() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( self::ACTION_ON_FILE_ALL );

		$drive = WPCPM_Request::posted_text( 'wpcpm_agreement_drive' );

		if ( ! self::is_drive_link( $drive ) ) {
			self::bounce_on_file( 'agreement-link' );
		}

		$where = trim( mb_substr( WPCPM_Request::posted_text( 'wpcpm_agreement_where' ), 0, self::MAX_LOCATION ) );
		$tally = array(
			'recorded' => 0,
			'skipped'  => 0,
			'failed'   => array(),
			'left'     => 0,
			'at'       => time(),
			'actor'    => get_current_user_id(),
		);
		$began = microtime( true );
		$todo  = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			if ( 'Confirmed' !== trim( (string) $row['stage'] ) || self::is_settled( $record_id ) ) {
				continue;
			}

			$todo[] = $record_id;
		}

		if ( empty( $todo ) ) {
			self::bounce_on_file( 'agreement-all-none' );
		}

		foreach ( $todo as $i => $record_id ) {
			if ( microtime( true ) - $began > self::ON_FILE_ALL_BUDGET ) {
				$tally['left'] = count( $todo ) - $i;
				break;
			}

			// Decided per institution, on the manager ground, because that is what each audit
			// row records: a bulk action is thirty-eight decisions, not one.
			$decision = WPCPM_Institution_Policy::decide(
				WPCPM_Institution_Policy::ACT_AGREEMENT,
				WPCPM_Institution_Policy::subject_institution( $record_id )
			);

			if ( empty( $decision['allowed'] ) ) {
				$tally['failed'][ $record_id ] = 'refused';
				continue;
			}

			$outcome = self::record_on_file( $record_id, $drive, '', $where, $decision );

			if ( 'agreement-on-file' === $outcome || 'agreement-later' === $outcome ) {
				++$tally['recorded'];
			} elseif ( 'agreement-standing' === $outcome ) {
				++$tally['skipped'];
			} else {
				$tally['failed'][ $record_id ] = $outcome;
			}
		}

		update_option( self::OPTION_ON_FILE_ALL, $tally, false );

		self::bounce_on_file( 'agreement-on-file-all' );
	}

	/**
	 * Take a signed agreement in (T3, T10).
	 *
	 * The plugin's first multipart handler, and the thirteen steps of design spec 7.4 are an
	 * order rather than a list. Two boundaries in it carry the whole design:
	 *
	 * - **The ceiling is claimed before the bytes are looked at.** A script posting files in a
	 *   loop should cost this site one option read each, not one read, one hash and one
	 *   decompression pass over ten megabytes each. Anything cheaper than the ceiling goes
	 *   above it; nothing else does.
	 * - **Nothing reaches the private directory that has not passed every check.** The
	 *   extension, the declared type, the magic bytes, `finfo` and the courtesy scan all run
	 *   against PHP's temporary copy, and `store()` is called after the last of them. A file
	 *   that fails any one of them was never in the store to have to be removed from it.
	 *
	 * Airtable comes after the post, not before, and a failure there does not fail the upload:
	 * an institution that has sent its signed agreement has done its part, and telling it
	 * otherwise because a third party was down would be the plugin's mistake presented as
	 * theirs. The record carries `_wpcpm_agr_airtable_pending` and the sync retries.
	 */
	public static function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to upload an agreement.', 'wpcredits-program-manager' ), 403 );
		}

		$record = self::record_for_request();

		check_admin_referer( self::ACTION_UPLOAD . '_' . $record );

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// Step 4, and it is above step 6 on purpose: see the docblock.
		if ( ! WPCPM_Ceiling::claim( 'agreement-upload:' . $record, self::upload_limit(), DAY_IN_SECONDS ) ) {
			self::bounce( 'agreement-busy' );
		}

		// The declaration is a hidden `0` and a checkbox `1`, so an unticked box posts `0`
		// rather than nothing and "I forgot to tick it" and "my browser dropped the field"
		// read the same to the person, which is what they are.
		if ( '1' !== WPCPM_Request::posted_text( 'wpcpm_agreement_signed' ) ) {
			self::bounce( 'agreement-declare' );
		}

		$kind = WPCPM_Request::posted_key( 'wpcpm_agreement_kind' );

		if ( ! in_array( $kind, array( self::KIND_TEMPLATE, self::KIND_OWN ), true ) ) {
			self::bounce( 'agreement-kind' );
		}

		// One in review at a time. Two documents waiting would make "the submitted post" mean
		// two things to accept, return and withdraw, and a reviewer would have to guess which
		// of them the institution meant.
		$summary = self::summary( $record );

		if ( ! empty( $summary['pending_id'] ) ) {
			self::bounce( 'agreement-in-review' );
		}

		$file    = self::uploaded_file();
		$maximum = self::upload_bytes();

		if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] || $file['size'] > $maximum ) {
			self::bounce( 'agreement-too-big' );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
			self::bounce( 'agreement-no-file' );
		}

		// The size PHP reported and the size on disk are the same number on any request that
		// went as it should. The one on disk decides, because it is the one about to be read
		// into this process's memory a few lines below.
		if ( self::disk_size( $file['tmp_name'] ) > $maximum ) {
			self::bounce( 'agreement-too-big' );
		}

		// The extension and the declared type together, from WordPress's own map, then the
		// bytes. `wp_check_filetype_and_ext()` answers about the name; the two tests below
		// answer about the contents, which is what a renamed executable fails.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'pdf' => 'application/pdf' ) );

		if ( 'pdf' !== ( isset( $checked['ext'] ) ? $checked['ext'] : '' ) || 'application/pdf' !== ( isset( $checked['type'] ) ? $checked['type'] : '' ) ) {
			self::bounce( 'agreement-not-pdf' );
		}

		$bytes = self::file_bytes( $file['tmp_name'] );
		$mime  = self::mime_of( $file['tmp_name'] );

		// An empty `$mime` is a host with no fileinfo extension. The magic bytes and the map
		// above still run, so the refusal that matters still happens; a host without fileinfo
		// is not told it may never send an agreement.
		if ( self::PDF_MAGIC !== substr( $bytes, 0, strlen( self::PDF_MAGIC ) ) || ( '' !== $mime && 'application/pdf' !== $mime ) ) {
			self::bounce( 'agreement-not-pdf' );
		}

		$scan = self::inspect_pdf( $bytes );

		if ( empty( $scan['ok'] ) ) {
			self::bounce( $scan['reason'] );
		}

		$stored = WPCPM_Private_Files::store( $bytes, 'pdf' );

		if ( is_wp_error( $stored ) ) {
			self::bounce( 'agreement-file' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the institution. */
					__( 'Signed Collaboration Agreement (%s)', 'wpcredits-program-manager' ),
					$record
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			// The bytes are already in the store and nothing points at them, so they are
			// removed here rather than left as a file no route could ever reach again.
			WPCPM_Private_Files::forget( $stored['path'] );
			self::bounce( 'agreement-file' );
		}

		$post_id = (int) $post_id;
		$today   = wp_date( 'Y-m-d' );

		update_post_meta( $post_id, self::META_INSTITUTION, $record );
		update_post_meta( $post_id, self::META_STATE, self::STATE_SUBMITTED );
		update_post_meta( $post_id, self::META_KIND, $kind );
		update_post_meta( $post_id, self::META_FILE, $stored );
		update_post_meta( $post_id, self::META_ORIGINAL_NAME, $file['name'] );
		update_post_meta( $post_id, self::META_FLAGS, $scan['flags'] );

		self::add_event( $post_id, self::EVENT_UPLOADED );

		// A template the institution generated and then signed is the same agreement, so the
		// generated row stops being an outstanding step and becomes history. So does one an
		// institution generated and then answered with its own lawyers' paper instead: the
		// step was answered either way, and a generated row left standing beside a document
		// in review would read as a second thing still to do.
		$version = self::supersede_generated( $record, $post_id );

		// The version travels with the program's own template and nothing else. It is what
		// lets the reviewer's checklist say which template the copy in front of them was cut
		// from; on an institution's own paper there is no such footer to compare, and
		// `handle_accept()` would write it into `Agreement Template Version` beside
		// `Agreement Kind` = `Institution-specific`, which is a sentence the base cannot mean.
		if ( self::KIND_TEMPLATE === $kind && '' !== $version ) {
			update_post_meta( $post_id, self::META_TEMPLATE_VERSION, $version );
		}

		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();
		$cells    = array( $fields['agr_submitted_on'] => $today );

		// T10: a replacement uploaded while an accepted agreement stands writes the date and
		// nothing else. The kind and the status describe the copy that is in force, and a
		// replacement that is never accepted must not have changed either of them.
		if ( empty( $summary['agreement_id'] ) ) {
			$cells[ $fields['agr_status'] ] = self::AIRTABLE_AWAITING;
			$cells[ $fields['agr_kind'] ]   = self::airtable_kind( $kind );
		}

		$airtable = new WPCPM_Airtable( $settings );
		$written  = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		$landed = ! is_wp_error( $written ) && ! empty( $written );

		if ( ! $landed ) {
			update_post_meta( $post_id, self::META_AIRTABLE_PENDING, 1 );
		}

		self::rebuild(
			$record,
			self::airtable_block( $record, ( $landed && empty( $summary['agreement_id'] ) ) ? array( 'status' => self::AIRTABLE_AWAITING ) : array() )
		);

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_UPLOAD,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => __( 'A signed Collaboration Agreement was uploaded and is waiting for a program manager to read it.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'     => $kind,
					'size'     => (int) $stored['size'],
					'sha256'   => (string) $stored['sha256'],
					'flags'    => $scan['flags'],
					'airtable' => $landed ? 'written' : 'pending',
				),
			)
		);

		self::mail_received( $record, $post_id );
		self::mail_landed( $record, $post_id );

		self::bounce( 'agreement-uploaded' );
	}

	/**
	 * Hand one stored document to somebody entitled to it.
	 *
	 * Never inline, and the reason is the reviewer as much as anyone: a PDF has a scripting
	 * model, and a program manager's browser should pass the file to a viewer of their
	 * choosing rather than open it in the tab their session lives in. The filename is built
	 * from the institution and the date the site holds, never from the uploaded name, because
	 * that name is the one string on this path an outsider chose.
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

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
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
	 * Accept one submitted document, and open the institution's account (T5).
	 *
	 * The order is the on-file route's, with one addition: the record is read live before it
	 * is written. Acceptance is the program saying yes, and it must not say yes to a record
	 * it has said no to, so the stage is read from the base rather than from the index the
	 * last sync left behind. A read that fails refuses the whole thing, because an acceptance
	 * that cannot read the record must not write it.
	 *
	 * One PATCH, not two. The five agreement cells and, when the stage read precedes it,
	 * `Current Stage`. A second call would be a second chance to half-succeed.
	 *
	 * The outcome is the on-file route's as well: `agreement-accepted` when the gate is open
	 * by the end of the request, `agreement-later` when the rebuild found the lock held and
	 * the account opens on the next sync instead. A manager told the account is open does not
	 * press Refresh, and this is the one path where that leaves a whole institution locked
	 * out with an email in their inbox saying otherwise.
	 */
	public static function handle_accept() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'wpcpm_agreement_post' );

		check_admin_referer( self::ACTION_ACCEPT . '_' . $post_id );

		$post = self::submitted_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'agreement-gone' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		// Read again, now that nothing else can be writing. The state was checked before the
		// lock was taken, and a withdrawal from the institution's own panel in that gap would
		// otherwise be accepted over: a document whose file has already been deleted, recorded
		// as the agreement in force.
		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-gone' );
		}

		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();
		$airtable = new WPCPM_Airtable( $settings );
		$live     = $airtable->get_record( $settings['institutions_table'], $record );

		if ( is_wp_error( $live ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$stage = isset( $live['fields'][ $fields['stage'] ] ) ? trim( (string) $live['fields'][ $fields['stage'] ] ) : '';

		if ( in_array( $stage, self::TERMINAL_STAGES, true ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-stage' );
		}

		$today = wp_date( 'Y-m-d' );
		$actor = wp_get_current_user();
		$kind  = (string) get_post_meta( $post_id, self::META_KIND, true );

		// Only a signed copy of the program's own template has a template version. The cell is
		// written on both kinds all the same, because an empty value is what clears a version
		// the base is holding from an earlier upload: `Institution-specific` standing beside a
		// template version is a contradiction a manager reading the grid cannot resolve.
		$version = self::KIND_TEMPLATE === $kind ? (string) get_post_meta( $post_id, self::META_TEMPLATE_VERSION, true ) : '';

		$cells = array(
			$fields['agr_status']      => self::AIRTABLE_ACCEPTED,
			$fields['agr_kind']        => self::airtable_kind( $kind ),
			$fields['agr_accepted_on'] => $today,
			$fields['agr_accepted_by'] => $actor instanceof WP_User ? $actor->display_name : '',
			$fields['agr_template']    => $version,
		);

		// Forward only, and never off the end. An empty stage counts as preceding, because a
		// record with no stage at all is one nothing has moved yet.
		if ( self::precedes_confirmed( $stage ) ) {
			$cells[ $fields['stage'] ] = self::STAGE_CONFIRMED;
		}

		$written = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		// An empty result is a refusal too: `update_records()` drops a record it cannot send
		// and answers with the ones it did, so "nothing was updated" must not read as success
		// on the path where success opens an account.
		if ( is_wp_error( $written ) || empty( $written ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$previous = self::summary( $record );

		if ( ! empty( $previous['agreement_id'] ) ) {
			self::supersede( (int) $previous['agreement_id'] );
		}

		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		self::add_event( $post_id, self::EVENT_ACCEPTED );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_ACCEPT,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => __( 'A signed Collaboration Agreement was accepted, and the institution\'s account opened.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'     => $kind,
					'stage'    => $stage,
					'confirms' => isset( $cells[ $fields['stage'] ] ) ? 1 : 0,
				),
			)
		);

		// Released before the rebuild, which takes the same lock for its own critical
		// section, exactly as `record_on_file()` does. Nothing is left unguarded: the post is
		// accepted now, so a second acceptance is refused by the state test rather than by
		// the lock.
		self::unlock( $record );

		$option = self::rebuild(
			$record,
			self::airtable_block(
				$record,
				array(
					'status'           => self::AIRTABLE_ACCEPTED,
					'kind'             => self::airtable_kind( $kind ),
					'accepted_on'      => $today,
					'template_version' => $version,
				)
			)
		);

		self::mail_accepted( $record, $post_id );

		// An empty option means the rebuild found the lock held, by a sync working through
		// this very record, and skipped rather than raced it: both writes have landed and the
		// gate is still shut. The members are told either way, because the acceptance is real,
		// nothing sends that message a second time, and the next sync or Refresh opens the
		// account within the minute. What must not happen is the manager reading "the account
		// is open" over a gate that is not, so they are told what the on-file route tells them
		// in the same half-done state, which names the one thing left to do.
		if ( empty( $option ) ) {
			self::bounce( 'agreement-later' );
		}

		self::bounce( 'agreement-accepted' );
	}

	/**
	 * Send one submitted document back with a note (T6).
	 *
	 * The note is required and mailed verbatim, with reply-to the manager who wrote it: an
	 * institution told only that its agreement came back learns nothing, and the person who
	 * can answer the question it will ask is the one who sent it.
	 */
	public static function handle_return() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'wpcpm_agreement_post' );

		check_admin_referer( self::ACTION_RETURN . '_' . $post_id );

		$post = self::submitted_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'agreement-gone' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$note   = self::posted_note();
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $note ) : strlen( $note );

		if ( $length < self::MIN_NOTE || $length > self::MAX_NOTE ) {
			self::bounce( 'agreement-note' );
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		// Read again under the lock, for the reason `handle_accept()` gives.
		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-gone' );
		}

		$summary = self::summary( $record );

		// T6: the status is `Returned` unless an accepted agreement stands. Sending a
		// replacement back must not tell the base that the copy in force was returned.
		if ( empty( $summary['agreement_id'] ) ) {
			$settings = WPCPM_Settings::get();
			$fields   = WPCPM_Institutions_Sync::fields();
			$airtable = new WPCPM_Airtable( $settings );
			$written  = $airtable->update_records(
				$settings['institutions_table'],
				array(
					array(
						'id'     => $record,
						'fields' => array( $fields['agr_status'] => self::AIRTABLE_RETURNED ),
					),
				)
			);

			if ( is_wp_error( $written ) || empty( $written ) ) {
				self::unlock( $record );
				self::bounce( 'agreement-airtable' );
			}
		}

		$today = wp_date( 'Y-m-d' );

		update_post_meta( $post_id, self::META_STATE, self::STATE_RETURNED );
		update_post_meta( $post_id, self::META_NOTE, $note );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		self::add_event( $post_id, self::EVENT_RETURNED );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_RETURN,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'A signed Collaboration Agreement was returned to the institution with a note.', 'wpcredits-program-manager' ),
				'data'        => array( 'replacement' => empty( $summary['agreement_id'] ) ? 0 : 1 ),
			)
		);

		self::unlock( $record );

		self::rebuild(
			$record,
			self::airtable_block( $record, empty( $summary['agreement_id'] ) ? array( 'status' => self::AIRTABLE_RETURNED ) : array() )
		);

		self::mail_returned( $record, $post_id, $note );

		self::bounce( 'agreement-returned' );
	}

	/**
	 * Take an accepted agreement out of force, and close the account with it (T8).
	 *
	 * The one action on this page that takes access away, and three things about it are
	 * different from every other transition here because of that.
	 *
	 * **Airtable first, and the whole thing refused when it fails.** Acceptance's rule read
	 * the other way round, for the same reason: the base is the program's record of this
	 * state, and an institution locked out on the site while the base still says `Accepted`
	 * is a partner whose access was removed by a system nobody at the program can see it in.
	 * If the PATCH lands and this request dies before the post changes, the two sides
	 * disagree, which the predicate reads as locked and the manager screen lists by name;
	 * pressing Revoke again writes the same cell and completes.
	 *
	 * **The stage is not touched.** `Not Moving Forward` is the program saying the
	 * partnership is over, and a revoked agreement is not that: it is one document out of
	 * force, which is as often the prelude to a new one as to an ending. The dialog says so
	 * and asks the manager to change the stage themselves if the partnership has ended.
	 *
	 * **The option is deleted rather than rewritten.** Deleting is what closes the gate on
	 * this request: `option()` finds nothing, `is_settled()` is false on the next line, and
	 * the member's next page load is the agreement panel. A rewritten row would say the same
	 * thing right up until the write is lost, and a lost write has to leave an institution
	 * locked rather than open, which is the direction every failure in this class falls.
	 *
	 * The note is required and mailed verbatim. "Your access has been removed" with no reason
	 * is what makes somebody ring a program manager, and there is nowhere else the
	 * institution can read why.
	 */
	public static function handle_revoke() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'wpcpm_agreement_post' );

		check_admin_referer( self::ACTION_REVOKE . '_' . $post_id );

		$post = self::document_in_state( $post_id, self::STATE_ACCEPTED );

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'agreement-not-accepted' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$note   = self::posted_note();
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $note ) : strlen( $note );

		// Ahead of the lock, because this refusal needs nothing but the posted string, and a
		// manager who pressed the button with an empty box must not find the institution's
		// record locked for five minutes by their typo.
		if ( $length < self::MIN_NOTE || $length > self::MAX_NOTE ) {
			self::bounce( 'agreement-revoke-note' );
		}

		// Read from the document's own meta and never from the form. The nonce is keyed to
		// the document, and a posted record would be this form's claim about what it is
		// acting on rather than the state the site is holding.
		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		// Read again under the lock, for the reason `handle_accept()` gives. Two managers
		// pressing the same button, or a replacement accepted in the gap, would otherwise
		// each revoke a document the other has already moved.
		if ( ! self::document_in_state( $post_id, self::STATE_ACCEPTED ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-accepted' );
		}

		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();
		$airtable = new WPCPM_Airtable( $settings );

		// T8: the status, and nothing else. Not the stage, not `Agreement Accepted On`, not
		// the kind. The document is still the one the program accepted and still says what it
		// said; what changed is that it is no longer in force.
		$written = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => array( $fields['agr_status'] => self::AIRTABLE_REVOKED ),
				),
			)
		);

		// An empty result is a refusal too: `update_records()` drops a record it cannot send
		// and answers with the ones it did, so "nothing was updated" must not read as success
		// on the path where success closes an account.
		if ( is_wp_error( $written ) || empty( $written ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$today = wp_date( 'Y-m-d' );

		update_post_meta( $post_id, self::META_STATE, self::STATE_REVOKED );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		// The note replaces whatever this document carried, which for a legacy row is the
		// manager's own "second folder, the 2025 copy". That is the right way round: section
		// 9 keeps one note per document, the panel prints it to the institution, and a note
		// written for a colleague is not one an institution should be reading.
		update_post_meta( $post_id, self::META_NOTE, $note );

		// The line that closes the gate, and it is here rather than after the log or the mail
		// for the same reason it is a delete and not a rewrite: nothing between this request
		// and the member's next page load may leave the account open.
		delete_option( self::option_name( $record ) );

		// Every invitation still outstanding goes with the access it would have granted. The
		// accept path asks the gate for itself as well, because a link already in somebody's
		// mailbox is not recalled by a database write; this is so a manager is not shown a
		// queue of pending invitations to an institution that cannot admit anybody.
		if ( class_exists( 'WPCPM_Institution_Invite' ) && method_exists( 'WPCPM_Institution_Invite', 'cancel_for_institution' ) ) {
			WPCPM_Institution_Invite::cancel_for_institution( $record );
		}

		self::add_event( $post_id, self::EVENT_REVOKED );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_REVOKE,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'An accepted Collaboration Agreement was revoked, and the institution\'s account closed.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'    => (string) get_post_meta( $post_id, self::META_KIND, true ),
					'members' => count( WPCPM_Institution_Members::members_of( $record ) ),
				),
			)
		);

		// No rebuild, so the lock is released with nothing left to do under it: an option
		// this handler deleted is one the next sync writes from the base, which now says
		// `Revoked`, and rebuilding here would put a row back for the sake of putting one
		// back.
		self::unlock( $record );

		self::mail_revoked( $record, $post_id, $note );

		self::bounce( 'agreement-revoked' );
	}

	/**
	 * Put the most recently revoked agreement back in force (T9).
	 *
	 * The abort that makes revoking a safe click. A manager who revokes the wrong institution,
	 * or revokes the right one and is then told the paperwork was in hand all along, has one
	 * button that undoes it rather than an upload the institution has to be asked for again.
	 *
	 * Airtable first, exactly as T8: the status goes back to what the document is, `On file`
	 * for a legacy row and `Accepted` for anything this site accepted, and a failed PATCH
	 * leaves both sides where they were. Then the post, then the option through `rebuild()`,
	 * which is what opens the gate on this request.
	 *
	 * Two things have to be true, and both are read from stored state rather than from the
	 * form: this is the institution's most recently revoked document, and no accepted one
	 * stands. The second is what keeps "an accepted one stands" meaning one document, which
	 * T3, T6 and T10 all rely on; the first is what stops a manager reinstating last year's
	 * agreement from a page drawn before this year's was revoked.
	 */
	public static function handle_reinstate() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'wpcpm_agreement_post' );

		check_admin_referer( self::ACTION_REINSTATE . '_' . $post_id );

		$post = self::document_in_state( $post_id, self::STATE_REVOKED );

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'agreement-not-revoked' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		// Read again under the lock, for the reason `handle_accept()` gives.
		if ( ! self::document_in_state( $post_id, self::STATE_REVOKED ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-not-revoked' );
		}

		// T9's "from" column, both halves of it, and both under the lock: the test and the
		// write that acts on it are one decision.
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

		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();
		$airtable = new WPCPM_Airtable( $settings );
		$written  = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => array( $fields['agr_status'] => $status ),
				),
			)
		);

		if ( is_wp_error( $written ) || empty( $written ) ) {
			self::unlock( $record );
			self::bounce( 'agreement-airtable' );
		}

		$today = wp_date( 'Y-m-d' );

		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );

		// Today, not the day it was first accepted. The revocation already overwrote that
		// date with its own, because a document carries one decision's note and date at a
		// time, and the honest answer to "in force since when" for a document that spent a
		// fortnight out of force is the day it came back. The event rows keep every step.
		update_post_meta( $post_id, self::META_DECIDED_AT, $today );

		self::add_event( $post_id, self::EVENT_REINSTATED );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_REINSTATE,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'A revoked Collaboration Agreement was reinstated, and the institution\'s account opened again.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'   => $kind,
					'status' => $status,
				),
			)
		);

		// Released before the rebuild, which takes the same lock for its own critical
		// section, exactly as `handle_accept()` does. Nothing is left unguarded: the post is
		// accepted now, so a second reinstatement is refused by the state test.
		self::unlock( $record );

		$option = self::rebuild( $record, self::airtable_block( $record, array( 'status' => $status ) ) );

		self::mail_reinstated( $record, $post_id );

		// A held lock means a sync was rebuilding this very record and skipped rather than
		// raced it. Both writes have landed and the gate is still shut, which is what the
		// manager is told rather than "the account is open", for the reason `handle_accept()`
		// gives at the same line.
		if ( empty( $option ) ) {
			self::bounce( 'agreement-later' );
		}

		self::bounce( 'agreement-reinstated' );
	}

	/**
	 * Take a submitted document back before anybody has read it (T4).
	 *
	 * A member's control, and a manager's on their behalf. The file goes at once rather than
	 * on the discard cron's schedule: withdrawing is what somebody does when they realise
	 * they attached the wrong document, and "it will be deleted within thirty days" is not
	 * the answer that person wants. Nothing is mailed, because nothing happened that anyone
	 * else needs to be told about, and the base was never told the document arrived in a way
	 * a withdrawal has to undo.
	 */
	public static function handle_withdraw() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You need to be signed in to withdraw an agreement.', 'wpcredits-program-manager' ), 403 );
		}

		$post_id = WPCPM_Request::posted_id( 'wpcpm_agreement_post' );

		check_admin_referer( self::ACTION_WITHDRAW . '_' . $post_id );

		$post = self::submitted_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			self::bounce( 'agreement-gone' );
		}

		// The subject is the document's own institution stamp, never a posted record: the one
		// refusal this handler has to make is a member of B withdrawing A's document, and a
		// posted field is exactly what such a request would carry.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, self::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );

		// The same lock accept and return take, for the same reason and from the other side:
		// a member pressing Withdraw in the second a manager presses Accept must lose or win
		// outright, never delete the file out from under an acceptance that is mid-flight.
		if ( ! self::lock( $record ) ) {
			self::bounce( 'agreement-busy' );
		}

		if ( ! self::submitted_post( $post_id ) instanceof WP_Post ) {
			self::unlock( $record );
			self::bounce( 'agreement-gone' );
		}

		update_post_meta( $post_id, self::META_STATE, self::STATE_WITHDRAWN );
		update_post_meta( $post_id, self::META_DECIDED_BY, get_current_user_id() );
		update_post_meta( $post_id, self::META_DECIDED_AT, wp_date( 'Y-m-d' ) );

		self::forget_file( $post_id );
		self::add_event( $post_id, self::EVENT_WITHDRAWN );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_WITHDRAW,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_CACHE,
				'message'     => __( 'A submitted Collaboration Agreement was withdrawn and its file deleted.', 'wpcredits-program-manager' ),
				'data'        => array(),
			)
		);

		self::unlock( $record );
		self::rebuild( $record, self::airtable_block( $record, array() ) );

		self::bounce( 'agreement-withdrawn' );
	}

	/**
	 * Every document waiting to be read, across every institution, oldest first.
	 *
	 * Oldest first because it is a queue somebody works through, and the institution that has
	 * waited longest is the one whose turn it is. The manager's screen reads this; nothing
	 * else does.
	 *
	 * @param int $limit Most rows to read. The queue card wants them all up to its own cap;
	 *                   the menu bubble wants only enough to know whether to print a number.
	 * @return int[] Post IDs.
	 */
	public static function awaiting_review( $limit = 200 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::POST_STATUS,
				'numberposts'      => (int) $limit > 0 ? (int) $limit : 200,
				'fields'           => 'ids',
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

		return array_map( 'intval', (array) $posts );
	}

	/**
	 * What the reviewer's checklist needs to know about one document.
	 *
	 * Facts only, in the shape the review block prints them: nothing here decides anything
	 * and nothing here is a URL. An unknown post answers with an empty array rather than a
	 * half-filled one, so a caller that forgot to check gets nothing to print.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return array
	 */
	public static function review_facts( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return array();
		}

		$record = (string) get_post_meta( $post_id, self::META_INSTITUTION, true );
		$row    = WPCPM_Institutions_Index::row( $record );
		$file   = self::file_of( $post_id );
		$author = $post->post_author ? get_userdata( (int) $post->post_author ) : false;
		$flags  = get_post_meta( $post_id, self::META_FLAGS, true );

		return array(
			'post_id'          => $post_id,
			'state'            => (string) get_post_meta( $post_id, self::META_STATE, true ),
			'kind'             => (string) get_post_meta( $post_id, self::META_KIND, true ),
			'name_on_document' => (string) get_post_meta( $post_id, self::META_NAME_ON_DOCUMENT, true ),
			'template_version' => (string) get_post_meta( $post_id, self::META_TEMPLATE_VERSION, true ),
			'institution'      => $record,
			'institution_name' => ( is_array( $row ) && '' !== trim( (string) $row['name'] ) ) ? trim( (string) $row['name'] ) : $record,
			'uploaded_by'      => $author instanceof WP_User ? $author->display_name : '',
			'uploaded_at'      => substr( (string) $post->post_date, 0, 10 ),
			'original_name'    => (string) get_post_meta( $post_id, self::META_ORIGINAL_NAME, true ),
			'flags'            => is_array( $flags ) ? array_values( $flags ) : array(),
			'size'             => null === $file ? 0 : (int) $file['size'],
			'members'          => count( WPCPM_Institution_Members::members_of( $record ) ),
		);
	}

	/**
	 * Forget the files of documents nobody is waiting on any more (T11). The daily cron body.
	 *
	 * Withdrawn and returned only. An accepted document is the agreement and is kept; a
	 * superseded one is the copy that was in force before it and is the answer to "what did
	 * we agree to last year"; a generated one has no file at all. What is left is the two
	 * states that mean "this file was a mistake or is being replaced", and holding somebody's
	 * signed paperwork for longer than there is a reason to is the thing being avoided.
	 *
	 * The post is kept in every case: the history of an institution's agreement is what the
	 * next manager reads, and a deleted row would make a return look like it never happened.
	 *
	 * Oldest first, documents that still have a file only, and paged until the query runs
	 * dry. All three are what make a run catch up rather than circle. A batch of the newest
	 * withdrawn and returned rows hands every run the same recent documents and leaves the
	 * oldest file on disk for ever, which is a retention rule that has quietly stopped
	 * retaining; asking only for rows that still have a file is what stops the front of an
	 * oldest-first queue filling with the ones this cron dealt with months ago; and paging is
	 * what carries a run past a page of documents it has to keep, so a busy month cannot hide
	 * the one file that is a year overdue behind it.
	 *
	 * The cursor counts the rows left in place rather than the rows read, because forgetting
	 * a file takes that document out of the query's own answer: counting reads would step
	 * over as many documents as were just dealt with. `DISCARD_PAGES` is the end of it: five
	 * thousand documents a night, which for a program with forty-two institutions is every
	 * one of them a hundred times over, and a cron that cannot say when it will end is worse
	 * than one that leaves the rest for tomorrow.
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

				if ( ! in_array( $state, array( self::STATE_WITHDRAWN, self::STATE_RETURNED ), true ) || self::decided_at_of( $post ) > $cut ) {
					++$offset;
					continue;
				}

				// A row whose file meta is there but says nothing readable: there is nothing to
				// delete and it stays in the answer, so the cursor steps over it like any other
				// document this pass leaves where it is.
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
	 * Tell the managers what has been waiting too long. The daily digest.
	 *
	 * Sent only when something is actually overdue, and at most once a day. A queue that is
	 * somebody's job needs the reminder more than the notice each upload sends, and a digest
	 * that arrives every morning saying nothing is one nobody opens on the morning it says
	 * something. The day is stamped only when a message goes out, so an item that becomes
	 * overdue at noon is not held until tomorrow by a silent run at dawn.
	 *
	 * @return int How many messages went out.
	 */
	public static function remind() {
		$today = wp_date( 'Y-m-d' );

		if ( (string) get_option( self::OPTION_REMINDED, '' ) === $today ) {
			return 0;
		}

		$days    = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$cut     = time() - ( $days * DAY_IN_SECONDS );
		$overdue = array();

		foreach ( self::awaiting_review() as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || self::post_time( $post ) > $cut ) {
				continue;
			}

			$facts = self::review_facts( $post_id );

			if ( ! empty( $facts ) ) {
				$overdue[] = $facts;
			}
		}

		if ( empty( $overdue ) ) {
			return 0;
		}

		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );

		$build = function () use ( $site, $overdue, $queue, $days ) {
			// One plural form and one number that never needs pluralising: the days are
			// written as "the 3-day review window" so that a translator has one rule to
			// follow rather than two counts crossing in one sentence.
			$lines = array(
				sprintf(
					/* translators: 1: number of agreements, 2: number of days in the review window. */
					_n(
						'%1$s signed agreement has been waiting longer than the %2$s-day review window.',
						'%1$s signed agreements have been waiting longer than the %2$s-day review window.',
						count( $overdue ),
						'wpcredits-program-manager'
					),
					number_format_i18n( count( $overdue ) ),
					number_format_i18n( $days )
				),
			);

			foreach ( $overdue as $facts ) {
				$lines[] = sprintf(
					/* translators: 1: institution name, 2: date the agreement was uploaded. */
					__( '%1$s, uploaded %2$s', 'wpcredits-program-manager' ),
					$facts['institution_name'],
					$facts['uploaded_at']
				);
			}

			$lines[] = $queue;

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: number of agreements. */
					_n(
						'[%1$s] %2$s signed agreement is waiting for review',
						'[%1$s] %2$s signed agreements are waiting for review',
						count( $overdue ),
						'wpcredits-program-manager'
					),
					$site,
					number_format_i18n( count( $overdue ) )
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		$sent = (int) WPCPM_Institutions::notify_managers( self::MAIL_REMINDER, $build );

		update_option( self::OPTION_REMINDED, $today, false );

		return $sent;
	}

	/**
	 * The courtesy scan, and it is presented as a courtesy everywhere it is shown.
	 *
	 * A bounded look for seven names, two of which refuse the file and five of which are
	 * recorded for the reviewer. What makes it worth writing at all is the two steps before
	 * the looking: a PDF may write `/Launch` as `/L#61unch`, and it may hide the whole
	 * object in a `FlateDecode` stream, so a scan that searched the raw bytes would pass a
	 * file any text editor could show you was hostile. Both escapes are undone first.
	 *
	 * What it is not is evidence. A token can be hidden in ways a bounded scan will not
	 * find - an object stream inside an object stream, a filter chain this does not
	 * implement, a name split across an indirect reference - and the reviewer's actual
	 * protection is download-never-inline plus their own viewer. The panel, the checklist and
	 * the tests all say so, in those words, because a "scanned: clean" badge would move
	 * somebody from "I will open this carefully" to "the site checked it".
	 *
	 * @param string $bytes The file's contents.
	 * @return array{ok: bool, reason: string, flags: string[]}
	 */
	public static function inspect_pdf( $bytes ) {
		$bytes    = (string) $bytes;
		$haystack = self::decode_names( $bytes );

		foreach ( self::inflated_streams( $bytes ) as $stream ) {
			$haystack .= "\n" . self::decode_names( $stream );
		}

		foreach ( self::SCAN_REFUSALS as $name => $reason ) {
			if ( self::names_contain( $haystack, $name ) ) {
				return array(
					'ok'     => false,
					'reason' => $reason,
					'flags'  => array(),
				);
			}
		}

		$flags = array();

		foreach ( self::SCAN_FLAGS as $name ) {
			if ( self::names_contain( $haystack, $name ) ) {
				$flags[] = $name;
			}
		}

		return array(
			'ok'     => true,
			'reason' => '',
			'flags'  => $flags,
		);
	}

	/**
	 * The last bulk recording's tally, for the screen.
	 *
	 * @return array|null
	 */
	public static function last_on_file_all() {
		$tally = get_option( self::OPTION_ON_FILE_ALL, null );

		return is_array( $tally ) ? $tally : null;
	}

	/**
	 * Delete every option, every lock and every post. Called on uninstall.
	 */
	public static function delete_all() {
		global $wpdb;

		// Locks share the option prefix, so one pattern removes both.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Options are addressable only by exact name; there is one per institution. Uninstall only.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) );

		foreach ( (array) $names as $name ) {
			delete_option( (string) $name );
		}

		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * The record IDs that have a stored option.
	 *
	 * The prefix is shared with the lock rows and, later, with `wpcpm_agreement_drift`, so
	 * the suffix is checked for the record-ID shape: that is what keeps a lock from being
	 * listed as an institution with an unreadable row.
	 *
	 * Public because the sync's revoke phase closes the gate on every institution that has
	 * left the active stages, and it needs the list of institutions that have a gate at all.
	 *
	 * @return string[]
	 */
	public static function stored_records() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Options are addressable only by exact name; there is one per institution and the screen reads them once.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) );

		$records = array();

		foreach ( (array) $names as $name ) {
			$suffix = substr( (string) $name, strlen( self::OPTION_PREFIX ) );

			if ( WPCPM_Mentors_Sync::is_record_id( $suffix ) ) {
				$records[] = $suffix;
			}
		}

		return $records;
	}

	/**
	 * Check a stored value against the option's shape.
	 *
	 * @param mixed $stored Whatever `get_option()` returned.
	 * @return array|null The row with every field present and typed, or null.
	 */
	private static function validate( $stored ) {
		if ( ! is_array( $stored ) ) {
			return null;
		}

		if ( (int) ( isset( $stored['v'] ) ? $stored['v'] : 0 ) !== self::VERSION ) {
			return null;
		}

		if ( ! isset( $stored['settled'], $stored['site_state'], $stored['airtable_status'] ) ) {
			return null;
		}

		if ( ! is_bool( $stored['settled'] ) || ! is_string( $stored['site_state'] ) || ! is_string( $stored['airtable_status'] ) ) {
			return null;
		}

		$states = array(
			self::SUMMARY_NONE,
			self::SUMMARY_GENERATED,
			self::SUMMARY_SUBMITTED,
			self::SUMMARY_RETURNED,
			self::SUMMARY_REVOKED,
			self::SUMMARY_ACCEPTED,
			self::SUMMARY_ON_FILE,
		);

		if ( ! in_array( $stored['site_state'], $states, true ) ) {
			return null;
		}

		// A flag that disagrees with its own two sides is corruption, not a decision.
		if ( self::settles( $stored['site_state'], $stored['airtable_status'] ) !== $stored['settled'] ) {
			return null;
		}

		return array(
			'v'               => self::VERSION,
			'settled'         => $stored['settled'],
			'site_state'      => $stored['site_state'],
			'airtable_status' => $stored['airtable_status'],
			'kind'            => isset( $stored['kind'] ) ? (string) $stored['kind'] : '',
			'agreement_id'    => isset( $stored['agreement_id'] ) ? (int) $stored['agreement_id'] : 0,
			'pending_id'      => isset( $stored['pending_id'] ) ? (int) $stored['pending_id'] : 0,
			'generated_id'    => isset( $stored['generated_id'] ) ? (int) $stored['generated_id'] : 0,
			'accepted_at'     => isset( $stored['accepted_at'] ) ? (string) $stored['accepted_at'] : '',
			'drive_url'       => isset( $stored['drive_url'] ) ? (string) $stored['drive_url'] : '',
			'updated'         => isset( $stored['updated'] ) ? (int) $stored['updated'] : 0,
		);
	}

	/**
	 * The one rule: both sides settled, or not settled.
	 *
	 * @param string $site_state      A `SUMMARY_*` value.
	 * @param string $airtable_status An `Agreement Status` value.
	 * @return bool
	 */
	private static function settles( $site_state, $airtable_status ) {
		return in_array( (string) $site_state, self::SETTLED_STATES, true )
			&& in_array( (string) $airtable_status, self::AIRTABLE_SETTLED, true );
	}

	/**
	 * Every field of an Airtable block as a trimmed string, missing ones empty.
	 *
	 * @param array $airtable The block as passed in.
	 * @return array
	 */
	private static function normalise( array $airtable ) {
		$out = array();

		foreach ( array( 'status', 'kind', 'accepted_on', 'signed_on', 'accepted_by', 'document', 'submitted_on', 'template_version' ) as $key ) {
			$out[ $key ] = isset( $airtable[ $key ] ) && is_scalar( $airtable[ $key ] ) ? trim( (string) $airtable[ $key ] ) : '';
		}

		return $out;
	}

	/**
	 * The site half of the answer, from the posts.
	 *
	 * An accepted post wins whatever else exists, since a replacement upload sits beside it
	 * without unseating it. Otherwise a submitted post means "in review". Otherwise the most
	 * recent returned, revoked or generated post names the state; withdrawn and superseded
	 * posts are history and say nothing on their own.
	 *
	 * @param WP_Post[] $posts The institution's posts, newest first.
	 * @return array{site_state: string, kind: string, agreement_id: int, pending_id: int, generated_id: int, accepted_at: string, drive_url: string, route: string}
	 */
	private static function site_summary( array $posts ) {
		$accepted  = null;
		$pending   = 0;
		$generated = 0;
		$latest    = '';

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$state = (string) get_post_meta( $post->ID, self::META_STATE, true );

			if ( self::STATE_ACCEPTED === $state && null === $accepted ) {
				$accepted = $post;
			} elseif ( self::STATE_SUBMITTED === $state && ! $pending ) {
				$pending = (int) $post->ID;
			} elseif ( self::STATE_GENERATED === $state && ! $generated ) {
				$generated = (int) $post->ID;
			}

			if ( '' === $latest && in_array( $state, array( self::STATE_RETURNED, self::STATE_REVOKED, self::STATE_GENERATED ), true ) ) {
				$latest = $state;
			}
		}

		$out = array(
			'site_state'   => self::SUMMARY_NONE,
			'kind'         => '',
			'agreement_id' => 0,
			'pending_id'   => $pending,
			'generated_id' => $generated,
			'accepted_at'  => '',
			'drive_url'    => '',
			'route'        => '',
		);

		if ( $accepted instanceof WP_Post ) {
			$kind  = (string) get_post_meta( $accepted->ID, self::META_KIND, true );
			$drive = (string) get_post_meta( $accepted->ID, self::META_DRIVE_URL, true );

			$out['site_state']   = self::KIND_LEGACY === $kind ? self::SUMMARY_ON_FILE : self::SUMMARY_ACCEPTED;
			$out['kind']         = $kind;
			$out['agreement_id'] = (int) $accepted->ID;
			$out['accepted_at']  = self::accepted_at_of( $accepted );
			$out['drive_url']    = self::is_drive_link( $drive ) ? $drive : '';
			$out['route']        = self::was_recorded_in_airtable( $accepted->ID ) ? 'grid' : 'site';

			return $out;
		}

		if ( $pending ) {
			$out['site_state'] = self::SUMMARY_SUBMITTED;
		} elseif ( '' !== $latest ) {
			// The three states spell the same as their summaries.
			$out['site_state'] = $latest;
		}

		return $out;
	}

	/**
	 * The date an accepted post was accepted, Y-m-d.
	 *
	 * The decision date when the post records one, the post's own date otherwise.
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
	 * Whether a post is one the rebuild materialised from the grid.
	 *
	 * Read from the event rows rather than `post_author` or the kind. A manager recording an
	 * agreement on file by hand (a later phase) also makes a legacy post, so the kind cannot
	 * tell the two apart, and an author of 0 is incidental where the event row is the one
	 * thing written for exactly this purpose.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function was_recorded_in_airtable( $post_id ) {
		foreach ( (array) get_post_meta( (int) $post_id, self::META_EVENT, false ) as $event ) {
			if ( is_array( $event ) && isset( $event['event'] ) && self::EVENT_RECORDED === $event['event'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stand a legacy post up for a grid-recorded `On file` row.
	 *
	 * Author 0 and one `recorded in Airtable` event: the site did not accept anything, it
	 * noted that the program already had. The signed date comes from the base when it holds
	 * one; the decision date is the base's `Agreement Accepted On`, or today when the manager
	 * did not fill it, so the panel's "accepted on" line never reads blank.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $airtable  The normalised block.
	 * @return int The new post's ID, or 0.
	 */
	private static function materialise_legacy( $record_id, array $airtable ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
				'post_author' => 0,
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the institution. */
					__( 'Collaboration Agreement on file (%s)', 'wpcredits-program-manager' ),
					$record_id
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id  = (int) $post_id;
		$accepted = self::date_or_empty( $airtable['accepted_on'] );

		update_post_meta( $post_id, self::META_INSTITUTION, $record_id );
		update_post_meta( $post_id, self::META_STATE, self::STATE_ACCEPTED );
		update_post_meta( $post_id, self::META_KIND, self::KIND_LEGACY );
		update_post_meta( $post_id, self::META_DRIVE_URL, esc_url_raw( $airtable['document'] ) );
		update_post_meta( $post_id, self::META_SIGNED_ON, self::date_or_empty( $airtable['signed_on'] ) );
		update_post_meta( $post_id, self::META_DECIDED_BY, 0 );
		update_post_meta( $post_id, self::META_DECIDED_AT, '' !== $accepted ? $accepted : wp_date( 'Y-m-d' ) );

		if ( '' !== $airtable['template_version'] ) {
			update_post_meta( $post_id, self::META_TEMPLATE_VERSION, sanitize_text_field( $airtable['template_version'] ) );
		}

		add_post_meta(
			$post_id,
			self::META_EVENT,
			array(
				'event' => self::EVENT_RECORDED,
				'at'    => time(),
				'actor' => 0,
			)
		);

		return $post_id;
	}

	/**
	 * A value as a Y-m-d date, or empty when it is not one.
	 *
	 * Airtable date fields arrive as `YYYY-MM-DD`; anything else, including a datetime, is
	 * cut to its first ten characters and must still be a real date.
	 *
	 * @param mixed $value The value to test.
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
	 * Take the rebuild lock for an institution.
	 *
	 * `add_option()` is the test-and-set: it returns false when the row already exists, and
	 * it is one INSERT, so a sync tick and a manager's Refresh racing for the same record
	 * cannot both write. A lock older than `LOCK_TIMEOUT` belonged to a request that died
	 * between taking and releasing it, and is cleared, since otherwise that one institution
	 * could never be rebuilt again.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $record_id ) {
		$key  = self::lock_name( $record_id );
		$held = get_option( $key );

		if ( false !== $held && ( time() - (int) $held ) > self::LOCK_TIMEOUT ) {
			delete_option( $key );
		}

		return (bool) add_option( $key, time(), '', false );
	}

	/**
	 * Release the rebuild lock.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	private static function unlock( $record_id ) {
		delete_option( self::lock_name( $record_id ) );
	}

	/**
	 * Return from the on-file handler with a one-shot outcome, and stop.
	 *
	 * The form is drawn in two places, the manager's institution row and the dashboard's
	 * agreement panel, so the destination is where the request came from rather than one
	 * fixed screen; `wp_safe_redirect()` keeps that to this site, and a request with no
	 * referer lands on the Institutions screen, which is where the row is. The words for
	 * each outcome live in `WPCPM_Institution_Panel::messages()`, once, because both screens
	 * print them.
	 *
	 * @param string $status Outcome slug.
	 */
	private static function bounce_on_file( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=wpcpm-institutions' ) );
		exit;
	}

	/**
	 * Which institution this request acts for.
	 *
	 * A member's own stamp wins, always. The posted record is the manager's on-behalf form
	 * saying which row it was drawn on, and honouring it for a member would make a hidden
	 * input the fence rather than the membership. A manager with neither a posted record nor
	 * a membership falls through to the switcher, which is where `resolve_institution()`
	 * keeps that logic for every other screen in the module.
	 *
	 * The answer is what the nonce is keyed to, so a form drawn for one institution cannot be
	 * replayed at another whatever the posted field says.
	 *
	 * @return string Institutions record ID, or ''.
	 */
	private static function record_for_request() {
		$viewer = wp_get_current_user();
		$own    = WPCPM_Institution_Members::institution_of( $viewer );

		if ( '' !== $own ) {
			return $own;
		}

		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

		if ( $can_manage ) {
			$asked = WPCPM_Request::posted_text( 'wpcpm_agreement_record' );

			if ( WPCPM_Institutions_Index::has( $asked ) ) {
				return trim( $asked );
			}
		}

		return WPCPM_Institution_Roster::resolve_institution( $viewer, $can_manage );
	}

	/**
	 * How many uploads one institution gets a day.
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
	 * `$_FILES` is the one superglobal a form field cannot be read out of with
	 * `WPCPM_Request`, so it is read here, once, and each member is cast on the way out. The
	 * name is sanitised and capped because it is kept for display beside the reviewer's
	 * checklist; it is never used on disk and never put in a header.
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
		if ( ! isset( $_FILES['wpcpm_agreement_file'] ) || ! is_array( $_FILES['wpcpm_agreement_file'] ) ) {
			return $empty;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above for the nonce; every member is cast or sanitised on the lines below, which is the only sanitising an upload admits. What makes the bytes safe is the six checks `handle_upload()` runs over them, not a filter on this array.
		$raw = wp_unslash( $_FILES['wpcpm_agreement_file'] );

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
	 * `is_uploaded_file()` is what stops a posted string naming `wp-config.php` from being
	 * read out of the filesystem and stored as somebody's agreement, and it is the check
	 * being made here. It answers from PHP's own list of the files this request received,
	 * which is empty under CLI, where the test suite runs and where no `admin_post_` action
	 * can fire at all: `admin-post.php` is a web endpoint, so the branch below is unreachable
	 * from any request that could reach this handler.
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
	 * Read an uploaded temporary file into memory.
	 *
	 * Bounded by the size check the caller has already made, and read once: the magic bytes,
	 * the courtesy scan and `store()` all work on this one copy rather than opening the file
	 * three times and risking three different answers.
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
	 * What the fileinfo extension says a file is, or '' when the host has none.
	 *
	 * @param string $path Path to inspect.
	 * @return string A MIME type, or ''.
	 */
	private static function mime_of( $path ) {
		if ( ! function_exists( 'finfo_open' ) ) {
			return '';
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );

		if ( ! $finfo ) {
			return '';
		}

		$type = finfo_file( $finfo, $path );

		// No `finfo_close()`: it has been a no-op since PHP 8.0, where the resource became an
		// object that is freed when this method returns, and the standard flags the call as
		// deprecated. Letting `$finfo` go out of scope is the supported way to close it.
		unset( $finfo );

		return is_string( $type ) ? $type : '';
	}

	/**
	 * Undo PDF's `#xx` name escapes.
	 *
	 * A name in a PDF may be written with any of its characters as `#` and two hex digits, so
	 * `/Launch` is also `/L#61unch` and `/#4C#61#75#6E#63#68`. A scan that searched the raw
	 * bytes would pass every one of those, which is why this runs first and over both the
	 * raw file and each inflated stream.
	 *
	 * @param string $bytes Anything to search.
	 * @return string
	 */
	private static function decode_names( $bytes ) {
		return (string) preg_replace_callback(
			'/#([0-9A-Fa-f]{2})/',
			static function ( $escape ) {
				return chr( hexdec( $escape[1] ) );
			},
			(string) $bytes
		);
	}

	/**
	 * Whether a haystack names a PDF name, and not merely a longer one beginning with it.
	 *
	 * `/JS` must not match inside `/JSomething`, and `/AA` must not match `/AArgh`. A PDF
	 * name ends at a delimiter, so the test is the name followed by anything that is not a
	 * name character.
	 *
	 * @param string $haystack Decoded bytes.
	 * @param string $name     The name, leading slash included.
	 * @return bool
	 */
	private static function names_contain( $haystack, $name ) {
		return 1 === preg_match( '/' . preg_quote( (string) $name, '/' ) . '(?![0-9A-Za-z])/', (string) $haystack );
	}

	/**
	 * Every `FlateDecode` stream in a file, inflated, within a budget.
	 *
	 * The interesting half of the scan. A hostile PDF does not write `/Launch` where a text
	 * search would find it; it writes it inside a compressed object stream. Each `stream`
	 * keyword is found (never `endstream`, which the lookbehind excludes), the dictionary in
	 * front of it is checked for `/FlateDecode`, and what follows is inflated.
	 *
	 * The budget is the other half. A few hundred bytes of zlib can expand into gigabytes, so
	 * a scan meant to protect the reviewer must not become the way to exhaust the site's
	 * memory: at most `SCAN_MAX_STREAMS` streams, at most `SCAN_MAX_STREAM` bytes out of each
	 * and `SCAN_MAX_TOTAL` in all. A stream that would exceed its own bound is skipped rather
	 * than partly read, and this is one of the bounds the docblock on `inspect_pdf()` means
	 * when it says the scan is a courtesy and not evidence.
	 *
	 * @param string $bytes The file's contents.
	 * @return string[] The inflated streams, in file order.
	 */
	private static function inflated_streams( $bytes ) {
		$out   = array();
		$total = 0;

		if ( ! preg_match_all( '/(?<![A-Za-z])stream(\r\n|\r|\n)?/', $bytes, $hits, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}

		foreach ( $hits[0] as $index => $hit ) {
			if ( $index >= self::SCAN_MAX_STREAMS || $total >= self::SCAN_MAX_TOTAL ) {
				break;
			}

			$at   = (int) $hit[1];
			$back = min( 2048, $at );

			// A window rather than a parser. A filter named further back than this is one
			// this scan does not claim to find.
			if ( ! self::names_contain( self::decode_names( substr( $bytes, $at - $back, $back ) ), '/FlateDecode' ) ) {
				continue;
			}

			$from  = $at + strlen( $hit[0] );
			$end   = strpos( $bytes, 'endstream', $from );
			$body  = false === $end ? substr( $bytes, $from ) : substr( $bytes, $from, $end - $from );
			$plain = self::inflate( $body );

			if ( '' === $plain ) {
				continue;
			}

			$out[]  = $plain;
			$total += strlen( $plain );
		}

		return $out;
	}

	/**
	 * Inflate one stream body, bounded, whichever of the two shapes it is in.
	 *
	 * `gzuncompress()` reads the zlib wrapper nearly every producer writes; `gzinflate()`
	 * reads the raw deflate a few write instead. Both answer false for data they cannot read,
	 * which for a PDF full of images is the normal case rather than an error, so both are
	 * silenced. Both are given the output bound, and both answer false rather than a
	 * truncated string when the stream would exceed it.
	 *
	 * @param string $body The compressed bytes.
	 * @return string The inflated bytes, or '' when there are none to be had.
	 */
	private static function inflate( $body ) {
		if ( '' === $body || ! function_exists( 'gzuncompress' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A stream this scan cannot read is a normal part of a normal PDF, not a condition worth a warning in the host's log on every upload.
		$plain = @gzuncompress( $body, self::SCAN_MAX_STREAM );

		if ( ! is_string( $plain ) || '' === $plain ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- As above, for the producers that write a raw deflate stream with no zlib header.
			$plain = @gzinflate( $body, self::SCAN_MAX_STREAM );
		}

		return is_string( $plain ) ? $plain : '';
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
		$post = get_post( absint( $post_id ) );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		if ( self::STATE_SUBMITTED !== (string) get_post_meta( $post->ID, self::META_STATE, true ) ) {
			return null;
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( get_post_meta( $post->ID, self::META_INSTITUTION, true ) ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * One of this class's documents in the state a handler expects to find it, or null.
	 *
	 * `submitted_post()`'s sibling for the two states Phase 4 acts on: an accepted document
	 * to revoke, a revoked one to put back. The three questions are the same three, and each
	 * has to be asked again here. Another post type is somebody else's row; a state that has
	 * moved since the page was drawn is a second manager having pressed the button first; and
	 * an institution meta that is not a record ID is a document this handler must not carry
	 * into a PATCH, because that string is what names the row Airtable would write.
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

		$stored = (string) get_post_meta( $post->ID, self::META_STATE, true );

		if ( (string) $state !== $stored ) {
			return null;
		}

		if ( ! WPCPM_Mentors_Sync::is_record_id( get_post_meta( $post->ID, self::META_INSTITUTION, true ) ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * The institution's most recently revoked document, or 0.
	 *
	 * `posts_for()` is newest first, so the first revoked row it hands back is the one the
	 * manager's Reinstate button is drawn against. Reinstating any other would put an older
	 * agreement back in force under a newer one's revocation, which is a state no screen in
	 * this module knows how to describe.
	 *
	 * @param string $record Institutions record ID.
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
	 * The stored file a document points at, or null when it has none.
	 *
	 * A generated post and a legacy row both have none, and both are legitimate; the caller
	 * decides whether that is a 404 or a fact for the checklist.
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
	 * pointing at bytes that are not there any more is what would make the panel offer a
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
	 * Retire the generated templates an upload has just answered, and say which version.
	 *
	 * A template the institution generated and then signed is the same agreement, so the
	 * generated row stops being an outstanding step. The version comes back so the uploaded
	 * copy can carry it: that is what lets the reviewer's checklist say which template the
	 * paper in front of them was cut from, on a copy that arrived as a scan with nothing
	 * machine-readable in it.
	 *
	 * @param string $record Institutions record ID.
	 * @param int    $except The new post, which is not to be touched.
	 * @return string The newest generated document's template version, or ''.
	 */
	private static function supersede_generated( $record, $except ) {
		$version = '';

		foreach ( self::posts_for( $record ) as $post ) {
			if ( (int) $post->ID === (int) $except ) {
				continue;
			}

			if ( self::STATE_GENERATED !== (string) get_post_meta( $post->ID, self::META_STATE, true ) ) {
				continue;
			}

			// `posts_for()` is newest first, so the first one found is the one they printed.
			if ( '' === $version ) {
				$version = (string) get_post_meta( $post->ID, self::META_TEMPLATE_VERSION, true );
			}

			self::supersede( (int) $post->ID );
		}

		return $version;
	}

	/**
	 * How the base spells one of this class's kinds.
	 *
	 * @param string $kind A `KIND_*` value.
	 * @return string The `Agreement Kind` choice, or '' for a kind the base has no word for.
	 */
	private static function airtable_kind( $kind ) {
		$map = array(
			self::KIND_TEMPLATE => self::AIRTABLE_KIND_TEMPLATE,
			self::KIND_OWN      => self::AIRTABLE_KIND_OWN,
			self::KIND_LEGACY   => self::AIRTABLE_KIND_LEGACY,
		);

		return isset( $map[ (string) $kind ] ) ? $map[ (string) $kind ] : '';
	}

	/**
	 * The Airtable block to rebuild an option from, after a transition wrote part of one.
	 *
	 * `rebuild()` wants the record's whole agreement block and a handler knows only the cells
	 * it just wrote. What the option already holds stands in for the rest, so a transition
	 * that changed the status does not quietly forget the Drive link a legacy row settled on.
	 * A handler whose Airtable write failed passes no changes at all and gets the state the
	 * base is still in, which is the state the sync will find on its next pass.
	 *
	 * @param string $record  Institutions record ID.
	 * @param array  $changed The cells this request wrote, in `rebuild()`'s vocabulary.
	 * @return array
	 */
	private static function airtable_block( $record, array $changed ) {
		$option = self::option( $record );

		return array_merge(
			array(
				'status'   => null === $option ? '' : $option['airtable_status'],
				'document' => null === $option ? '' : $option['drive_url'],
			),
			$changed
		);
	}

	/**
	 * Whether a stage read from the base comes before `Confirmed`.
	 *
	 * Forward only, and never off the end: an institution already at `Student` is not moved
	 * back to `Confirmed` by an acceptance. An empty stage counts as preceding, because a
	 * record nothing has moved yet is at the start of the list rather than off it. A stage
	 * the list does not know is not moved at all: this class does not guess where somebody
	 * else's word belongs.
	 *
	 * @param string $stage The `Current Stage` value read live.
	 * @return bool
	 */
	private static function precedes_confirmed( $stage ) {
		$stage = trim( (string) $stage );

		if ( '' === $stage ) {
			return true;
		}

		$at     = array_search( $stage, self::STAGE_ORDER, true );
		$target = array_search( self::STAGE_CONFIRMED, self::STAGE_ORDER, true );

		return false !== $at && false !== $target && $at < $target;
	}

	/**
	 * The unix time a document's decision was made, falling back to when it arrived.
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
	 * Read from `post_date` rather than `post_date_gmt` because the stand-ins the suite runs
	 * against carry one date, and because every comparison this feeds is in days: an offset
	 * of a few hours cannot move an item across a three-day or a thirty-day line in a way
	 * that matters to anybody.
	 *
	 * @param WP_Post $post The document.
	 * @return int
	 */
	private static function post_time( WP_Post $post ) {
		$time = strtotime( (string) $post->post_date . ' UTC' );

		return false === $time ? 0 : (int) $time;
	}

	/**
	 * The filename a download is offered under.
	 *
	 * The institution and the date, from what this site holds. Never
	 * `_wpcpm_agr_original_name`: that string is the one thing on this path an outsider chose,
	 * and a header is exactly where a chosen string should not end up.
	 *
	 * @param WP_Post $post The document.
	 * @return string
	 */
	private static function download_name( WP_Post $post ) {
		$record = (string) get_post_meta( $post->ID, self::META_INSTITUTION, true );
		$row    = WPCPM_Institutions_Index::row( $record );
		$name   = ( is_array( $row ) && '' !== trim( (string) $row['name'] ) ) ? trim( (string) $row['name'] ) : $record;
		$slug   = sanitize_title( $name );

		if ( '' === $slug ) {
			$slug = 'collaboration-agreement';
		}

		return sanitize_file_name( $slug . '-' . substr( (string) $post->post_date, 0, 10 ) ) . '.pdf';
	}

	/**
	 * The download link this request was making, for the login form to come back to.
	 *
	 * Rebuilt from the three arguments the route takes rather than read out of
	 * `REQUEST_URI`, so the host in it is this site's and not a header somebody sent.
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
	 * Which document a download link names.
	 *
	 * The download is a `wp_nonce_url()` link rather than a form, so its arguments arrive in
	 * the query string and `WPCPM_Request::id()` is the right reader for them. It is the
	 * wrong reader inside a handler that receives a posted form, and `bin/check-references.php`
	 * says so of any handler that checks a nonce, which is why the read is named here instead
	 * of sitting in `handle_download()`'s body.
	 *
	 * @return int
	 */
	private static function requested_document() {
		return WPCPM_Request::id( 'post' );
	}

	/**
	 * The note a manager typed, with its line breaks.
	 *
	 * `WPCPM_Request` has no reader for a textarea, and `sanitize_text_field()` would fold
	 * the paragraphs of a note that is mailed verbatim into one line.
	 *
	 * @return string
	 */
	private static function posted_note() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The calling handler verifies the nonce before reaching here.
		if ( ! isset( $_POST['wpcpm_agreement_note'] ) || ! is_scalar( $_POST['wpcpm_agreement_note'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		return trim( sanitize_textarea_field( wp_unslash( $_POST['wpcpm_agreement_note'] ) ) );
	}

	/**
	 * Send one message to every live member of an institution.
	 *
	 * Every member at every step, because equal members should not learn from a colleague
	 * that the account is open, or that it is not.
	 *
	 * @param string   $record  Institutions record ID.
	 * @param string   $context Mail context, for the log.
	 * @param callable $build   Builder in `WPCPM_Mail::send()`'s shape.
	 * @return int How many were sent.
	 */
	private static function mail_members( $record, $context, $build ) {
		$sent = 0;

		foreach ( WPCPM_Institution_Members::members_of( $record ) as $member ) {
			$sent += WPCPM_Mail::send( $member, $context, $build ) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * Tell the institution its signed agreement arrived (T3, T10).
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The submitted document.
	 * @return int How many members were mailed.
	 */
	private static function mail_received( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site = WPCPM_Mail::site_name();
		$days = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );
		$who  = '' === $facts['uploaded_by'] ? __( 'a member of your institution', 'wpcredits-program-manager' ) : $facts['uploaded_by'];

		$build = function () use ( $site, $facts, $days, $who ) {
			$lines = array(
				sprintf(
					/* translators: 1: date the file arrived, 2: who uploaded it. */
					__( 'We received your signed Collaboration Agreement on %1$s, uploaded by %2$s.', 'wpcredits-program-manager' ),
					$facts['uploaded_at'],
					$who
				),
				sprintf(
					/* translators: %s: number of working days. */
					_n(
						'A program manager will read it and you will get an email either way, usually within %s working day.',
						'A program manager will read it and you will get an email either way, usually within %s working days.',
						$days,
						'wpcredits-program-manager'
					),
					number_format_i18n( $days )
				),
				__( 'If it was the wrong file, withdraw it from the agreement panel on the site and upload the right one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] We received your signed agreement', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_RECEIVED, $build );
	}

	/**
	 * Tell the managers that a signed agreement is waiting to be read.
	 *
	 * One per upload. The reminder digest is what carries the queue after this; a first
	 * notice that never came would make the digest read as a surprise.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The submitted document.
	 * @return int How many managers were mailed.
	 */
	private static function mail_landed( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site  = WPCPM_Mail::site_name();
		$queue = admin_url( 'admin.php?page=wpcpm-institutions' );

		$build = function () use ( $site, $facts, $queue ) {
			$lines = array(
				sprintf(
					/* translators: 1: institution name, 2: date, 3: who uploaded it. */
					__( '%1$s uploaded a signed Collaboration Agreement on %2$s (%3$s).', 'wpcredits-program-manager' ),
					$facts['institution_name'],
					$facts['uploaded_at'],
					'' === $facts['uploaded_by'] ? $facts['institution'] : $facts['uploaded_by']
				),
				$facts['flags']
					? sprintf(
						/* translators: %s: comma-separated list of PDF feature names. */
						__( 'The courtesy scan noticed: %s. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
						implode( ', ', $facts['flags'] )
					)
					: __( 'The courtesy scan noticed nothing. It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.', 'wpcredits-program-manager' ),
				$queue,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] Signed agreement from %2$s is waiting for review', 'wpcredits-program-manager' ),
					$site,
					$facts['institution_name']
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return (int) WPCPM_Institutions::notify_managers( self::MAIL_LANDED, $build );
	}

	/**
	 * Tell the institution its agreement was accepted, and its account is open (T5).
	 *
	 * The link is to the dashboard, not to the file: a download link carries a nonce, a nonce
	 * belongs to the account it was made for, and this one message goes to every member. The
	 * card at the foot of the dashboard draws each of them a link that is theirs.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The accepted document.
	 * @return int How many members were mailed.
	 */
	private static function mail_accepted( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was accepted. */
					__( 'Your Collaboration Agreement was accepted on %s. Your account on the site is open.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'You can now see your students and their progress, open one student to read their Student Report Card, and download your own copy of the signed agreement from the card at the foot of the page.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement is accepted', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_ACCEPTED, $build );
	}

	/**
	 * Send the institution the manager's note, word for word (T6).
	 *
	 * Verbatim, and with reply-to the manager who wrote it: an institution told only that
	 * its agreement came back learns nothing, and the person who can answer the question it
	 * will ask is the one who sent it.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The returned document.
	 * @param string $note    The note, as typed.
	 * @return int How many members were mailed.
	 */
	private static function mail_returned( $record, $post_id, $note ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site    = WPCPM_Mail::site_name();
		$manager = wp_get_current_user();
		$named   = $manager instanceof WP_User ? $manager->display_name : '';
		$headers = WPCPM_Mail::reply_to( $manager instanceof WP_User ? $manager : null );
		$when    = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $note, $named, $headers, $when ) {
			$lines = array(
				sprintf(
					/* translators: 1: date, 2: the program manager's name. */
					__( 'Your signed Collaboration Agreement was sent back on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					$when,
					$named
				),
				$note,
				__( 'Upload the corrected copy from the agreement panel on the site. Replying to this message reaches the program manager who wrote the note.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your signed agreement needs a change', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => $headers,
			);
		};

		return self::mail_members( $record, self::MAIL_RETURNED, $build );
	}

	/**
	 * Tell the institution its agreement was revoked, and why (T8).
	 *
	 * Verbatim, like the return note, and for a harder reason: this message is the whole of
	 * what an institution is told about why the account it signed in to yesterday shows one
	 * panel today. It says what is limited rather than what is gone, because nothing about
	 * their students is deleted by a revocation, and it names the country contact so there is
	 * somebody to write to who is not the address this mail was sent from.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The revoked document.
	 * @param string $note    The note, as typed.
	 * @return int How many members were mailed.
	 */
	private static function mail_revoked( $record, $post_id, $note ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site    = WPCPM_Mail::site_name();
		$manager = wp_get_current_user();
		$named   = $manager instanceof WP_User ? $manager->display_name : '';
		$when    = wp_date( 'Y-m-d' );

		$build = function () use ( $site, $note, $named, $when, $record ) {
			$lines = array(
				sprintf(
					/* translators: 1: date, 2: the program manager's name. */
					__( 'Your Collaboration Agreement was revoked on %1$s by %2$s, with this note:', 'wpcredits-program-manager' ),
					$when,
					$named
				),
				$note,
				__( 'Your account on this site is limited to the agreement panel until an agreement is in force again. Nothing about your students has been deleted.', 'wpcredits-program-manager' ),
				// Built here rather than above, because `WPCPM_Mail::send()` calls this
				// builder inside the recipient's own locale and a sentence translated
				// outside it would reach them in the site's language.
				self::contact_line( $record ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement has been revoked', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_REVOKED, $build );
	}

	/**
	 * Tell the institution its agreement is in force again (T9).
	 *
	 * The `agreement-accepted` context with the reinstated wording, because that is what this
	 * is: the same account opening, and a manager searching the mail log for why an
	 * institution was let back in should find it filed with every other acceptance. The
	 * second line answers the question the first raises, which is what happened to everything
	 * while the account was shut.
	 *
	 * @param string $record  Institutions record ID.
	 * @param int    $post_id The reinstated document.
	 * @return int How many members were mailed.
	 */
	private static function mail_reinstated( $record, $post_id ) {
		$facts = self::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return 0;
		}

		$site = WPCPM_Mail::site_name();
		$when = wp_date( 'Y-m-d' );
		$page = WPCPM_Institutions_Dashboard::page_url();

		$build = function () use ( $site, $when, $page ) {
			$lines = array(
				sprintf(
					/* translators: %s: date the agreement was put back in force. */
					__( 'Your Collaboration Agreement was put back in force on %s. Your account on the site is open again.', 'wpcredits-program-manager' ),
					$when
				),
				__( 'Nothing was removed while it was out of force: your students, their progress and your copy of the signed agreement are where they were.', 'wpcredits-program-manager' ),
			);

			if ( '' !== $page ) {
				$lines[] = $page;
			}

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] Your agreement is in force again', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return self::mail_members( $record, self::MAIL_ACCEPTED, $build );
	}

	/**
	 * Who to write to about a revoked agreement, in one sentence.
	 *
	 * The country contact when the base names one, and the panel's own neutral sentence when
	 * it does not, so the two places an institution reads this read the same. Named in the
	 * body and never added as a recipient: that is the rule design spec 7.4 sets for this
	 * address everywhere it appears, and this mail is not the country contact's business
	 * unless the institution decides it is.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function contact_line( $record ) {
		$row     = WPCPM_Institutions_Index::row( $record );
		$country = is_array( $row ) ? (string) $row['country'] : '';
		$routing = '' === $country ? null : WPCPM_Countries::routing( $country );

		if ( null === $routing || '' === trim( (string) $routing['email'] ) ) {
			return __( 'Write to the program manager who has been in touch with your institution.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: 1: country name, 2: the contact's email address. */
			__( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' ),
			WPCPM_Countries::name_of( $country ),
			trim( (string) $routing['email'] )
		);
	}

	/**
	 * Return from an agreement handler with a one-shot outcome, and stop.
	 *
	 * The same shape as `bounce_on_file()`, which Phase 2 shipped and which this phase does
	 * not touch: back where the request came from, since every one of these forms is drawn on
	 * both the institution's panel and the manager's row, with the Institutions screen as the
	 * fallback for a request that carried no referer. The words for each outcome live in
	 * `WPCPM_Institution_Panel::messages()`, once, because both screens print them.
	 *
	 * @param string $status Outcome slug.
	 */
	private static function bounce( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=wpcpm-institutions' ) );
		exit;
	}
}
