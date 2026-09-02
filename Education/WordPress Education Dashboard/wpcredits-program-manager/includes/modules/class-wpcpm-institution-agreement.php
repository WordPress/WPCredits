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
 * Phase 1 ships the predicate, the summary and the reconcile path. Generation, upload, accept,
 * return, revoke, reinstate, download and stage writes are later phases; `STAGE_ORDER` and
 * `TERMINAL_STAGES` are declared here so the base's spelling is asserted once, in the fixture.
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

	/**
	 * Hooks.
	 *
	 * The on-file handler is registered here, beside the method it names, because that is
	 * the one arrangement `bin/test-institution-policy.php` can read: it resolves every
	 * `admin_post_` registration in the institution classes to a method body in the same
	 * file, and asserts that the body decides before it touches Airtable.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_ON_FILE, array( __CLASS__, 'handle_on_file' ) );
		add_action( 'admin_post_' . self::ACTION_ON_FILE_ALL, array( __CLASS__, 'handle_on_file_all' ) );
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
}
