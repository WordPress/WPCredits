<?php
/**
 * Institutions module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 3 - Institutions.
 *
 * The program's side of the partnership with a school: the pipeline of institution records
 * read from Airtable, the roster index the students sync builds per institution, the state
 * of each Collaboration Agreement, and the reconciliation between the two Airtable tables
 * that describe the same students.
 *
 * Phase 1 is manager-only. The screen reads the pipeline index, the roster counts, the
 * countries map and the per-institution agreement options, and never Airtable itself: a
 * render that paged the base would take the rate limit away from the syncs, and a number
 * read live cannot say when it was true. Every card prints the read time its numbers came
 * from instead.
 */
class WPCPM_Institutions extends WPCPM_Sync_Module {

	const ACTION_SYNC   = 'wpcpm_institutions_sync';
	const ACTION_CANCEL = 'wpcpm_institutions_cancel';
	const ACTION_PROBE  = 'wpcpm_institutions_probe';
	const ACTION_TICK   = 'wpcpm_institutions_tick';

	/** Provisioning: every institution that is ready, and one institution at a time. */
	const ACTION_PROVISION     = 'wpcpm_institutions_provision';
	const ACTION_PROVISION_ONE = 'wpcpm_institutions_provision_one';

	/**
	 * How many accounts one press of the bulk button creates.
	 *
	 * A ceiling and not a page size: forty-two Confirmed institutions is the whole of day
	 * one, and a request that inserts every one of them plus its stamp and its audit row is
	 * the request that times out on a slow host. Whatever is left is still listed on the card
	 * afterwards, and the button says so, so pressing it again is the way through.
	 */
	const PROVISION_LIMIT = 25;

	/** How many institutions the gate's refusal names before it says "and N more". */
	const PROVISION_NAMES = 5;

	/** Flash channel for this screen's outcomes. */
	const FLASH = 'institutions';

	/** The one pipeline filter Phase 1 offers, as the `wpcpm_filter` query argument. */
	const FILTER_GAP = 'agreement_gap';

	/**
	 * Linking one unlinked Students row to an institution, from the reconciliation card.
	 *
	 * A program manager's act and never an institution's. An import preview that answered
	 * "this address is an adoptable Students row" would be a membership oracle a school could
	 * walk address by address, so the import collapses every outside hit to one neutral
	 * refusal and adoption happens here instead.
	 */
	const ACTION_LINK = 'wpcpm_institutions_link';

	/** Flash channel for the link control, read by the card that draws the control. */
	const FLASH_LINK = 'institutions_link';

	/** Where the link control's redirect lands, so the outcome is on screen next to it. */
	const ANCHOR_UNLINKED = 'wpcpm-unlinked';

	/**
	 * The statuses `Add students to Students Reports and Feedback` (`wflXg1xFuiCSG0pXZ`) watches.
	 *
	 * That automation is deployed, and it creates a Students Reports row and a Feedback row as
	 * soon as a Students row holds a name, an address, an institution link and a mentor at one
	 * of these four statuses. A row that already has the other three is one write away from
	 * firing it, and that write is the link this control makes: it is the traced source of the
	 * duplicate reports rows in the base, 18 addresses of them on the reports side.
	 *
	 * Pinned here rather than taken from `student_statuses`: that setting says which statuses
	 * this program tracks and a manager may change it, while this list says what somebody
	 * else's automation does, which no setting on this site can alter.
	 */
	const AUTOMATION_STATUSES = array( 'In Sensei', 'In Sensei Self-onboarding', 'In Sensei 50h', 'Developer Track' );

	/**
	 * Why a row may not be linked, and how a press of Link ended.
	 *
	 * One vocabulary for both sides: the card prints the same sentence beside a row it will
	 * not offer a control for that the handler flashes when it refuses the write, so a manager
	 * cannot be told two different things about the same row.
	 */
	const LINK_AUTOMATION     = 'automation';
	const LINK_REPORTS        = 'reports-row';
	const LINK_NO_EMAIL       = 'no-address';
	const LINK_ALREADY        = 'already-linked';
	const LINK_DONE           = 'linked';
	const LINK_BAD_RECORD     = 'bad-record';
	const LINK_NO_INSTITUTION = 'unknown-institution';
	const LINK_READ_FAILED    = 'read-failed';
	const LINK_WRITE_FAILED   = 'write-failed';

	/** The audit kind a link writes, in the shape `WPCPM_Institution_Agreement::LOG_ON_FILE` uses. */
	const LOG_LINKED = 'student_linked';

	/**
	 * The day the consent checkbox was added to the Airtable application form.
	 *
	 * Every consent-ticked record was created on or after this date and none before it, so a
	 * record created earlier with no tick was collected before the question existed. The
	 * consent card counts those and says so; it never says consent went missing, because it
	 * was not asked.
	 */
	const CONSENT_QUESTION_ADDED = '2026-07-20';

	/**
	 * How many semester reports the card lists.
	 *
	 * A hundred and five institutions and two semesters a year, so the whole set is a page of
	 * its own before long. Newest edit first, because the question this card answers is what
	 * the schools have been doing rather than what they have ever done.
	 */
	const REPORTS_SHOWN = 60;

	/**
	 * The five decisions a manager takes on an application, and the deletion behind them.
	 *
	 * One action apiece rather than one handler reading a posted verb: the nonce is keyed to
	 * the action and the application together, so a nonce harvested from the Reject form of
	 * one application cannot approve another.
	 */
	const ACTION_APPROVE = 'wpcpm_app_approve';
	const ACTION_INFO    = 'wpcpm_app_info';
	const ACTION_REJECT  = 'wpcpm_app_reject';
	const ACTION_SPAM    = 'wpcpm_app_spam';
	const ACTION_REOPEN  = 'wpcpm_app_reopen';
	const ACTION_PURGE   = 'wpcpm_app_purge';

	/** The daily retention run, which deletes what the three retention settings say to. */
	const CRON_PURGE = 'wpcpm_purge_applications';

	/**
	 * What each deletion left behind.
	 *
	 * A reference, a state and a date, and never an address or a word of what anybody wrote:
	 * a log that survives the thing it describes must not become the copy of it that the
	 * retention rule was there to remove.
	 */
	const OPT_APP_LOG = 'wpcpm_application_log';

	/** How many rows that log keeps. */
	const APP_LOG_MAX = 200;

	/**
	 * How many rows the queue card draws.
	 *
	 * A ceiling and not a page size, the way `PROVISION_LIMIT` is. `/apply` is open to
	 * strangers, so how many rows are waiting is somebody else's decision: a card that drew
	 * one per submission would answer a bad afternoon on the form with a screen nobody can
	 * open, which is the screen the applications need a manager to be able to open. The
	 * oldest fifty are the ones whose turn it is, and the card says when there are more.
	 */
	const QUEUE_MAX = 50;

	/**
	 * Where the menu bubble stops counting.
	 *
	 * The same reason and a harder one: the bubble is drawn on every admin page load, not
	 * only on this screen. Two hundred to match the ceiling `WPCPM_Institution_Agreement::awaiting_review()`
	 * already reads under, and because past it the exact number changes nothing a manager
	 * does next.
	 */
	const COUNT_MAX = 200;

	/** The query argument that opens one application on this screen. */
	// The same spelling `WPCPM_Institution_Application::QUERY_QUEUE` puts in the managers'
	// mail, and deliberately not `wpcpm_application`, which is already the name of the posted
	// answers array on the public form: one word meaning two things is how a deep link ends
	// up opening nothing at all.
	const ARG_APPLICATION = 'wpcpm_app_id';

	/** The form field naming the application every decision is posted for. */
	const FIELD_APPLICATION = 'wpcpm_application';

	/**
	 * A manager's question or reason: long enough to be a sentence, short enough to read.
	 *
	 * The question is the whole of what an applicant is told, so an empty one is refused;
	 * the reason on a rejection is never sent anywhere, so it is optional and only the
	 * ceiling applies to it.
	 */
	const MIN_NOTE = 10;
	const MAX_NOTE = 2000;

	/** The two messages this queue sends, named for the mail log. */
	const MAIL_INFO     = 'institution-information';
	const MAIL_DECLINED = 'institution-declined';

	/** What the application's own history calls each decision. */
	const EVENT_INFO     = 'information requested';
	const EVENT_REJECTED = 'rejected';
	const EVENT_SPAM     = 'marked as spam';
	const EVENT_REOPENED = 'reopened';

	/** The form's own word for "this one looks like another one", on `META_SIGNALS`. */
	const SIGNAL_DUPLICATE = 'duplicate';

	/**
	 * The membership stamps `WPCPM_Institution_Members` writes on accounts.
	 *
	 * Named through the class that owns them rather than as literals: `WPCPM_Institution_Members`
	 * is the only writer, `bin/test-institution-members.php` proves it by scanning for the strings,
	 * and a key renamed there would otherwise leave this uninstall list quietly deleting nothing.
	 *
	 * @return string[]
	 */
	public static function member_meta() {
		return array(
			WPCPM_Institution_Members::META_RECORD_ID,
			WPCPM_Institution_Members::META_ACTIVE,
			WPCPM_Institution_Members::META_RECORD_ID_WAS,
			WPCPM_Institution_Members::META_MEMBERSHIP,
			WPCPM_Institution_Members::META_INVITED,
			WPCPM_Institution_Members::META_PROFILE,
		);
	}

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'institutions';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Institutions', 'wpcredits-program-manager' );
	}

	/**
	 * The menu title, carrying the queue's pending count as a bubble.
	 *
	 * The number is what a manager opens this screen for: applications waiting to be decided
	 * and signed agreements waiting to be read, added together, in the markup WordPress
	 * already styles for comments awaiting moderation. `label()` stays plain, because it is
	 * also the `<h1>` and a bubble in a heading is not a heading.
	 *
	 * Two reads on every admin page load, which is what a bubble costs. The alternative is a
	 * cached number that says three when there are four, and a queue whose count nobody
	 * trusts is a queue nobody works.
	 *
	 * Bounded at `COUNT_MAX`, and it says "and more" there rather than pretending the ceiling
	 * is the number: a form open to strangers can be flooded, and a bubble that counted every
	 * row of a flood would put that cost on every admin page in the site.
	 *
	 * @return string
	 */
	public function menu_label() {
		$pending = self::queue_count();

		if ( $pending < 1 ) {
			return $this->label();
		}

		$shown = $pending < self::COUNT_MAX
			? number_format_i18n( $pending )
			: sprintf(
				/* translators: %s: the largest number the menu bubble counts to. */
				__( '%s+', 'wpcredits-program-manager' ),
				number_format_i18n( self::COUNT_MAX )
			);

		return sprintf(
			'%1$s <span class="awaiting-mod count-%2$d"><span class="pending-count">%3$s</span></span>',
			$this->label(),
			$pending,
			$shown
		);
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_INSTITUTION;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Educational institution records from Airtable, the students each one has in the program, and the state of every Collaboration Agreement. Institution accounts are based on the Subscriber role, with access to Institution-level content.', 'wpcredits-program-manager' );
	}

	/**
	 * This module is built.
	 *
	 * @return bool
	 */
	public function is_implemented() {
		return true;
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		// The two private post types this module keeps, registered on `init` the way the
		// Students module boots its helpers.
		WPCPM_Institution_Agreement::init();
		WPCPM_Institution_Audit::init();

		// The daily jobs, put back on the clock whenever they are missing: see the method.
		add_action( 'init', array( __CLASS__, 'schedule_cron' ), 20 );

		// The institution's own page, and the three membership handlers behind the People card
		// and this screen's backstop.
		WPCPM_Ceiling::init();
		WPCPM_Institution_Application::init();
		WPCPM_Agreement_Generate::init();
		WPCPM_Institutions_Dashboard::init();
		WPCPM_Institution_People::init();
		WPCPM_Institution_Student_Form::init();
		WPCPM_Institution_Notes::init();
		WPCPM_Institution_Invite::init();
		WPCPM_Institution_Request::init();
		WPCPM_Institution_Students::init();
		WPCPM_Institution_Export::init();
		WPCPM_Institution_Import::init();
		WPCPM_Institution_Import_Form::init();
		WPCPM_Institution_Create::init();

		// The semester report, in the module's two halves: the data half registers the post
		// type and its meta, the request-touching half the nine handlers and the print assets.
		// Both are booted here rather than from the dashboard, because the print document is an
		// `admin_post_` route and a handler registered only when a page renders is a handler
		// that is not there when the form posts to it.
		WPCPM_Semester_Report::init();
		WPCPM_Semester_Report_Screen::init();

		WPCPM_Institutions_Sync::register_cron();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_PROBE, array( $this, 'handle_probe' ) );
		add_action( 'admin_post_' . self::ACTION_PROVISION, array( $this, 'handle_provision' ) );
		add_action( 'admin_post_' . self::ACTION_PROVISION_ONE, array( $this, 'handle_provision_one' ) );
		add_action( 'admin_post_' . self::ACTION_LINK, array( $this, 'handle_link' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// The review queue's six decisions. `admin_post_` and never `nopriv`: every one of
		// them needs the management capability, and a logged-out request has no business
		// reaching a handler that mails an applicant.
		add_action( 'admin_post_' . self::ACTION_APPROVE, array( $this, 'handle_approve' ) );
		add_action( 'admin_post_' . self::ACTION_INFO, array( $this, 'handle_info' ) );
		add_action( 'admin_post_' . self::ACTION_REJECT, array( $this, 'handle_reject' ) );
		add_action( 'admin_post_' . self::ACTION_SPAM, array( $this, 'handle_spam' ) );
		add_action( 'admin_post_' . self::ACTION_REOPEN, array( $this, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::ACTION_PURGE, array( $this, 'handle_purge' ) );

		add_action( self::CRON_PURGE, array( __CLASS__, 'purge_applications' ) );
	}

	/**
	 * Activation: the private directory and its probe, the countries map, the sync schedule.
	 *
	 * The countries refresh is best effort. It needs a connected base, and activation must
	 * finish whether or not one is configured yet; the sync's own `countries` phase reads the
	 * table again on its first run anyway.
	 */
	public function activate() {
		WPCPM_Private_Files::ensure();
		WPCPM_Private_Files::probe();

		if ( WPCPM_Settings::is_connected() ) {
			WPCPM_Countries::refresh();
		}

		WPCPM_Institutions_Sync::activate();
		WPCPM_Institutions_Dashboard::ensure_page();

		// The application form's page, and deliberately not gated: see `ensure_page()`.
		WPCPM_Institution_Application::ensure_page();

		self::schedule_cron();
	}

	/**
	 * Put the module's five daily jobs on the clock, if they are not there already.
	 *
	 * **Called from `boot()` on every load, not only from `activate()`.** The activation hook
	 * fires on an explicit activation and on nothing else: not on the files being replaced,
	 * and not on the upgrader's silent reactivation, which is how this site is deployed
	 * (`wp plugin install --force`). Until this method existed the five jobs were scheduled
	 * only in `activate()`, so a site whose schedule had gone, or was installed through the
	 * upgrader, would never purge an application or expire an invitation again and nothing
	 * on any screen would say so. The three syncs already self-heal this way on every boot;
	 * this is the same rule for the rest. `wp_next_scheduled()` makes it cheap and
	 * idempotent: five cache reads on a normal load, one write when a job is missing.
	 *
	 * The ceiling's sweep is the ceiling's own housekeeping: its rows are hash-named claims
	 * that nothing reads after their window, so a sweep is the only thing that ever removes
	 * them. The four nightly jobs are retention on applications, retention on agreement
	 * files, the reviewer's digest and invitation expiry. All daily because the settings
	 * behind them are in days, and none on the sync's cadence: a base that is down must not
	 * stop a file being forgotten on time. An hour apart so they never land in one request.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( WPCPM_Ceiling::CRON_SWEEP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', WPCPM_Ceiling::CRON_SWEEP );
		}

		$nightly = array( self::CRON_PURGE, WPCPM_Institution_Agreement::CRON_DISCARD, WPCPM_Institution_Agreement::CRON_REMINDERS, WPCPM_Institution_Invite::CRON_EXPIRE );

		foreach ( $nightly as $offset => $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + ( ( 2 + $offset ) * HOUR_IN_SECONDS ), 'daily', $hook );
			}
		}
	}

	/**
	 * Deactivation: stop scheduled work.
	 */
	public function deactivate() {
		WPCPM_Institutions_Sync::deactivate();
	}

	/**
	 * Uninstall: drop the indexes, the agreement state, the audit log and the stamps.
	 *
	 * Accounts are left alone, they are people. The signed agreement files under uploads are
	 * left alone too, on purpose: the design spec's risk register names them as data the next
	 * site owner must be told about, and a plugin removal is not the moment to shred a signed
	 * document nobody has another copy of.
	 */
	public function uninstall() {
		delete_option( WPCPM_Institutions_Sync::OPT_STATE );
		delete_option( WPCPM_Institutions_Sync::OPT_REPORT );
		delete_option( WPCPM_Institutions_Sync::OPT_LAST );
		delete_option( WPCPM_Institutions_Sync::OPT_ERROR );
		delete_option( WPCPM_Institutions_Sync::OPT_LOCK );

		delete_option( WPCPM_Institutions_Index::OPT_NAME );
		delete_option( WPCPM_Countries::OPT_NAME );
		delete_option( WPCPM_Private_Files::OPT_PROBE );
		delete_option( WPCPM_Institutions_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED );

		wp_clear_scheduled_hook( WPCPM_Ceiling::CRON_SWEEP );
		wp_clear_scheduled_hook( self::CRON_PURGE );
		wp_clear_scheduled_hook( WPCPM_Institution_Agreement::CRON_DISCARD );
		wp_clear_scheduled_hook( WPCPM_Institution_Agreement::CRON_REMINDERS );
		wp_clear_scheduled_hook( WPCPM_Institution_Invite::CRON_EXPIRE );
		wp_clear_scheduled_hook( WPCPM_Semester_Report_Screen::CRON_ASK );

		delete_option( WPCPM_Institution_Application::OPT_PAGE );
		delete_option( self::OPT_APP_LOG );

		WPCPM_Ceiling::delete_all();
		WPCPM_Institution_Application::delete_all();
		WPCPM_Institution_Approval::delete_all();
		WPCPM_Roster_Index::delete_all();
		// The signed files and their key stay (spec 7.11: they are legal records); the posts
		// that named them go, so the inventory is mailed first, while the names still exist.
		WPCPM_Institution_Agreement::manifest_kept_files();
		WPCPM_Institution_Agreement::delete_all();
		WPCPM_Institution_Audit::delete_all();
		// Both documented as "called on uninstall" for two releases without a caller: the
		// invitations carry the invitee's address and the token hash, the requests a student's
		// record ID, and both outlived the plugin.
		WPCPM_Institution_Invite::delete_all();
		WPCPM_Institution_Request::delete_all();
		// The batch posts hold a school's list of names and addresses, so they go with the
		// rest rather than outliving the plugin that made them.
		WPCPM_Institution_Import::delete_all();
		// The semester reports go for the same reason and one more: each one holds students'
		// own words, released to one university for one document. The consent that put them
		// there was consent to that document, not to a post that outlives the plugin.
		WPCPM_Semester_Report::delete_all();

		foreach ( self::member_meta() as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}

		// The report's three user meta keys, each named through the class that writes it. The
		// permissions stamp is a copy of an answer that lives in Airtable, the asked stamp is
		// a reminder clock, and the stash is an unsent draft somebody's colleague overwrote;
		// none of the three is the answer itself, and none of them means anything once the
		// screens that read them are gone.
		delete_metadata( 'user', 0, WPCPM_Student_Feedback::META_REPORT_PERMISSIONS, '', true );
		delete_metadata( 'user', 0, WPCPM_Semester_Report_Screen::META_ASKED, '', true );
		delete_metadata( 'user', 0, WPCPM_Semester_Report_Screen::META_STASH, '', true );

		// The generate lock and the ask queue are per report, so they have no single name to
		// delete. They are transient by intent rather than by storage (a lock has to survive a
		// crashed request, which is why it is an option), and left behind they are rows in
		// `wp_options` naming post IDs that no longer exist.
		WPCPM_Semester_Report_Screen::delete_all();
	}

	/*
	 * Handlers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sync this module owns.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return 'WPCPM_Institutions_Sync';
	}

	/**
	 * The flash channel this screen reads its outcomes from.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return self::FLASH;
	}

	/**
	 * The Institutions sync calls its slice `tick()`.
	 *
	 * @param int $budget Seconds.
	 */
	protected function run_sync_tick( $budget ) {
		WPCPM_Institutions_Sync::tick( $budget );
	}

	/**
	 * Ask the host what it does with the private directory.
	 */
	public function handle_probe() {
		$this->verify( self::ACTION_PROBE );

		$result = WPCPM_Private_Files::probe();

		$this->redirect_back( '' !== $result['error'] ? 'probe-failed' : 'probed' );
	}

	/**
	 * Create an account for every institution that is ready for one.
	 *
	 * The gate first, and in the handler and not only in the markup: the button is drawn
	 * disabled while a Confirmed institution has no agreement recorded, but a disabled button
	 * is a courtesy to the person and not a check. Nothing is created while the count is
	 * above zero, whichever institutions are ready, because the point of the gate is that the
	 * program records what it has already agreed before it starts opening accounts.
	 *
	 * `PROVISION_LIMIT` at a time. The card recomputes what is left on the next page load, so
	 * a run that stops at the ceiling needs no state to carry on: press it again.
	 */
	public function handle_provision() {
		$this->verify( self::ACTION_PROVISION );

		$reasons = self::provision_reasons( WPCPM_Institutions_Index::rows() );

		if ( ! empty( array_keys( $reasons, WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT, true ) ) ) {
			$this->redirect_back( 'provision-blocked' );
		}

		$created = 0;
		$failed  = 0;

		foreach ( array_slice( array_keys( $reasons, '', true ), 0, self::PROVISION_LIMIT ) as $record_id ) {
			$result = WPCPM_Institutions_Sync::provision( $record_id, get_current_user_id() );

			if ( is_wp_error( $result ) ) {
				++$failed;
				continue;
			}

			++$created;
		}

		if ( $failed > 0 ) {
			$this->redirect_back( 'provision-failed' );
		}

		$this->redirect_back( $created > 0 ? 'provisioned' : 'provision-none' );
	}

	/**
	 * Create the account for one institution.
	 *
	 * The record ID is read with `WPCPM_Request::posted_text()` and never a `key()`:
	 * `sanitize_key()` lowercases, and an Airtable record ID is case-sensitive. It is read
	 * before the capability is decided only because the nonce is keyed to it; nothing is done
	 * with it until both checks have passed.
	 *
	 * Whether this institution may be provisioned is `provision_block()`'s answer, asked
	 * inside `provision()`, so a stale page that offers the button after somebody else has
	 * recorded a revocation is refused rather than obeyed.
	 */
	public function handle_provision_one() {
		$record_id = WPCPM_Request::posted_text( 'wpcpm_institution' );

		$this->verify( self::ACTION_PROVISION_ONE . '_' . $record_id );

		$result = WPCPM_Institutions_Sync::provision( $record_id, get_current_user_id() );

		if ( ! is_wp_error( $result ) ) {
			$this->redirect_back( 'provisioned' );
		}

		$refused = WPCPM_Institutions_Sync::PROVISION_ERROR === $result->get_error_code();

		$this->redirect_back( $refused ? 'provision-refused' : 'provision-failed' );
	}

	/**
	 * Link one unlinked Students row to an institution.
	 *
	 * **The write this handler makes is what fires an automation, so the row is read again
	 * before it is made.** Setting `Educational Institutions` on a Students row that already
	 * carries a mentor at one of `AUTOMATION_STATUSES` completes the condition
	 * `Add students to Students Reports and Feedback` watches, and it creates a second
	 * Students Reports row for a student who has one. The same is true one step removed when
	 * a reports row already exists for the address. Both are refused, and refused against a
	 * live read: `wpcpm_roster_unlinked` is as old as the last sync, and a mentor assigned in
	 * the base an hour ago is exactly the case that turns a safe row into an unsafe one.
	 *
	 * The order is the module's: the capability and the nonce through `verify()`, then
	 * `decide()`, then anything that reaches the network. The nonce is keyed to the Students
	 * row, so a token harvested from the control of a row the site is willing to link is not
	 * a token for the row beside it, which is the row these refusals are about.
	 *
	 * Nothing else on the row is written. Every other cell is somebody else's writing, and a
	 * PATCH carrying a copy read a moment earlier is this site overwriting the base with it.
	 */
	public function handle_link() {
		$students_record = WPCPM_Request::posted_text( 'wpcpm_student' );

		$this->verify( self::ACTION_LINK . '_' . $students_record );

		$institution = WPCPM_Request::posted_text( 'wpcpm_institution' );

		// Shape, then the policy, then the state of the record, in the order
		// `WPCPM_Institution_People::handle_add_account()` explains: a value that is not a
		// record ID at all is a forged or a broken form, and deciding after it keeps the
		// decision the log reads about a well-formed institution.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $students_record ) || ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			$this->finish_link( self::LINK_BAD_RECORD, '' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_ADD_STUDENT,
			WPCPM_Institution_Policy::subject_institution( $institution )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		// An institution the site has never read cannot be given a student: the record ID
		// would go into Airtable unchecked, and a mistyped one links the student to nothing.
		if ( ! WPCPM_Institutions_Index::has( $institution ) ) {
			$this->finish_link( self::LINK_NO_INSTITUTION, '' );
		}

		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Mentors_Sync::fields();
		$airtable = new WPCPM_Airtable( $settings );
		$record   = $airtable->get_record( isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '', $students_record );

		if ( is_wp_error( $record ) ) {
			$this->finish_link( self::LINK_READ_FAILED, $record->get_error_message() );
		}

		$row    = self::link_row( isset( $record['fields'] ) ? (array) $record['fields'] : array(), $fields );
		$reason = self::link_block( $row );

		// The cheap refusals first, because the reports lookup is a second request and a row
		// the automation already covers is refused whatever that lookup would have said.
		if ( '' !== $reason ) {
			$this->finish_link( $reason, '' );
		}

		$reports = self::reports_by_email( $airtable, $settings, $fields, $row['email_key'] );

		// Fail closed. "Airtable did not answer" is not "there is no reports row", and the
		// whole point of this control is that it does not guess about the second row.
		if ( is_wp_error( $reports ) ) {
			$this->finish_link( self::LINK_READ_FAILED, $reports->get_error_message() );
		}

		$row['reports'] = $reports;
		$reason         = self::link_block( $row );

		if ( '' !== $reason ) {
			$this->finish_link( $reason, '' );
		}

		$written = $airtable->update_records(
			isset( $settings['students_table'] ) ? (string) $settings['students_table'] : '',
			array(
				array(
					'id'     => $students_record,
					'fields' => array( $fields['student_institution'] => array( $institution ) ),
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			$this->finish_link( self::LINK_WRITE_FAILED, $written->get_error_message() );
		}

		$row['record_id']   = $students_record;
		$row['institution'] = $institution;

		// On the roster now rather than after tonight's sync, for the reason the import path
		// inserts its rows: a manager who has just linked a student and is told to come back
		// tomorrow to see it has no way to tell a slow index from a write that did not land.
		WPCPM_Roster_Index::insert( $institution, $row );

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_LINKED,
				'institution' => $institution,
				'subject'     => $students_record,
				'actor'       => get_current_user_id(),
				// The ground the fence answered on, not an assumption about who pressed:
				// decision 2 says a manager passes every action as `manager`, and the log is
				// where that is read back.
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => '',
				'data'        => array(
					'to'     => $institution,
					'status' => $row['status'],
				),
			)
		);

		$this->finish_link( self::LINK_DONE, self::institution_name( $institution ) );
	}

	/**
	 * Say how the link ended and go back to the card that asked.
	 *
	 * Its own channel rather than the screen's, so the sentence is drawn inside the
	 * reconciliation card beside the row it is about instead of at the top of a screen with
	 * eight other cards on it.
	 *
	 * @param string $status One of the `LINK_` constants.
	 * @param string $detail What the sentence adds: an institution name, or an Airtable error.
	 */
	private function finish_link( $status, $detail ) {
		WPCPM_Flash::set(
			self::FLASH_LINK,
			array(
				'status' => (string) $status,
				'detail' => (string) $detail,
			)
		);

		wp_safe_redirect( $this->admin_url() . '#' . self::ANCHOR_UNLINKED );
		exit;
	}

	/**
	 * One live Students record in the shape the roster index and `link_block()` use.
	 *
	 * Built from the record that was just read rather than from the index, because it is the
	 * live row the write lands on. `reports` is left empty here and filled by the lookup: the
	 * Students table's own `Students Reports` column is empty on all 800 rows, so the only
	 * join between the two tables is the address.
	 *
	 * @param array $cells  The record's `fields`.
	 * @param array $fields `WPCPM_Mentors_Sync::fields()`.
	 * @return array A row in the roster index's shape.
	 */
	private static function link_row( array $cells, array $fields ) {
		$read = static function ( $column ) use ( $cells, $fields ) {
			return isset( $fields[ $column ], $cells[ $fields[ $column ] ] )
				? trim( (string) WPCPM_Airtable::flatten( $cells[ $fields[ $column ] ] ) )
				: '';
		};

		$links = WPCPM_Airtable::link_ids( isset( $cells[ $fields['student_institution'] ] ) ? $cells[ $fields['student_institution'] ] : array() );
		$email = $read( 'student_email' );

		return array(
			'record_id'      => '',
			'name'           => $read( 'student_record_name' ),
			'email'          => $email,
			'email_key'      => strtolower( $email ),
			'status'         => $read( 'student_status' ),
			'institution'    => ( ! empty( $links ) && WPCPM_Mentors_Sync::is_record_id( $links[0] ) ) ? trim( (string) $links[0] ) : '',
			'start'          => $read( 'student_start' ),
			'end'            => $read( 'student_end' ),
			'has_mentor'     => ! empty( WPCPM_Airtable::link_ids( isset( $cells[ $fields['student_mentor'] ] ) ? $cells[ $fields['student_mentor'] ] : array() ) ),
			'username'       => WPCPM_Mentors_Sync::wporg_username( $read( 'student_profile' ) ),
			'field_of_study' => $read( 'student_study' ),
			'tutor'          => $read( 'student_tutor' ),
			'import_key'     => $read( 'student_import_key' ),
			'reports'        => array(),
			'user_id'        => 0,
		);
	}

	/**
	 * Why this row may not be linked, or '' when it may.
	 *
	 * **One rule, asked twice.** The card asks it of the index row so it does not offer a
	 * control that would be refused, and the handler asks it of the live record because the
	 * index is as old as the last sync and the write is what fires the automation. Written
	 * once so the two cannot drift into telling a manager different things about one row.
	 *
	 * The order is deliberate: a row that already names an institution is not this list's
	 * business at all, the automation refusal costs nothing to check, and the reports refusal
	 * is last because on the live path it is the one that costs a request.
	 *
	 * @param array $row A row in the roster index's shape.
	 * @return string A `LINK_` reason, or '' when the row may be linked.
	 */
	private static function link_block( array $row ) {
		if ( '' !== trim( (string) ( isset( $row['institution'] ) ? $row['institution'] : '' ) ) ) {
			return self::LINK_ALREADY;
		}

		$status = trim( (string) ( isset( $row['status'] ) ? $row['status'] : '' ) );

		if ( ! empty( $row['has_mentor'] ) && in_array( $status, self::AUTOMATION_STATUSES, true ) ) {
			return self::LINK_AUTOMATION;
		}

		// No address is not "no duplicate": it is the site being unable to run the check at
		// all, because the two tables are joined by nothing else. Three Students rows carry
		// no email; a manager fixes the address in the base and the row becomes linkable.
		if ( '' === trim( (string) ( isset( $row['email_key'] ) ? $row['email_key'] : '' ) ) ) {
			return self::LINK_NO_EMAIL;
		}

		if ( ! empty( $row['reports'] ) ) {
			return self::LINK_REPORTS;
		}

		return '';
	}

	/**
	 * The Students Reports record IDs held for one address.
	 *
	 * `formula_in()`'s third argument wraps the column in `LOWER()`, which is what makes this
	 * a comparison of mailboxes rather than of spellings: the base holds addresses as they
	 * were typed and one pair in the Students table differs only by case.
	 *
	 * One page and never every page: the question is whether any row exists at all, and a
	 * second page can only add more of them. What comes back is counted and **not** re-checked against the
	 * address in PHP, because Airtable answers a `fields` list naming an unknown column with
	 * records carrying no fields at all, and a re-check would then read those as misses and
	 * hand back the empty answer this control must never invent.
	 *
	 * @param WPCPM_Airtable $airtable A configured client.
	 * @param array          $settings Plugin settings.
	 * @param array          $fields   `WPCPM_Mentors_Sync::fields()`.
	 * @param string         $email    The address, lowercased.
	 * @return array|WP_Error Record IDs, or why the base could not be asked.
	 */
	private static function reports_by_email( $airtable, array $settings, array $fields, $email ) {
		$page = $airtable->fetch_page(
			isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '',
			array(
				'formula' => $airtable->formula_in( $fields['report_email'], array( (string) $email ), true ),
				'fields'  => array( $fields['report_email'] ),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$found = array();

		foreach ( $page['records'] as $record ) {
			if ( ! empty( $record['id'] ) ) {
				$found[] = (string) $record['id'];
			}
		}

		return $found;
	}

	/**
	 * Approve one application: the Airtable record, the index row and the account.
	 *
	 * Capability, then the nonce keyed to the application, then the state, and only then the
	 * work, which lives in `WPCPM_Institution_Approval` because it is ten ordered steps with
	 * a lock around them and half of them talk to Airtable. A half-done application, one
	 * whose record landed and whose account did not, is finished by pressing this again: the
	 * approval skips whichever half is already stamped, and there is no second path that
	 * could finish it differently.
	 */
	public function handle_approve() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_APPROVE . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		if ( ! in_array( self::application_state( $post ), self::open_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		$result = WPCPM_Institution_Approval::approve( (int) $post->ID, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( self::approval_outcome( $result->get_error_code() ) );
		}

		$this->redirect_back( ! empty( $result['adopted'] ) ? 'app-adopted' : 'app-approved' );
	}

	/**
	 * Ask the applicant a question and park the application until they answer.
	 *
	 * The question is mailed with the asking manager's address to reply to, because the
	 * answer is a conversation and the site is not part of it. Nothing else changes: the
	 * application stays in the queue under `info`, so a question nobody answers is still
	 * something a manager can see and decide.
	 *
	 * The state moves only if the message left. `info` is this queue's word for "asked, and
	 * waiting on them", so writing it after a send that failed would put an application in
	 * front of the next manager as one somebody is already waiting on - and the applicant,
	 * who was never asked anything, waits for a question that is sitting in nobody's inbox.
	 */
	public function handle_info() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_INFO . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		if ( ! in_array( self::application_state( $post ), self::open_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		$question = self::posted_note( 'wpcpm_question' );

		if ( mb_strlen( $question ) < self::MIN_NOTE ) {
			$this->redirect_back( 'app-question' );
		}

		$email = self::application_email( $post );

		if ( ! is_email( $email ) ) {
			$this->redirect_back( 'app-no-email' );
		}

		$manager   = wp_get_current_user();
		$site      = WPCPM_Mail::site_name();
		$reference = self::application_reference( $post );

		$build = static function () use ( $site, $reference, $question, $manager ) {
			$lines = array(
				__( 'Thank you for applying to the WordPress Credits Program. Before a program manager can take your application further, they have one question:', 'wpcredits-program-manager' ),
				$question,
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: application reference, e.g. APP-2026-0007. */
					__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager ),
			);
		};

		if ( ! WPCPM_Mail::send_to( $email, self::MAIL_INFO, $build ) ) {
			$this->redirect_back( 'app-not-sent' );
		}

		update_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_INFO );
		self::record_event( (int) $post->ID, self::EVENT_INFO, $question );

		$this->redirect_back( 'app-info' );
	}

	/**
	 * Reject one application, and tell the applicant nothing but that it was decided.
	 *
	 * Design decision 16: the acknowledgement carries no reason. A reason written for a
	 * colleague reads differently to the person it is about, a reason is what an argument
	 * starts from, and the program has no obligation to give one. The manager's reason is
	 * kept on the application's own history, where the next manager reading the queue can
	 * see it and the applicant cannot.
	 */
	public function handle_reject() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_REJECT . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		if ( ! in_array( self::application_state( $post ), self::open_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		$reason = self::posted_note( 'wpcpm_reason' );
		$email  = self::application_email( $post );
		$site   = WPCPM_Mail::site_name();

		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			$lines = array(
				__( 'Thank you for your interest in the WordPress Credits Program, and for the time it took to write to us.', 'wpcredits-program-manager' ),
				__( 'A program manager has read your application and we are not taking it forward. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'Institutions apply again as their programs change, and an application now says nothing about a later one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] About your application to the WordPress Credits Program', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		if ( is_email( $email ) ) {
			WPCPM_Mail::send_to( $email, self::MAIL_DECLINED, $build );
		}

		update_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_REJECTED );
		self::record_event( (int) $post->ID, self::EVENT_REJECTED, $reason );

		$this->redirect_back( 'app-rejected' );
	}

	/**
	 * Mark one application as spam, and send nothing at all.
	 *
	 * Not a quieter rejection: the address on a spam submission is forged or is somebody
	 * else's, so an acknowledgement is either wasted or is mail this site sent to a stranger
	 * on a spammer's instruction. The row stays until the retention cron removes it, so the
	 * decision can be undone by a manager who disagrees.
	 */
	public function handle_spam() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_SPAM . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		if ( ! in_array( self::application_state( $post ), self::open_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		update_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_SPAM );
		self::record_event( (int) $post->ID, self::EVENT_SPAM, '' );

		$this->redirect_back( 'app-spam' );
	}

	/**
	 * Put a decided application back in the queue.
	 *
	 * The abort that makes the other four safe to press: a rejection or a spam mark taken in
	 * haste, or a question that turned out to be unnecessary, goes back to `new`. An approved
	 * application is not on this list, because approval created a record and an account and
	 * reopening it would offer Approve on a row that has already had it.
	 */
	public function handle_reopen() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_REOPEN . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		if ( ! in_array( self::application_state( $post ), self::reopen_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		update_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_STATE, WPCPM_Institution_Application::STATE_NEW );
		self::record_event( (int) $post->ID, self::EVENT_REOPENED, '' );

		$this->redirect_back( 'app-reopened' );
	}

	/**
	 * Delete one decided application for good, and log that it happened.
	 *
	 * `wp_delete_post( $id, true )`: no trash, because a retention rule that leaves the row
	 * in the trash has not deleted anything. Only a decided application can be purged, so a
	 * queue row cannot be made to disappear by a mis-click; the log row that replaces it
	 * carries the reference and the date and nothing a person wrote.
	 */
	public function handle_purge() {
		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		$this->verify( self::ACTION_PURGE . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			$this->redirect_back( 'app-unknown' );
		}

		$state = self::application_state( $post );

		if ( ! in_array( $state, self::purgeable_states(), true ) ) {
			$this->redirect_back( 'app-state' );
		}

		$reference = self::application_reference( $post );
		$post_id   = (int) $post->ID;

		if ( ! wp_delete_post( $post_id, true ) ) {
			$this->redirect_back( 'app-failed' );
		}

		self::log_purge( $post_id, $reference, $state, 0, get_current_user_id() );

		$this->redirect_back( 'app-purged' );
	}


	/*
	 * Notifications
	 * --------------------------------------------------------------------
	 */

	/**
	 * Tell the program managers something.
	 *
	 * The one mechanism for every queue event: recipients are the `agreement_notify` setting
	 * when it names anybody, otherwise every account holding the management capability that
	 * has an address. Program managers here are WordPress administrators, so the default
	 * reaches the technical ones too; the setting narrows it and should be set before the
	 * first real upload. Every send goes through `WPCPM_Mail`, so it is logged and filtered
	 * like any other message: `send()` for an address that belongs to an account, so the
	 * message is built in that person's language, and `send_to()` for a bare address.
	 *
	 * The country contact is named in a body when a message is about their country, never
	 * added here as a recipient: routing is information, not a mailing list.
	 *
	 * @param string   $context Short label for the mail log, e.g. `agreement-landed`.
	 * @param callable $build   Builder in `WPCPM_Mail::send()`'s shape. It receives a `WP_User`
	 *                          for an account and the address string for a bare address, and
	 *                          returns `subject`, `body` and optionally `headers`.
	 * @return int How many messages were handed off.
	 */
	public static function notify_managers( $context, $build ) {
		if ( ! is_callable( $build ) ) {
			return 0;
		}

		$sent    = 0;
		$setting = trim( (string) WPCPM_Settings::get_value( 'agreement_notify', '' ) );

		if ( '' !== $setting ) {
			$addresses = array_unique( array_filter( array_map( 'trim', explode( ',', $setting ) ) ) );

			foreach ( $addresses as $address ) {
				$user = get_user_by( 'email', $address );

				if ( $user instanceof WP_User && $user->exists() ) {
					$sent += WPCPM_Mail::send( $user, $context, $build ) ? 1 : 0;
				} else {
					$sent += WPCPM_Mail::send_to( $address, $context, $build ) ? 1 : 0;
				}
			}

			return $sent;
		}

		foreach ( self::managers() as $manager ) {
			$sent += WPCPM_Mail::send( $manager, $context, $build ) ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * Every account holding the management capability that has an address.
	 *
	 * The query narrows by capability, which WordPress resolves to the roles that grant it;
	 * `user_can()` then decides per account, so a capability removed from a role by another
	 * plugin, or granted to one account by hand, is answered by the same test the screens use.
	 *
	 * @return WP_User[]
	 */
	public static function managers() {
		$users = get_users(
			array(
				'capability' => WPCPM_Roles::CAP_MANAGE,
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'number'     => 200,
			)
		);

		$managers = array();

		foreach ( (array) $users as $user ) {
			if ( ! $user instanceof WP_User || '' === trim( (string) $user->user_email ) ) {
				continue;
			}

			if ( user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
				$managers[] = $user;
			}
		}

		return $managers;
	}

	/*
	 * The application queue
	 * --------------------------------------------------------------------
	 */

	/**
	 * The application a handler was posted for, or null when it is not one of ours.
	 *
	 * The post type is checked and not only the ID: `admin-post.php` takes whatever number
	 * is posted, and a handler that trusted it would set an application state on a page.
	 *
	 * @param int $application_id Post ID.
	 * @return WP_Post|null
	 */
	private static function application( $application_id ) {
		$post = get_post( (int) $application_id );

		if ( ! $post instanceof WP_Post || WPCPM_Institution_Application::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * One application's state.
	 *
	 * @param WP_Post $post The application.
	 * @return string One of `WPCPM_Institution_Application`'s `STATE_` values, or ''.
	 */
	private static function application_state( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_STATE, true );
	}

	/**
	 * One application's reference, e.g. `APP-2026-0007`.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	private static function application_reference( WP_Post $post ) {
		$stored = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_REFERENCE, true );

		// The stored one first, because it is what the acknowledgement quoted; the computed
		// one behind it, so a row whose stamp went missing still has something to be called.
		return '' !== $stored ? $stored : WPCPM_Institution_Application::reference( (int) $post->ID );
	}

	/**
	 * The answers as they were stored, keyed by Airtable column name.
	 *
	 * @param WP_Post $post The application.
	 * @return array
	 */
	private static function application_fields( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The address the applicant gave, or '' when there is not a usable one.
	 *
	 * The column is named through the sync's map rather than as a literal, so the base's
	 * spelling of it is asserted in one place and against one fixture.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	private static function application_email( WP_Post $post ) {
		$columns = WPCPM_Institutions_Sync::fields();
		$key     = isset( $columns['contact_email'] ) ? $columns['contact_email'] : 'Contact Email';
		$fields  = self::application_fields( $post );

		return isset( $fields[ $key ] ) ? sanitize_email( (string) $fields[ $key ] ) : '';
	}

	/**
	 * The three states that put an application in the queue.
	 *
	 * `held` is in the list on purpose: a submission the anti-spam checks degraded is one
	 * nobody was told about, so if it is not in front of a manager nobody ever sees it.
	 *
	 * @return string[]
	 */
	private static function open_states() {
		return array(
			WPCPM_Institution_Application::STATE_NEW,
			WPCPM_Institution_Application::STATE_HELD,
			WPCPM_Institution_Application::STATE_INFO,
		);
	}

	/**
	 * The states a manager may put back to `new`.
	 *
	 * @return string[]
	 */
	private static function reopen_states() {
		return array(
			WPCPM_Institution_Application::STATE_HELD,
			WPCPM_Institution_Application::STATE_INFO,
			WPCPM_Institution_Application::STATE_SPAM,
			WPCPM_Institution_Application::STATE_REJECTED,
		);
	}

	/**
	 * The states that may be deleted, by hand or by the retention cron.
	 *
	 * The same three the retention settings name, and never an open one: an application
	 * waiting for a decision is somebody's unanswered letter.
	 *
	 * @return string[]
	 */
	private static function purgeable_states() {
		return array(
			WPCPM_Institution_Application::STATE_SPAM,
			WPCPM_Institution_Application::STATE_REJECTED,
			WPCPM_Institution_Application::STATE_APPROVED,
		);
	}

	/**
	 * A state in the words the screen uses for it.
	 *
	 * @param string $state An application state.
	 * @return string
	 */
	private static function application_state_label( $state ) {
		$labels = array(
			WPCPM_Institution_Application::STATE_NEW      => __( 'new', 'wpcredits-program-manager' ),
			WPCPM_Institution_Application::STATE_HELD     => __( 'held by the anti-spam checks', 'wpcredits-program-manager' ),
			WPCPM_Institution_Application::STATE_INFO     => __( 'waiting on the applicant', 'wpcredits-program-manager' ),
			WPCPM_Institution_Application::STATE_SPAM     => __( 'marked as spam', 'wpcredits-program-manager' ),
			WPCPM_Institution_Application::STATE_REJECTED => __( 'rejected', 'wpcredits-program-manager' ),
			WPCPM_Institution_Application::STATE_APPROVED => __( 'approved', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/**
	 * Which outcome message a refused approval gets.
	 *
	 * Mapped rather than passed through: an approval's `WP_Error` message is written for the
	 * caller, and what a manager needs is the sentence that says what to do next. Each code a
	 * person can act on gets its own; a code nobody can act on, an Airtable request that never
	 * came back or a capability that went away mid-request, falls through to "it did not land".
	 *
	 * @param string $code The `WP_Error` code from `WPCPM_Institution_Approval::approve()`.
	 * @return string Outcome slug for the flash.
	 */
	private static function approval_outcome( $code ) {
		$outcomes = array(
			'wpcpm_app_unknown'       => 'app-unknown',
			'wpcpm_app_state'         => 'app-state',
			'wpcpm_app_unverified'    => 'app-unverified',
			'wpcpm_app_email'         => 'app-email',
			'wpcpm_app_country'       => 'app-country',
			'wpcpm_app_busy'          => 'app-busy',
			'wpcpm_app_fields'        => 'app-incomplete',
			'wpcpm_app_no_email'      => 'app-incomplete',
			'wpcpm_app_name'          => 'app-incomplete',

			// The membership refusals. Named separately from `app-failed` because that
			// sentence ends with "pressing Approve again completes the rest", which is true
			// of a write that failed halfway and false of these: the account they name is
			// already somebody else's, and no number of presses changes that.
			'wpcpm_member_elsewhere'  => 'app-member-elsewhere',
			'wpcpm_member_is_admin'   => 'app-member-taken',
			'wpcpm_member_is_student' => 'app-member-taken',
		);

		return isset( $outcomes[ (string) $code ] ) ? $outcomes[ (string) $code ] : 'app-failed';
	}

	/**
	 * A posted note, trimmed to the ceiling.
	 *
	 * `sanitize_textarea_field()` and not `WPCPM_Request::posted_text()`: a question to an
	 * applicant has paragraphs in it, and `sanitize_text_field()` would fold them into one
	 * line. Only ever called from a handler that has already checked the capability and the
	 * nonce, exactly as the rest of the `posted_` family is.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private static function posted_note( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify() has checked the nonce before any handler reaches here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		$note = sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) );

		return trim( mb_substr( $note, 0, self::MAX_NOTE ) );
	}

	/**
	 * Add one row to an application's own history.
	 *
	 * Through the form's own writer, so the shape of a history row is decided in one place
	 * and the sanitising with it. This is where a rejection's reason lives, and the only
	 * place it lives.
	 *
	 * @param int    $post_id Application post ID.
	 * @param string $event   What happened.
	 * @param string $note    What the manager wrote, or ''.
	 */
	private static function record_event( $post_id, $event, $note ) {
		WPCPM_Institution_Application::add_event( (int) $post_id, $event, get_current_user_id(), $note );
	}

	/**
	 * How many things are waiting for a manager, applications and agreements together.
	 *
	 * Stops at `COUNT_MAX`, because the caller is a bubble on every admin page load: what a
	 * manager does about a queue is the same at two hundred as at two thousand, and the
	 * difference between those two numbers is a cost the whole of wp-admin would carry.
	 *
	 * @return int Between 0 and `COUNT_MAX`.
	 */
	private static function queue_count() {
		// One more than the bubble can show, from both sides, and no further: this runs on
		// every wp-admin page load, so the question it asks has to stay the same size whatever
		// the queue does. `COUNT_MAX + 1` is enough to tell "199" from "200 or more".
		$limit   = self::COUNT_MAX + 1;
		$waiting = (int) WPCPM_Institution_Application::pending_count( $limit )
			+ count( (array) WPCPM_Institution_Agreement::awaiting_review( $limit ) );

		return min( self::COUNT_MAX, $waiting );
	}

	/**
	 * The queue, as one list, oldest first.
	 *
	 * Two kinds of row and one list, because they are one queue: a person works it from the
	 * top, and an application that has waited a week does not become less urgent by being
	 * filed under a different heading than a signed agreement that has waited a day.
	 *
	 * Nothing here reads Airtable. Both halves come from posts this site holds, the country
	 * names from the countries map and the institution names from the pipeline index, so the
	 * cost of the card is bounded by the window and never by how many submissions arrived.
	 *
	 * Bounded because the applications half is written by strangers. Both lists arrive oldest
	 * first, so the oldest `$limit` of each is every row that can reach the top of a list
	 * sorted by age, and building the rest would cost several meta reads apiece for rows
	 * nobody would be shown. `waiting` is the whole number so the card can say it is showing
	 * part of it - the agreements in it are as many as their own reader returns, which has a
	 * ceiling of its own, and the applications are counted in full because that is the half
	 * that can be flooded.
	 *
	 * @param int $limit How many rows to build, at most.
	 * @return array{rows: array[], waiting: int} Rows: `kind`, `kind_label`, `id`, `at`,
	 *               `overdue`, `name`, `country_name`, `contact`, `state`, `signals`,
	 *               `duplicate`, `record`.
	 */
	private static function queue_rows( $limit ) {
		$limit   = max( 1, (int) $limit );
		$now     = time();
		$overdue = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) ) * DAY_IN_SECONDS;
		$rows    = array();

		$applications = WPCPM_Institution_Application::applications( self::open_states() );
		$documents    = array_map( 'intval', (array) WPCPM_Institution_Agreement::awaiting_review() );
		$waiting      = count( $applications ) + count( $documents );

		$applications = array_slice( $applications, 0, $limit );
		$documents    = array_slice( $documents, 0, $limit );
		$duplicates   = self::duplicates( $applications );

		foreach ( $applications as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );
			$at      = (int) get_post_time( 'U', true, $post );
			$name    = trim( (string) $post->post_title );

			$rows[] = array(
				'kind'         => 'application',
				'kind_label'   => __( 'Application', 'wpcredits-program-manager' ),
				'id'           => (int) $post->ID,
				'at'           => $at,
				'overdue'      => ( $now - $at ) > $overdue,
				'name'         => '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ),
				'country_name' => self::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) ),
				'contact'      => WPCPM_Countries::contact_of( $country ),
				'state'        => self::application_state( $post ),
				// Carried on the row rather than counted again when it is drawn: a held row
				// has to say on the list that it was held, and how many checks held it.
				'signals'      => self::signals( $post ),
				'duplicate'    => in_array( (int) $post->ID, $duplicates, true ),
				'record'       => '',
			);
		}

		foreach ( $documents as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$record = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Agreement::META_INSTITUTION, true );
			$index  = WPCPM_Institutions_Index::row( $record );
			$index  = is_array( $index ) ? $index : array();

			$country = isset( $index['country'] ) ? (string) $index['country'] : '';
			$at      = (int) get_post_time( 'U', true, $post );

			$rows[] = array(
				'kind'         => 'agreement',
				'kind_label'   => __( 'Signed agreement', 'wpcredits-program-manager' ),
				'id'           => (int) $post->ID,
				'at'           => $at,
				'overdue'      => ( $now - $at ) > $overdue,
				'name'         => self::institution_name( $record ),
				'country_name' => self::country_name( $country, isset( $index['country_name'] ) ? (string) $index['country_name'] : '' ),
				'contact'      => WPCPM_Countries::contact_of( $country ),
				'state'        => '',
				'signals'      => array(),
				'duplicate'    => false,
				'record'       => $record,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['at'] === $b['at'] ? $a['id'] - $b['id'] : $a['at'] - $b['at'];
			}
		);

		return array(
			// Sliced after the sort, so the window is the oldest of the two kinds together
			// rather than the oldest of each with the newer kind pushed off the end.
			'rows'    => array_slice( $rows, 0, $limit ),
			'waiting' => $waiting,
		);
	}

	/**
	 * What the form's checks recorded against one application.
	 *
	 * @param WP_Post $post The application.
	 * @return string[] Signal slugs, in the order they were raised.
	 */
	private static function signals( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_SIGNALS, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) );
	}

	/**
	 * Which of the open applications look like each other.
	 *
	 * Nothing is merged, ever: a stranger submitting first with a target's published address
	 * must never be able to edit or suppress the genuine submission. So the queue flags and
	 * a manager decides, on the hashed address the form stored and on the trimmed, lowered
	 * name, which is the same pair the live search asks Airtable about.
	 *
	 * The form's own signal is honoured too, whichever way round it stores it, so a duplicate
	 * of something already decided is still flagged after the twin has left the queue, and the
	 * later of a pair the queue's window has split still carries its flag when it is drawn.
	 * The older one of such a pair does not, which is one of the things the card is saying
	 * when it says it is showing part of a longer list.
	 *
	 * @param array $posts Open applications; the queue passes the window it is drawing.
	 * @return int[] Post IDs to flag.
	 */
	private static function duplicates( array $posts ) {
		$by_email = array();
		$by_name  = array();
		$flagged  = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$email = trim( (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_EMAIL, true ) );
			$name  = self::lower( $post->post_title );

			if ( '' !== $email ) {
				$by_email[ $email ][] = (int) $post->ID;
			}

			if ( '' !== $name ) {
				$by_name[ $name ][] = (int) $post->ID;
			}

			$signals = get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_SIGNALS, true );

			if ( is_array( $signals ) && in_array( self::SIGNAL_DUPLICATE, $signals, true ) ) {
				$flagged[] = (int) $post->ID;
			}
		}

		foreach ( array_merge( array_values( $by_email ), array_values( $by_name ) ) as $group ) {
			if ( count( $group ) > 1 ) {
				$flagged = array_merge( $flagged, $group );
			}
		}

		return array_values( array_unique( $flagged ) );
	}

	/**
	 * A value lowered for comparison, with mbstring where there is any.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function lower( $value ) {
		$value = trim( (string) $value );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * A country's name from the map, falling back to whatever was stored beside the ID.
	 *
	 * The stamp is the fallback and not the source: a country renamed in the base should
	 * print its new name on a row submitted before the rename.
	 *
	 * @param string $country_id Countries record ID.
	 * @param string $stored     The name stored alongside it.
	 * @return string
	 */
	private static function country_name( $country_id, $stored ) {
		$name = WPCPM_Countries::name_of( $country_id );

		return '' !== $name ? $name : trim( (string) $stored );
	}

	/*
	 * Retention
	 * --------------------------------------------------------------------
	 */

	/**
	 * Delete the applications the retention settings say have been kept long enough.
	 *
	 * The daily `CRON_PURGE` run. Three settings, one per decided state, each in days, and
	 * **0 means never**: an approved application is the paper trail behind an account and a
	 * record, and the default keeps it forever on purpose.
	 *
	 * The clock runs from the decision and not from the submission, so lengthening a
	 * retention setting gives every row the longer life rather than deleting a batch that
	 * was already past the old one.
	 *
	 * @return int How many were deleted.
	 */
	public static function purge_applications() {
		$retention = array(
			WPCPM_Institution_Application::STATE_SPAM     => 'application_spam_days',
			WPCPM_Institution_Application::STATE_REJECTED => 'application_rejected_days',
			WPCPM_Institution_Application::STATE_APPROVED => 'application_approved_days',
		);

		$purged = 0;

		foreach ( $retention as $state => $setting ) {
			$days = (int) WPCPM_Settings::get_value( $setting, 0 );

			if ( $days < 1 ) {
				continue;
			}

			$cutoff = time() - ( $days * DAY_IN_SECONDS );

			foreach ( WPCPM_Institution_Application::applications( array( $state ) ) as $post ) {
				if ( ! $post instanceof WP_Post || self::decided_at( $post ) > $cutoff ) {
					continue;
				}

				$reference = self::application_reference( $post );
				$post_id   = (int) $post->ID;

				if ( ! wp_delete_post( $post_id, true ) ) {
					continue;
				}

				self::log_purge( $post_id, $reference, $state, $days, 0 );
				++$purged;
			}
		}

		return $purged;
	}

	/**
	 * When an application was last decided.
	 *
	 * The newest row of its own history, because that is when the state it is being kept
	 * under began. An application with no history at all falls back to when it arrived,
	 * which is the only other date it has.
	 *
	 * @param WP_Post $post The application.
	 * @return int Unix time, UTC.
	 */
	private static function decided_at( WP_Post $post ) {
		$latest = 0;

		foreach ( (array) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_EVENT, false ) as $event ) {
			if ( is_array( $event ) && isset( $event['at'] ) && (int) $event['at'] > $latest ) {
				$latest = (int) $event['at'];
			}
		}

		return $latest > 0 ? $latest : (int) get_post_time( 'U', true, $post );
	}

	/**
	 * Record that an application was deleted.
	 *
	 * The reference, the state it was in, the rule that removed it and who pressed it: enough
	 * to answer "what happened to APP-2026-0007" months later, and nothing that would make
	 * this option a copy of the thing the retention rule deleted. No address, and not a word
	 * of what anybody wrote.
	 *
	 * @param int    $post_id   The application that was deleted.
	 * @param string $reference Its reference.
	 * @param string $state     The state it was in.
	 * @param int    $days      The retention setting that removed it, or 0 for a manager.
	 * @param int    $actor     Who pressed it, 0 for the cron.
	 */
	private static function log_purge( $post_id, $reference, $state, $days, $actor ) {
		$log = self::application_log();

		$log[] = array(
			'at'        => time(),
			'id'        => (int) $post_id,
			'reference' => sanitize_text_field( (string) $reference ),
			'state'     => sanitize_key( (string) $state ),
			'days'      => (int) $days,
			'actor'     => (int) $actor,
		);

		if ( count( $log ) > self::APP_LOG_MAX ) {
			$log = array_slice( $log, -self::APP_LOG_MAX );
		}

		update_option( self::OPT_APP_LOG, $log, false );
	}

	/**
	 * Every purge this site has recorded, oldest first.
	 *
	 * @return array[]
	 */
	public static function application_log() {
		$log = get_option( self::OPT_APP_LOG, array() );

		return is_array( $log ) ? array_values( $log ) : array();
	}

	/**
	 * Every outcome the queue's handlers can flash, in the words the reader gets.
	 *
	 * Kept beside the handlers that flash them, the way `WPCPM_Institution_Panel::messages()`
	 * keeps the agreement route's. Every refusal opens by saying that nothing happened, since
	 * the reader has just pressed a button and cannot see the server.
	 *
	 * @return array<string, array>
	 */
	private static function queue_messages() {
		return array(
			'app-approved'         => array( 'success', __( 'The application is approved. The Airtable record, the pipeline row and the account are created, and the invitation is queued.', 'wpcredits-program-manager' ) ),
			'app-adopted'          => array( 'success', __( 'The application is approved. Airtable already held a record for this institution, so it was adopted rather than created; the account is made and the invitation is queued.', 'wpcredits-program-manager' ) ),
			'app-unknown'          => array( 'error', __( 'Nothing happened. That application no longer exists.', 'wpcredits-program-manager' ) ),
			'app-state'            => array( 'error', __( 'Nothing happened. That application is not in a state this decision applies to; somebody may have decided it first. Reload the screen to see where it is.', 'wpcredits-program-manager' ) ),
			'app-unverified'       => array( 'error', __( 'Nothing was created. The address on that application has not been confirmed, and approving mails a password-set link: it must never go to an address nobody has claimed. Confirming it is the applicant\'s own act and no manager can take it for them; open the application, where the line under the heading says what that means for the state this one is in.', 'wpcredits-program-manager' ) ),
			'app-email'            => array( 'error', __( 'Nothing was created. An account on this site already holds that address. Find out whose it is first: an account found by address with no institution stamp is a conflict, not a match.', 'wpcredits-program-manager' ) ),
			'app-country'          => array( 'error', __( 'Nothing was created. The country on the application no longer resolves in the Countries table. Run a sync and try again.', 'wpcredits-program-manager' ) ),
			'app-busy'             => array( 'error', __( 'Nothing happened. This application was already being approved, from a sync or from somebody else pressing the same button. Give it a minute and look at the row again.', 'wpcredits-program-manager' ) ),
			'app-incomplete'       => array( 'error', __( 'Nothing was created. This application does not carry the name, the address or the answers a record is made from. Ask the applicant for what is missing, or reject it.', 'wpcredits-program-manager' ) ),
			'app-failed'           => array( 'error', __( 'Nothing was finished. Airtable or this site refused a write; whatever half landed is stamped, and pressing Approve again completes the rest.', 'wpcredits-program-manager' ) ),
			'app-member-elsewhere' => array( 'error', __( 'Nothing was finished. The account for that address already acts for a different institution, so pressing Approve again cannot help: decide who that account belongs to first, and remove it from the other institution if this application is the right one.', 'wpcredits-program-manager' ) ),
			'app-member-taken'     => array( 'error', __( 'Nothing was finished. The account for that address is not one this can adopt, so pressing Approve again cannot help: a person has to decide what that account is for before this institution can have one.', 'wpcredits-program-manager' ) ),
			'app-question'         => array( 'error', __( 'Nothing was sent. Write the question you want answered: it is the whole of what the applicant is told.', 'wpcredits-program-manager' ) ),
			'app-no-email'         => array( 'error', __( 'Nothing was sent. This application carries no usable address, so there is nobody to ask.', 'wpcredits-program-manager' ) ),
			'app-not-sent'         => array( 'error', __( 'Nothing was sent and nothing moved. This site could not hand the question to its mail server, so the application is exactly where it was and the question is still yours to ask. Try again, and look at the recent mail on the settings screen if it keeps failing.', 'wpcredits-program-manager' ) ),
			'app-info'             => array( 'success', __( 'The question is on its way, with your address to reply to. The application waits in the queue for their answer.', 'wpcredits-program-manager' ) ),
			'app-rejected'         => array( 'success', __( 'The application is rejected and the applicant has a neutral acknowledgement with no reason in it. Your reason is on the application\'s own history, where the next manager can read it.', 'wpcredits-program-manager' ) ),
			'app-spam'             => array( 'info', __( 'The application is marked as spam. Nothing was sent: the address is forged or is somebody else\'s, and either way a reply is wrong.', 'wpcredits-program-manager' ) ),
			'app-reopened'         => array( 'info', __( 'The application is open again and back in the queue.', 'wpcredits-program-manager' ) ),
			'app-purged'           => array( 'success', __( 'The application is deleted. The log keeps its reference and the date, and nothing else.', 'wpcredits-program-manager' ) ),
		);
	}

	/*
	 * The screen
	 * --------------------------------------------------------------------
	 */

	/**
	 * Render the Institutions screen.
	 */
	public function render_admin_page() {
		$index    = WPCPM_Institutions_Index::read();
		$counts   = WPCPM_Roster_Index::counts();
		$progress = WPCPM_Institutions_Sync::progress();
		$last     = (int) WPCPM_Institutions_Sync::last_read();
		$filter   = WPCPM_Request::key( 'wpcpm_filter' );

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		// This screen's own outcomes; the three every sync screen shares come from the module.
		$messages = array(
			'probed'            => array( 'success', __( 'The probe ran. The storage card says what the host did.', 'wpcredits-program-manager' ) ),
			'probe-failed'      => array( 'error', __( 'The probe could not be completed. The storage card says why.', 'wpcredits-program-manager' ) ),
			'provisioned'       => array( 'success', __( 'The accounts were created, each with an invitation queued. The provisioning card says what is left.', 'wpcredits-program-manager' ) ),
			'provision-blocked' => array( 'error', __( 'Nothing was created: a Confirmed institution has no agreement recorded. The provisioning card names them.', 'wpcredits-program-manager' ) ),
			'provision-refused' => array( 'error', __( 'That institution cannot be given an account yet. The provisioning card says why.', 'wpcredits-program-manager' ) ),
			'provision-failed'  => array( 'error', __( 'Not every account could be created. The provisioning card names what is left.', 'wpcredits-program-manager' ) ),
			'provision-none'    => array( 'info', __( 'There was nothing to create.', 'wpcredits-program-manager' ) ),
		);

		// The on-file route's outcomes, named once in the class that draws its form: the same form
		// appears on this screen and on the institution's own agreement panel, and it lands
		// wherever it was pressed. Without this the outcome is taken from the channel and dropped.
		if ( class_exists( 'WPCPM_Institution_Panel' ) && method_exists( 'WPCPM_Institution_Panel', 'messages' ) ) {
			$messages = array_merge( $messages, (array) WPCPM_Institution_Panel::messages() );
		}

		// The queue's own outcomes, kept beside the handlers that flash them for the reason
		// the panel keeps the agreement route's: one list, in the words the reader gets.
		$messages = array_merge( $messages, self::queue_messages() );

		$this->render_status_notice( $messages );

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Airtable is not connected yet, so no institutions can be synced.', 'wpcredits-program-manager' ),
				esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
				esc_html__( 'Open settings', 'wpcredits-program-manager' )
			);
		}

		if ( ! empty( $progress['error'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( (string) $progress['error'] )
			);
		}

		$this->render_locked_accounts();

		// Read once for the whole screen: the two membership counts live on two different
		// cards, and `members_of()` is a query per institution, so computing them twice
		// would double every one of them for a number that cannot have changed in between.
		$gaps = self::membership_gaps( isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array() );

		$this->render_sync_panel( $progress, $last );
		$this->render_queue();
		$this->render_pipeline( $index, $filter, $gaps );
		$this->render_provisioning( $index );
		$this->render_reconciliation( $counts, $index, $gaps );
		$this->render_consent( $index );
		$this->render_discrepancies( $index );
		$this->render_semester_reports();
		$this->render_template( $index );
		$this->render_storage();

		echo '</div>';
	}

	/**
	 * The institution accounts whose roster write path is locked for the rest of today.
	 *
	 * The fence meters refused claims per acting account and locks the account when the
	 * day's ceiling fills (design spec 5.3). The lock is recorded once in that institution's
	 * log; this is where a manager sees every locked account at a glance, read from the same
	 * ceiling buckets that enforce it, so the notice and the lock cannot disagree.
	 */
	private function render_locked_accounts() {
		$locked = WPCPM_Institution_Roster::locked_today();

		if ( empty( $locked ) ) {
			return;
		}

		$names = array();

		foreach ( $locked as $user ) {
			$names[] = sprintf( '%1$s (%2$s)', $user->display_name, $user->user_login );
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: how many accounts. */
					_n( '%d institution account is locked out of roster changes for the rest of today.', '%d institution accounts are locked out of roster changes for the rest of today.', count( $locked ), 'wpcredits-program-manager' ),
					count( $locked )
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: the accounts, as display name (username), comma-separated. */
					__( 'Each was refused more claims than the daily ceiling allows: %s. The institution\'s log has the row, and the lock lifts by itself tomorrow.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				)
			)
		);
	}

	/**
	 * Sync controls and live progress.
	 *
	 * @param array $progress Progress payload from `WPCPM_Institutions_Sync::progress()`.
	 * @param int   $last     Timestamp of the last completed run.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		echo '<p class="description">' . esc_html__( 'Reads the Countries table, then every institution record\'s public columns and the eight agreement columns, and rebuilds the pipeline index and each institution\'s agreement state. The prose fields are never read.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! empty( $progress['running'] ) ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( self::ACTION_TICK ),
				esc_attr( wp_create_nonce( self::ACTION_TICK ) )
			);

			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( isset( $progress['label'] ) ? $progress['label'] : '' ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( isset( $progress['step_label'] ) ? $progress['step_label'] : '' ) );
			echo '</p>';

			$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;

			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $percent,
				esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
			);

			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> - ', (int) $percent );
			printf( '<span data-wpcpm-detail>%s</span> - ', esc_html( isset( $progress['detail'] ) ? $progress['detail'] : '' ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( isset( $progress['elapsed'] ) ? (int) $progress['elapsed'] : 0 ) ) )
			);
			echo '</p>';

			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				! empty( $progress['stalled'] ) ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The run may have been interrupted: cancel it and start again.', 'wpcredits-program-manager' )
			);

			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			submit_button( __( 'Cancel sync', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date and time, 2: human-readable time difference. */
							__( 'Last completed %1$s (%2$s ago).', 'wpcredits-program-manager' ),
							wp_date( 'Y-m-d H:i', $last ),
							human_time_diff( $last, time() )
						)
					)
				);
			} else {
				echo '<p>' . esc_html__( 'No sync has run yet.', 'wpcredits-program-manager' ) . '</p>';
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '" />';
			submit_button( __( 'Sync institutions now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The one thing waiting to be read: applications and signed agreements, oldest first.
	 *
	 * Drawn directly under the sync panel because it is the work, and everything below it is
	 * the state of the program. An open application is drawn above the list rather than
	 * instead of it, so a manager comparing two rows can see both.
	 */
	private function render_queue() {
		$open = self::application( WPCPM_Request::id( self::ARG_APPLICATION ) );

		if ( null !== $open ) {
			$this->render_application( $open );
		}

		$queue   = self::queue_rows( self::QUEUE_MAX );
		$rows    = $queue['rows'];
		$waiting = (int) $queue['waiting'];
		$days    = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );

		echo '<div class="wpcpm-card">';
		printf(
			'<h2 id="wpcpm-queue">%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Waiting for review', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $waiting ) )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of days. */
					__( 'Applications from institutions and signed agreements waiting to be read, in one list, oldest first: they are one queue and a person works it from the top. Anything that has waited longer than %s days is marked overdue.', 'wpcredits-program-manager' ),
					number_format_i18n( $days )
				)
			)
		);

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing is waiting. New applications and uploaded agreements appear here.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		// Said out loud whenever the list is shorter than the queue, so nobody reads the rows
		// on the screen as the whole of what is waiting for them.
		if ( count( $rows ) < $waiting ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many rows are drawn, 2: how many are waiting in total. */
						__( 'Showing the oldest %1$s of %2$s. The list stops there so that a burst of applications cannot make this screen too slow to open; decide these and the next of them take their place.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) ),
						number_format_i18n( $waiting )
					)
				)
			);
		}

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $row ) {
			$this->render_queue_row( $row );
		}

		echo '</ol>';
		echo '</div>';
	}

	/**
	 * One row of the queue.
	 *
	 * The age in words and the date in figures, because "4 days ago" is what a manager
	 * triages by and the date is what they quote in an email. The country's contact is
	 * printed for information and never mailed: routing says who knows this institution,
	 * not who else should hear about the row.
	 *
	 * A held row is marked as one. Every decision on this card is reachable from the list, so
	 * a state that only the open application admitted to would let somebody reject a
	 * submission the site had quietly decided was suspect without ever being told that it
	 * had. The mark says which rows those are; the application says why.
	 *
	 * @param array $row One row from `queue_rows()`.
	 */
	private function render_queue_row( array $row ) {
		$overdue = ! empty( $row['overdue'] );

		printf( '<li class="wpcpm-queue-item%s">', $overdue ? ' is-overdue' : '' );

		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( (string) $row['name'] ),
			esc_html( (string) $row['kind_label'] )
		);

		printf(
			'<p class="wpcpm-queue-age">%1$s%2$s</p>',
			esc_html(
				sprintf(
					/* translators: 1: human-readable time difference, 2: date and time. */
					__( 'Waiting %1$s, since %2$s.', 'wpcredits-program-manager' ),
					human_time_diff( (int) $row['at'], time() ),
					wp_date( 'Y-m-d H:i', (int) $row['at'] )
				)
			),
			$overdue
				? ' <span class="wpcpm-inst-mark wpcpm-inst-mark--overdue">' . esc_html__( 'overdue', 'wpcredits-program-manager' ) . '</span>'
				: ''
		);

		if ( '' !== (string) $row['country_name'] ) {
			printf(
				'<p class="wpcpm-queue-country">%s</p>',
				esc_html(
					'' !== (string) $row['contact']
						? sprintf(
							/* translators: 1: country, 2: the country's person of contact. */
							__( '%1$s. Person of contact: %2$s, for information.', 'wpcredits-program-manager' ),
							(string) $row['country_name'],
							(string) $row['contact']
						)
						: sprintf(
							/* translators: %s: country. */
							__( '%s. The Countries table names nobody for it, for information.', 'wpcredits-program-manager' ),
							(string) $row['country_name']
						)
				)
			);
		}

		if ( WPCPM_Institution_Application::STATE_HELD === (string) $row['state'] ) {
			$signals = isset( $row['signals'] ) ? (array) $row['signals'] : array();

			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--held" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'The anti-spam checks held this submission. Nothing was announced about it, and none of the checks refused it.', 'wpcredits-program-manager' ),
				esc_html__( 'held', 'wpcredits-program-manager' ),
				esc_html(
					empty( $signals )
						? __( 'Held with no check recorded against it. Open it and decide it on what is on it.', 'wpcredits-program-manager' )
						: sprintf(
							/* translators: %s: how many checks held the submission. */
							_n( '%s check held it; open the application to read it.', '%s checks held it; open the application to read them.', count( $signals ), 'wpcredits-program-manager' ),
							number_format_i18n( count( $signals ) )
						)
				)
			);
		}

		if ( ! empty( $row['duplicate'] ) ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--duplicate" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'Another application in the queue names the same institution or the same address.', 'wpcredits-program-manager' ),
				esc_html__( 'possible duplicate', 'wpcredits-program-manager' ),
				esc_html__( 'Open both and decide; nothing is ever merged.', 'wpcredits-program-manager' )
			);
		}

		if ( 'application' === $row['kind'] ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a> <span class="wpcpm-inst-muted">%3$s</span></p>',
				esc_url( add_query_arg( self::ARG_APPLICATION, (int) $row['id'], $this->admin_url() ) ),
				esc_html__( 'Open this application', 'wpcredits-program-manager' ),
				esc_html( self::application_state_label( (string) $row['state'] ) )
			);
		} else {
			// The review block itself: the checklist, the flags, the download link and the
			// two decisions. Drawn by the panel that owns every agreement control, so the
			// queue and the institution's own row cannot drift apart.
			WPCPM_Institution_Panel::render_review( (int) $row['id'] );
		}

		echo '</li>';
	}

	/**
	 * One application, open: every answer, the consent, the verification and the decisions.
	 *
	 * @param WP_Post $post The application.
	 */
	private function render_application( WP_Post $post ) {
		$state = self::application_state( $post );
		$name  = trim( (string) $post->post_title );

		echo '<div class="wpcpm-card wpcpm-application" id="wpcpm-application">';

		printf(
			'<h2>%1$s <span class="wpcpm-inst-muted">%2$s</span></h2>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( self::application_reference( $post ) )
		);

		printf(
			'<p class="wpcpm-inst-read">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html(
				sprintf(
					/* translators: 1: application state, 2: date and time it arrived. */
					__( 'State: %1$s. Arrived %2$s.', 'wpcredits-program-manager' ),
					self::application_state_label( $state ),
					wp_date( 'Y-m-d H:i', (int) get_post_time( 'U', true, $post ) )
				)
			),
			esc_url( $this->admin_url() . '#wpcpm-queue' ),
			esc_html__( 'Back to the queue', 'wpcredits-program-manager' )
		);

		// Before the address line and before a single answer: what the site thinks of this
		// submission is the context every other thing on the card is read in.
		$this->render_signals( $post, $state );

		echo '<p>' . esc_html( self::verification_line( $post, $state ) ) . '</p>';

		$this->render_application_answers( $post );
		$this->render_duplicate_search( $post );
		$this->render_application_actions( $post, $state );

		echo '</div>';
	}

	/**
	 * Every answer the applicant gave, in the order the form asked for them.
	 *
	 * The columns come from the form's own map, so a question added there appears here
	 * without a second list to keep in step, and the two the server holds rather than the
	 * applicant, the country and the consent, are answered from the meta the submission
	 * stamped. Every value is printed escaped: this is a stranger's prose on an admin screen.
	 *
	 * The question is printed as the applicant read it, with the Airtable column under it,
	 * because the manager deciding this is the person who will look the record up in the base.
	 *
	 * @param WP_Post $post The application.
	 */
	private function render_application_answers( WP_Post $post ) {
		$fields  = self::application_fields( $post );
		$columns = WPCPM_Institutions_Sync::fields();
		$country = (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY, true );

		echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

		foreach ( WPCPM_Institution_Application::fields() as $column => $spec ) {
			$column = (string) $column;
			$label  = is_array( $spec ) && ! empty( $spec['label'] ) ? (string) $spec['label'] : $column;

			if ( isset( $columns['country'] ) && $columns['country'] === $column ) {
				$value = self::country_name( $country, (string) get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_COUNTRY_NAME, true ) );
			} elseif ( isset( $columns['consent'] ) && $columns['consent'] === $column ) {
				$value = self::consent_line( get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_CONSENT, true ) );
			} else {
				$answer = isset( $fields[ $column ] ) ? $fields[ $column ] : '';
				$value  = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : (string) $answer;
			}

			printf(
				'<tr><th scope="row">%1$s<br /><code class="wpcpm-inst-record">%2$s</code></th><td>%3$s</td></tr>',
				esc_html( $label ),
				esc_html( $column ),
				'' !== trim( $value )
					? esc_html( $value )
					: '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>'
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The consent record in one sentence.
	 *
	 * The sentence they were shown, when they agreed, and when the policy was last changed,
	 * because "they agreed" is worthless if the policy moved afterwards and this is where a
	 * manager can see that it did.
	 *
	 * @param mixed $consent The stored consent record.
	 * @return string
	 */
	private static function consent_line( $consent ) {
		if ( ! is_array( $consent ) || empty( $consent ) ) {
			return __( 'No consent record was stored with this application.', 'wpcredits-program-manager' );
		}

		$at    = isset( $consent['at'] ) ? (int) $consent['at'] : 0;
		$parts = array(
			$at > 0
				? sprintf(
					/* translators: %s: date and time. */
					__( 'Agreed %s.', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $at )
				)
				: __( 'Agreed; no time was recorded.', 'wpcredits-program-manager' ),
		);

		if ( ! empty( $consent['sentence'] ) ) {
			$parts[] = (string) $consent['sentence'];
		}

		if ( ! empty( $consent['url'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: privacy policy address. */
				__( 'Policy: %s', 'wpcredits-program-manager' ),
				(string) $consent['url']
			);
		}

		if ( ! empty( $consent['modified'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: date the privacy policy was last changed. */
				__( 'The policy was last changed %s; anything later than the agreement above is not what they read.', 'wpcredits-program-manager' ),
				(string) $consent['modified']
			);
		}

		return implode( ' ', $parts );
	}

	/**
	 * What the form's checks made of this submission, in plain words.
	 *
	 * This screen is the only place `_wpcpm_app_signals` is ever read back, and until it was
	 * printed a held row looked like any other: a manager pressing Reject or Send this
	 * question on one was acting on a submission the site had quietly decided was suspect,
	 * without being shown either that or why. Every decision here is somebody's judgement
	 * about an institution, and a judgement taken on a hidden opinion is not one.
	 *
	 * Printed for every application and not only for a held one. Two of the checks hold
	 * nothing by themselves, `duplicate` and the site-wide ceiling, so a row can be `new` and
	 * still have something on it worth reading; and "nothing was flagged" is evidence too,
	 * which is why an empty list is printed as an empty list rather than as no block at all.
	 *
	 * @param WP_Post $post  The application.
	 * @param string  $state Its state.
	 */
	private function render_signals( WP_Post $post, $state ) {
		$held    = WPCPM_Institution_Application::STATE_HELD === (string) $state;
		$signals = self::signals( $post );
		$labels  = self::signal_labels();

		printf(
			'<h3>%s</h3>',
			esc_html(
				$held
					? __( 'Why this application is held', 'wpcredits-program-manager' )
					: __( 'What the checks made of it', 'wpcredits-program-manager' )
			)
		);

		if ( $held ) {
			// What holding actually does, which is narrower than it sounds and worth saying
			// plainly: it spares the managers a message, and nothing else. The applicant was
			// acknowledged like anybody else and holds the link that confirms their address,
			// so this row can be approved. Saying otherwise would steer the decision, which
			// is what the old wording here did.
			echo '<p>' . esc_html__( 'Holding spares the managers a message and nothing more. The applicant was acknowledged exactly as any other was, and holds the link that confirms their address, so this application can be approved once that link is used. None of these checks refused anything: each one holds a row for a person to look at, which is what this is.', 'wpcredits-program-manager' ) . '</p>';

			if ( in_array( 'mail-ceiling', $signals, true ) ) {
				// The one exception, and the only case where nobody wrote to them.
				echo '<p>' . esc_html__( 'Except this one: the day\'s limit on acknowledgements had been reached when it arrived, so no message was sent and the address is unconfirmed. Ask them for it by hand before approving.', 'wpcredits-program-manager' ) . '</p>';
			}
		}

		if ( empty( $signals ) ) {
			echo '<p>' . esc_html(
				$held
					? __( 'No check recorded anything against it. The state says held and nothing says why, so decide it on what is on it.', 'wpcredits-program-manager' )
					: __( 'Nothing. Every check the form makes passed.', 'wpcredits-program-manager' )
			) . '</p>';

			return;
		}

		echo '<ul class="wpcpm-app-signals">';

		foreach ( $signals as $signal ) {
			printf( '<li>%s</li>', esc_html( isset( $labels[ $signal ] ) ? $labels[ $signal ] : self::unnamed_signal( $signal ) ) );
		}

		echo '</ul>';
	}

	/**
	 * Every check the form can raise, in the words a manager reads.
	 *
	 * Kept here rather than beside the checks that raise them, for the reason
	 * `queue_messages()` keeps the outcomes here: what a slug means to the check is not what
	 * it has to mean to the person deciding. The numbers are read from the form's own
	 * constants, so a limit changed there cannot leave a sentence here quoting the old one.
	 *
	 * The slugs are the form's literals and there is nothing to name them through, so a check
	 * added there and not here prints as itself rather than disappearing: see
	 * `unnamed_signal()`, which is the whole reason this is a lookup and not a switch.
	 *
	 * @return array<string, string>
	 */
	private static function signal_labels() {
		return array(
			'honeypot'             => __( 'A field no visitor can see was filled in, which is what an automated submission does and a person cannot.', 'wpcredits-program-manager' ),
			'dwell'                => sprintf(
				/* translators: %s: a number of seconds. */
				__( 'It arrived without the token the form hands a browser, or less than %s seconds after the page was drawn.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Institution_Application::MIN_SECONDS )
			),
			'disallowed'           => __( 'Something written on it matches this site\'s comment disallowed list.', 'wpcredits-program-manager' ),
			'links'                => sprintf(
				/* translators: %s: a number of links. */
				__( 'The written answers carry %s links or more.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Institution_Application::MAX_LINKS )
			),
			'identical'            => __( 'The same paragraph was given as the answer to more than one question.', 'wpcredits-program-manager' ),
			'short'                => sprintf(
				/* translators: %s: a number of characters. */
				__( 'The answer about why they are interested is shorter than %s characters.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Institution_Application::MIN_REASON )
			),
			'no-mx'                => __( 'Nothing at the domain of the address they gave looks able to receive mail, so a reply may reach nobody.', 'wpcredits-program-manager' ),
			'name-is-contact'      => __( 'The institution and the person to contact were given the same name, which is what a script with one string to offer does.', 'wpcredits-program-manager' ),
			'site-ceiling'         => sprintf(
				/* translators: %s: how many applications a day the site accepts before it starts holding them. */
				__( 'The site had already taken %s applications that day, so this one was kept and held instead of being refused. It says nothing about the application itself.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Institution_Application::PER_DAY )
			),
			self::SIGNAL_DUPLICATE => __( 'Another application already named this institution or this address. Nothing is ever merged: open both and decide.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * A check this screen has no sentence for.
	 *
	 * Its own line rather than nothing at all: a check added to the form after this list was
	 * written is still a reason a submission is sitting in front of somebody, and a manager
	 * who can see the slug can go and ask what it means. A manager shown nothing cannot.
	 *
	 * @param string $signal The stored slug.
	 * @return string
	 */
	private static function unnamed_signal( $signal ) {
		return sprintf(
			/* translators: %s: the stored name of a check, e.g. no-mx. */
			__( 'A check this screen has no words for yet, recorded as "%s".', 'wpcredits-program-manager' ),
			$signal
		);
	}

	/**
	 * Whether the applicant has confirmed the address, in a sentence that fits the state.
	 *
	 * Its own line above the answers rather than a column in them: approval refuses an
	 * unconfirmed address, so this is the first thing a manager needs to know and the reason
	 * the Approve button will not work if they do not read it.
	 *
	 * A held row gets its own sentence. The one this used to print sent the manager off to
	 * wait for "the link in their acknowledgement", and a held submission is the one nothing
	 * was announced about, so it named a mail that may never have left. What is true of both
	 * is that confirming is the applicant's own act, which is the whole point of it; what is
	 * different is what an unconfirmed address on a held row is evidence of, which is
	 * nothing. Held is not a state this refuses to describe a way out of: the link is signed
	 * against the application and not against its state, so a held row can carry a
	 * confirmation like any other and be approved on it.
	 *
	 * @param WP_Post $post  The application.
	 * @param string  $state Its state.
	 * @return string
	 */
	private static function verification_line( WP_Post $post, $state ) {
		$verified = get_post_meta( (int) $post->ID, WPCPM_Institution_Application::META_VERIFIED, true );

		if ( '' === trim( (string) $verified ) ) {
			return WPCPM_Institution_Application::STATE_HELD === (string) $state
				? __( 'The address has not been confirmed, and nothing on this row says the applicant was ever asked to: read the checks above before you read the silence. Approving mails a password-set link and is refused until the address is confirmed, and only the applicant can confirm it. Ask them something from here if you need to, or decide the row on what is on it.', 'wpcredits-program-manager' )
				: __( 'The address has not been confirmed yet. The acknowledgement carried the link that confirms it, and approving mails a password-set link, so approval is refused until the applicant follows that link.', 'wpcredits-program-manager' );
		}

		if ( is_numeric( $verified ) ) {
			return sprintf(
				/* translators: %s: date and time. */
				__( 'The applicant confirmed their address on %s.', 'wpcredits-program-manager' ),
				wp_date( 'Y-m-d H:i', (int) $verified )
			);
		}

		return __( 'The applicant has confirmed their address.', 'wpcredits-program-manager' );
	}

	/**
	 * What Airtable already holds under this name or this address.
	 *
	 * The one live read on this screen, and it happens only for an application somebody has
	 * opened: the queue itself never touches the base, so a screen full of rows costs no
	 * requests. Two tests, because the base is full of records promoted from outreach that
	 * have a name and no `Contact Email` at all, and matching only on the address would miss
	 * every one of them.
	 *
	 * A match is information and never an action: whether this is the same institution is a
	 * judgement, and the approval makes the same search again when it is pressed.
	 *
	 * @param WP_Post $post The application.
	 */
	private function render_duplicate_search( WP_Post $post ) {
		echo '<h3>' . esc_html__( 'What Airtable already has', 'wpcredits-program-manager' ) . '</h3>';

		if ( ! WPCPM_Settings::is_connected() ) {
			echo '<p>' . esc_html__( 'Airtable is not connected, so no search was made.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		$matches = self::duplicate_search( trim( (string) $post->post_title ), self::application_email( $post ) );

		if ( is_wp_error( $matches ) ) {
			printf(
				'<p class="wpcpm-warning">%1$s %2$s</p>',
				esc_html__( 'The search could not be made:', 'wpcredits-program-manager' ),
				esc_html( $matches->get_error_message() )
			);

			return;
		}

		if ( empty( $matches ) ) {
			echo '<p>' . esc_html__( 'No Institutions record carries this name or this address. Approving creates one.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<p>' . esc_html__( 'These records match on the trimmed name or on the address. Approving adopts the first of them rather than creating a second:', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-app-matches">';

		foreach ( $matches as $match ) {
			printf(
				'<li><span class="wpcpm-inst-name__text">%1$s</span> <code class="wpcpm-inst-record">%2$s</code> %3$s</li>',
				esc_html( '' !== $match['name'] ? $match['name'] : __( '(no name)', 'wpcredits-program-manager' ) ),
				esc_html( $match['record_id'] ),
				esc_html( '' !== $match['stage'] ? $match['stage'] : __( 'no stage', 'wpcredits-program-manager' ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * Ask the base what it holds under this name or this address.
	 *
	 * `TRIM(LOWER({Name}))` on the field side because ten names in the table end in a space
	 * and the base is the side this code does not hold; the needle is lowered here. The
	 * address half goes through `formula_in()` with its `LOWER()` flag, which is what that
	 * flag is for.
	 *
	 * @param string $name  The institution name as applied under.
	 * @param string $email The address as applied under.
	 * @return array[]|WP_Error Matches: `record_id`, `name`, `stage`.
	 */
	private static function duplicate_search( $name, $email ) {
		$airtable = new WPCPM_Airtable();
		$columns  = WPCPM_Institutions_Sync::fields();
		$settings = WPCPM_Settings::get();
		$tests    = array();

		if ( '' !== trim( (string) $name ) ) {
			$tests[] = sprintf(
				'TRIM(LOWER({%1$s})) = %2$s',
				str_replace( array( '\\', '}' ), array( '\\\\', '\\}' ), $columns['name'] ),
				self::formula_literal( self::lower( $name ) )
			);
		}

		$by_email = $airtable->formula_in( $columns['contact_email'], array( (string) $email ), true );

		if ( '' !== $by_email ) {
			$tests[] = $by_email;
		}

		if ( empty( $tests ) ) {
			return array();
		}

		$page = $airtable->fetch_page(
			isset( $settings['institutions_table'] ) ? (string) $settings['institutions_table'] : '',
			array(
				'formula' => 1 === count( $tests ) ? $tests[0] : 'OR(' . implode( ', ', $tests ) . ')',
				'fields'  => array( $columns['name'], $columns['contact_email'], $columns['stage'] ),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$matches = array();

		foreach ( $page['records'] as $record ) {
			$record_id = isset( $record['id'] ) ? trim( (string) $record['id'] ) : '';

			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$cells = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();

			$matches[] = array(
				'record_id' => $record_id,
				'name'      => trim( WPCPM_Airtable::flatten( isset( $cells[ $columns['name'] ] ) ? $cells[ $columns['name'] ] : '' ) ),
				'stage'     => trim( WPCPM_Airtable::flatten( isset( $cells[ $columns['stage'] ] ) ? $cells[ $columns['stage'] ] : '' ) ),
			);
		}

		return $matches;
	}

	/**
	 * A string literal for an Airtable formula.
	 *
	 * `WPCPM_Airtable::formula_in()` builds its own and keeps its quoting private; this is
	 * the one formula in the plugin that is not a list of equalities, so it quotes its own
	 * needle the same way.
	 *
	 * @param string $value The value.
	 * @return string
	 */
	private static function formula_literal( $value ) {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $value ) . "'";
	}

	/**
	 * The decisions available on an open application.
	 *
	 * Which forms appear is the state's answer and the handlers ask it again: a form nobody
	 * may post is a courtesy that saves a reader a refusal, never the check itself.
	 *
	 * @param WP_Post $post  The application.
	 * @param string  $state Its state.
	 */
	private function render_application_actions( WP_Post $post, $state ) {
		echo '<h3>' . esc_html__( 'What happens next', 'wpcredits-program-manager' ) . '</h3>';

		$name  = trim( (string) $post->post_title );
		$name  = '' !== $name ? $name : self::application_reference( $post );
		$email = self::application_email( $post );

		if ( in_array( $state, self::open_states(), true ) ) {
			// The one address this screen prints, and design spec 7.3 asks for it by name:
			// the next thing that happens is a password-set link being mailed, and "to the
			// address on the application" is not something a manager can check.
			$this->render_decision_form(
				$post,
				array(
					'action'  => self::ACTION_APPROVE,
					'label'   => __( 'Approve', 'wpcredits-program-manager' ),
					'class'   => 'button button-primary',
					'confirm' => sprintf(
						/* translators: 1: institution name, 2: contact email address. */
						__( 'Create an Airtable record and a site account for %1$s, and email a password-set link to %2$s? The Airtable record cannot be removed from here.', 'wpcredits-program-manager' ),
						$name,
						'' !== $email ? $email : __( 'the address on the application', 'wpcredits-program-manager' )
					),
				)
			);

			$this->render_decision_form(
				$post,
				array(
					'action' => self::ACTION_INFO,
					'label'  => __( 'Send this question', 'wpcredits-program-manager' ),
					'field'  => 'wpcpm_question',
					'prompt' => __( 'Ask the applicant something. It is sent as it is written, with your address to reply to.', 'wpcredits-program-manager' ),
				)
			);

			$this->render_decision_form(
				$post,
				array(
					'action'  => self::ACTION_REJECT,
					'label'   => __( 'Reject', 'wpcredits-program-manager' ),
					'field'   => 'wpcpm_reason',
					'prompt'  => __( 'Why, for the next manager who reads this. It is never sent to the applicant.', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: institution name. */
						__( 'Reject the application from %s? They get a short acknowledgement with no reason in it, and your note stays on this site.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);

			$this->render_decision_form(
				$post,
				array(
					'action'  => self::ACTION_SPAM,
					'label'   => __( 'Reject as spam', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: institution name. */
						__( 'Mark the application from %s as spam? Nothing at all is sent to the address on it.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( in_array( $state, self::reopen_states(), true ) ) {
			$this->render_decision_form(
				$post,
				array(
					'action' => self::ACTION_REOPEN,
					'label'  => __( 'Put back in the queue', 'wpcredits-program-manager' ),
				)
			);
		}

		if ( in_array( $state, self::purgeable_states(), true ) ) {
			$this->render_decision_form(
				$post,
				array(
					'action'  => self::ACTION_PURGE,
					'label'   => __( 'Delete for good', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: institution name. */
						__( 'Delete the application from %s for good? Every answer on it goes; only its reference and the date are kept. This cannot be undone.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}
	}

	/**
	 * One decision's form.
	 *
	 * The nonce is keyed to the action and the application together, so a nonce harvested
	 * from one row's Reject cannot approve another's.
	 *
	 * @param WP_Post $post The application.
	 * @param array   $args `action`, `label`, `class`, `confirm`, `field`, `prompt`.
	 */
	private function render_decision_form( WP_Post $post, array $args ) {
		$args = array_merge(
			array(
				'action'  => '',
				'label'   => '',
				'class'   => 'button',
				'confirm' => '',
				'field'   => '',
				'prompt'  => '',
			),
			$args
		);

		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			'' !== $args['confirm'] ? ' onsubmit="return confirm(\'' . esc_js( $args['confirm'] ) . '\');"' : ''
		);
		wp_nonce_field( $args['action'] . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_APPLICATION ), (int) $post->ID );

		if ( '' !== $args['field'] ) {
			printf(
				'<p><label for="%1$s">%2$s</label><br /><textarea class="large-text" id="%1$s" name="%1$s" rows="4" maxlength="%3$d"></textarea></p>',
				esc_attr( $args['field'] ),
				esc_html( $args['prompt'] ),
				(int) self::MAX_NOTE
			);
		}

		printf(
			'<p><button type="submit" class="%1$s">%2$s</button></p>',
			esc_attr( $args['class'] ),
			esc_html( $args['label'] )
		);
		echo '</form>';
	}

	/**
	 * The pipeline: every institution record, grouped by stage.
	 *
	 * @param array  $index  The pipeline index, from `WPCPM_Institutions_Index::read()`.
	 * @param string $filter The `wpcpm_filter` argument, already sanitised.
	 * @param array  $gaps   The membership counts, from `membership_gaps()`.
	 */
	private function render_pipeline( array $index, $filter, array $gaps ) {
		$rows      = isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array();
		$read      = isset( $index['read'] ) ? (int) $index['read'] : 0;
		$summaries = array();

		foreach ( $rows as $record_id => $row ) {
			$summaries[ $record_id ] = WPCPM_Institution_Agreement::summary( $record_id );
		}

		$gap = self::agreement_gap( $rows, $summaries );

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Pipeline', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $rows ) ) )
		);

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No institution records yet. Run a sync to read them.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		if ( self::FILTER_GAP === $filter ) {
			printf(
				'<p class="wpcpm-inst-gap">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: number of institutions. */
						_n(
							'Showing the %s Confirmed institution with no agreement recorded.',
							'Showing the %s Confirmed institutions with no agreement recorded.',
							count( $gap ),
							'wpcredits-program-manager'
						),
						number_format_i18n( count( $gap ) )
					)
				),
				esc_url( $this->admin_url() ),
				esc_html__( 'Show every stage', 'wpcredits-program-manager' )
			);
		} else {
			printf(
				'<p class="wpcpm-inst-gap"><a href="%1$s">%2$s <span class="wpcpm-count">%3$s</span></a></p>',
				esc_url( add_query_arg( 'wpcpm_filter', self::FILTER_GAP, $this->admin_url() ) ),
				esc_html__( 'Confirmed with no agreement recorded', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( count( $gap ) ) )
			);
		}

		$this->render_member_gap( $gaps, $read );
		$this->render_routing_gaps( $rows );

		if ( self::FILTER_GAP === $filter ) {
			$groups = array( 'Confirmed' => $gap );
		} else {
			$groups = WPCPM_Institutions_Index::by_stage();
		}

		foreach ( $groups as $stage => $group ) {
			if ( empty( $group ) ) {
				continue;
			}

			printf(
				'<h3 class="wpcpm-inst-stage">%1$s <span class="wpcpm-count">%2$s</span></h3>',
				esc_html( self::stage_label( $stage ) ),
				esc_html( number_format_i18n( count( $group ) ) )
			);

			echo '<table class="widefat striped wpcpm-list wpcpm-inst-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Country', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'City', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Contact', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Created', 'wpcredits-program-manager' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Agreement', 'wpcredits-program-manager' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $group as $row ) {
				$record_id = isset( $row['record_id'] ) ? (string) $row['record_id'] : '';
				$summary   = isset( $summaries[ $record_id ] ) ? $summaries[ $record_id ] : WPCPM_Institution_Agreement::summary( $record_id );

				$this->render_pipeline_row( $row, $summary );
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * One institution's row in the pipeline.
	 *
	 * The name prints trimmed and the row says when the stored one was not: ten names in
	 * the base end in a space and two records have none, and a manager searching the grid
	 * for "Sorbonne university" should know why the match is not exact.
	 *
	 * @param array $row     An index row.
	 * @param array $summary The institution's agreement summary.
	 */
	private function render_pipeline_row( array $row, array $summary ) {
		$name    = isset( $row['name'] ) ? (string) $row['name'] : '';
		$trimmed = trim( $name );

		echo '<tr>';

		echo '<td class="wpcpm-inst-name">';

		if ( '' === $trimmed ) {
			printf(
				'<span class="wpcpm-inst-name__text wpcpm-inst-muted">%1$s</span> <span class="wpcpm-inst-mark wpcpm-inst-mark--empty" title="%2$s">%3$s</span>',
				esc_html__( '(no name)', 'wpcredits-program-manager' ),
				esc_attr__( 'This record has no Name in Airtable.', 'wpcredits-program-manager' ),
				esc_html__( 'no name', 'wpcredits-program-manager' )
			);
		} else {
			printf( '<span class="wpcpm-inst-name__text">%s</span>', esc_html( $trimmed ) );

			if ( $name !== $trimmed ) {
				printf(
					' <span class="wpcpm-inst-mark wpcpm-inst-mark--space" title="%1$s">%2$s</span>',
					esc_attr__( 'The Name in Airtable has whitespace around it; it prints trimmed here.', 'wpcredits-program-manager' ),
					esc_html__( 'whitespace', 'wpcredits-program-manager' )
				);
			}
		}

		if ( ! empty( $row['record_id'] ) ) {
			printf( '<br /><code class="wpcpm-inst-record">%s</code>', esc_html( (string) $row['record_id'] ) );
		}

		echo '</td>';

		$country = isset( $row['country'] ) ? (string) $row['country'] : '';

		if ( '' === $country ) {
			printf( '<td><span class="wpcpm-inst-muted">%s</span></td>', esc_html__( 'no country', 'wpcredits-program-manager' ) );
		} else {
			$country_name = WPCPM_Countries::name_of( $country );

			if ( '' === $country_name ) {
				$country_name = isset( $row['country_name'] ) ? (string) $row['country_name'] : '';
			}

			echo '<td>';
			echo esc_html( '' !== $country_name ? $country_name : __( 'unknown country', 'wpcredits-program-manager' ) );

			if ( null === WPCPM_Countries::routing( $country ) ) {
				printf(
					' <span class="wpcpm-inst-mark wpcpm-inst-mark--routing" title="%1$s">%2$s</span>',
					esc_attr__( 'The Countries table names no program manager for this country.', 'wpcredits-program-manager' ),
					esc_html__( 'no contact', 'wpcredits-program-manager' )
				);
			}

			echo '</td>';
		}

		printf( '<td>%s</td>', esc_html( isset( $row['city'] ) ? (string) $row['city'] : '' ) );

		if ( ! empty( $row['contact_email'] ) ) {
			printf( '<td>%s</td>', esc_html__( 'email on record', 'wpcredits-program-manager' ) );
		} else {
			printf( '<td><span class="wpcpm-warning">%s</span></td>', esc_html__( 'no email', 'wpcredits-program-manager' ) );
		}

		printf(
			'<td>%s</td>',
			! empty( $row['consent'] ) ? esc_html__( 'yes', 'wpcredits-program-manager' ) : esc_html__( 'no', 'wpcredits-program-manager' )
		);

		printf( '<td>%s</td>', esc_html( isset( $row['created'] ) ? (string) $row['created'] : '' ) );

		printf(
			'<td class="wpcpm-inst-agreement%1$s">%2$s</td>',
			self::is_settled_summary( $summary ) ? ' wpcpm-inst-agreement--settled' : '',
			esc_html( self::describe_summary( $summary ) )
		);

		echo '</tr>';
	}

	/**
	 * Institutions nobody can act for, and the backstop count that is not shown yet.
	 *
	 * The design calls this "the one that pages a manager", so it prints inside the pipeline
	 * card where a manager looks first rather than among the reconciliation rows at the foot
	 * of the screen. An institution with no live member has nobody at the school who can see
	 * their own roster, upload their signed agreement, or answer for it.
	 *
	 * The third count the design asks for, invitations older than seven days, is a line and
	 * not a number: the invitation post type ships with a later phase, and a zero printed
	 * beside two real counts would read as "none are overdue" rather than "none exist".
	 *
	 * @param array $gaps From `membership_gaps()`.
	 * @param int   $read Unix time the pipeline index was read.
	 */
	private function render_member_gap( array $gaps, $read ) {
		printf(
			'<p class="wpcpm-inst-members">%1$s <span class="wpcpm-count">%2$s</span> <span class="wpcpm-inst-muted">%3$s</span></p>',
			esc_html__( 'Institutions with no live member', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $gaps['no_member'] ) ? (int) $gaps['no_member'] : 0 ) ),
			esc_html( self::membership_read_line( $read ) )
		);

		echo '<p class="description">' . esc_html__( 'Nobody at these schools can act for them on this site. Adding an account by hand ships with the accounts phase; today the only route in is the sync provisioning an institution\'s Contact Email.', 'wpcredits-program-manager' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Invitations ship with a later phase, so the third backstop count, invitations older than seven days, is not shown: there are none to count yet.', 'wpcredits-program-manager' ) . '</p>';
	}

	/**
	 * Countries that institutions name and the Countries table routes nowhere.
	 *
	 * A routing gap, never an error: the acknowledgement for an applicant from one of these
	 * countries has no manager to name, and the queue shows no contact for information. Read
	 * from the index rows and the countries map, so the list is the three the base has today
	 * and not the fifty-eight countries nobody has applied from.
	 *
	 * @param array $rows Index rows.
	 */
	private function render_routing_gaps( array $rows ) {
		$gaps      = array();
		$countries = WPCPM_Countries::read();
		$read      = isset( $countries['read'] ) ? (int) $countries['read'] : 0;

		foreach ( $rows as $row ) {
			$country = isset( $row['country'] ) ? (string) $row['country'] : '';

			if ( '' === $country || null !== WPCPM_Countries::routing( $country ) ) {
				continue;
			}

			$label = WPCPM_Countries::name_of( $country );

			if ( '' === $label ) {
				$label = isset( $row['country_name'] ) && '' !== (string) $row['country_name']
					? (string) $row['country_name']
					: $country;
			}

			$gaps[ $label ] = isset( $gaps[ $label ] ) ? $gaps[ $label ] + 1 : 1;
		}

		$this->read_line( $read, __( 'Countries map', 'wpcredits-program-manager' ) );

		if ( empty( $gaps ) ) {
			echo '<p>' . esc_html__( 'Every country an institution names has a program manager contact.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		ksort( $gaps );

		$parts = array();

		foreach ( $gaps as $label => $count ) {
			$parts[] = sprintf( '%1$s (%2$s)', $label, number_format_i18n( $count ) );
		}

		printf(
			'<p class="wpcpm-inst-routing">%1$s %2$s</p>',
			esc_html__( 'Countries named by institutions with no program manager contact in the Countries table:', 'wpcredits-program-manager' ),
			esc_html( implode( ', ', $parts ) )
		);
	}

	/**
	 * Institution accounts: the bulk button, the gate in front of it, and a row apiece.
	 *
	 * Every Confirmed institution is listed with either the control that creates its account
	 * or the sentence saying why it has none, because a worklist that hides the refusals is a
	 * worklist a manager cannot finish. Which of the two a row gets is
	 * `WPCPM_Institutions_Sync::provision_block()`'s answer and never a second copy of the
	 * rule here, so this card, the button it draws and the nightly run cannot disagree.
	 *
	 * The gate is the design's: while any Confirmed institution has no agreement recorded the
	 * bulk button refuses, naming them and saying how many, whatever else is ready. Recording
	 * the agreement per institution is what stops a partner that signed years ago being
	 * emailed that its first step is to sign.
	 *
	 * "Recorded" here is `is_settled()`, the same option the policy's gate reads, rather than
	 * the pipeline card's summary: this is the predicate provisioning will actually be
	 * refused by, and when the two sides disagree the discrepancies card is where that is
	 * reported.
	 *
	 * @param array $index The pipeline index, from `WPCPM_Institutions_Index::read()`.
	 */
	private function render_provisioning( array $index ) {
		$rows    = isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array();
		$read    = isset( $index['read'] ) ? (int) $index['read'] : 0;
		$reasons = self::provision_reasons( $rows );

		$ready   = array_keys( $reasons, '', true );
		$blocked = array_keys( $reasons, WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT, true );
		$held    = array_keys( $reasons, WPCPM_Institutions_Sync::BLOCK_HAS_MEMBER, true );

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Institution accounts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $ready ) ) )
		);

		echo '<p class="description">' . esc_html__( 'The first account for an institution is made from the Contact Email Airtable holds for it, and only for a Confirmed institution whose agreement is recorded and that has never had a member. After that first account, membership is managed here: without that rule a contact who was removed would be given a new account every night. An address that already belongs to an account is a conflict and not a match, and is left alone.', 'wpcredits-program-manager' ) . '</p>';

		printf(
			'<p class="wpcpm-inst-read">%1$s %2$s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of institutions. */
					_n( '%s Confirmed institution.', '%s Confirmed institutions.', count( $reasons ), 'wpcredits-program-manager' ),
					number_format_i18n( count( $reasons ) )
				)
			),
			esc_html( self::membership_read_line( $read ) )
		);

		if ( empty( $reasons ) ) {
			echo '<p>' . esc_html__( 'No institution has reached Confirmed yet, so there is nothing to provision.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		$this->render_provision_gate( $blocked );
		$this->render_provision_button( count( $ready ), empty( $blocked ) );
		$this->render_provision_rows( $reasons );

		if ( ! empty( $held ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of institutions. */
						_n( '%s Confirmed institution already has an account and is not listed above.', '%s Confirmed institutions already have an account and are not listed above.', count( $held ), 'wpcredits-program-manager' ),
						number_format_i18n( count( $held ) )
					)
				)
			);
		}

		// Which of the two routes is live, said plainly: the same rule decides both, and a
		// manager who presses nothing here should still know whether accounts appear overnight.
		printf(
			'<p class="description">%s</p>',
			esc_html(
				WPCPM_Settings::get_value( 'institution_provision' )
					? __( 'The nightly sync creates these accounts too, by the same rule, on top of anything made here.', 'wpcredits-program-manager' )
					: __( 'The nightly sync does not create accounts: this card is the only way one is made.', 'wpcredits-program-manager' )
			)
		);

		echo '</div>';
	}

	/**
	 * The gate's refusal, naming the institutions that hold the bulk button shut.
	 *
	 * Named and not only counted: "42 institutions" is a number a manager can do nothing
	 * with, and the first few names plus the link to the filtered pipeline is where the work
	 * actually starts.
	 *
	 * @param array $blocked Record IDs of the Confirmed institutions with no agreement recorded.
	 */
	private function render_provision_gate( array $blocked ) {
		if ( empty( $blocked ) ) {
			return;
		}

		$names = array();

		foreach ( array_slice( $blocked, 0, self::PROVISION_NAMES ) as $record_id ) {
			$names[] = self::institution_name( $record_id );
		}

		$rest = count( $blocked ) - count( $names );

		if ( $rest > 0 ) {
			$names[] = sprintf(
				/* translators: %s: how many more institutions there are. */
				_n( 'and %s more', 'and %s more', $rest, 'wpcredits-program-manager' ),
				number_format_i18n( $rest )
			);
		}

		printf(
			'<p class="wpcpm-warning">%1$s %2$s. <a href="%3$s">%4$s</a></p>',
			esc_html(
				sprintf(
					/* translators: %s: number of institutions. */
					_n(
						'No account is created in bulk while %s Confirmed institution has no agreement recorded:',
						'No account is created in bulk while %s Confirmed institutions have no agreement recorded:',
						count( $blocked ),
						'wpcredits-program-manager'
					),
					number_format_i18n( count( $blocked ) )
				)
			),
			esc_html( implode( ', ', $names ) ),
			esc_url( add_query_arg( 'wpcpm_filter', self::FILTER_GAP, $this->admin_url() ) ),
			esc_html__( 'Show them', 'wpcredits-program-manager' )
		);

		$this->render_on_file_all_form( count( $blocked ) );
	}

	/**
	 * The bulk on-file form: every Confirmed institution with nothing recorded, one link.
	 *
	 * Every institution at Confirmed signed an agreement before this site could generate or
	 * upload one, so recording them one at a time is a chore that stalls the bulk button for
	 * everybody. This is the on-file route applied to all of them at once, with the one link
	 * they share: the program's folder of signed agreements. Each still gets its own recorded
	 * agreement, its own audit row and its own Airtable cells, which is what makes it a
	 * recording and not a fiat.
	 *
	 * @param int $count How many institutions it would record.
	 */
	private function render_on_file_all_form( $count ) {
		echo '<form class="wpcpm-on-file-all" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( WPCPM_Institution_Agreement::ACTION_ON_FILE_ALL );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_ON_FILE_ALL ) );

		echo '<p class="description">' . esc_html__( 'Every Confirmed institution signed a Collaboration Agreement before this site could record one. Record them all as signed with the one link they share, the folder where the signed copies are kept. Each institution gets its own recorded agreement, its own line in the audit log and its own Airtable cells, and its account can then be created.', 'wpcredits-program-manager' ) . '</p>';

		printf(
			'<p><label for="wpcpm-on-file-all-drive">%1$s</label> <input type="url" class="regular-text" id="wpcpm-on-file-all-drive" name="wpcpm_agreement_drive" required placeholder="%2$s" /></p>',
			esc_html__( 'Drive link to the signed agreements', 'wpcredits-program-manager' ),
			esc_attr__( 'https://drive.google.com/...', 'wpcredits-program-manager' )
		);
		printf(
			'<p><label for="wpcpm-on-file-all-where">%1$s</label> <input type="text" class="regular-text" id="wpcpm-on-file-all-where" name="wpcpm_agreement_where" maxlength="%2$d" placeholder="%3$s" /></p>',
			esc_html__( 'Where the paper is, in a few words (optional)', 'wpcredits-program-manager' ),
			(int) WPCPM_Institution_Agreement::MAX_LOCATION,
			esc_attr__( 'Signed PDFs in the program Drive, one per institution', 'wpcredits-program-manager' )
		);
		printf(
			'<p><button type="submit" class="button button-secondary">%s</button></p>',
			esc_html(
				sprintf(
					/* translators: %s: number of institutions. */
					_n( 'Record %s institution as signed', 'Record all %s institutions as signed', (int) $count, 'wpcredits-program-manager' ),
					number_format_i18n( (int) $count )
				)
			)
		);
		echo '</form>';
	}

	/**
	 * The bulk button.
	 *
	 * The count is in the button and not only in the prose above it, as the invitations card
	 * has it: what makes a bulk action safe is that nobody can press it without having read
	 * how many people it reaches. It is drawn disabled rather than hidden while the gate is
	 * shut, so a manager can see what will be there once the agreements are recorded.
	 *
	 * @param int  $count How many accounts pressing it would create.
	 * @param bool $open  Whether the agreement gate lets it through.
	 */
	private function render_provision_button( $count, $open ) {
		$count   = (int) $count;
		$enabled = $open && $count > 0;
		$confirm = sprintf(
			/* translators: %s: how many accounts. */
			_n(
				'Create %s institution account and email a password-set link to the address Airtable holds for it? Invitations cannot be recalled once sent.',
				'Create %s institution accounts and email each one a password-set link to the address Airtable holds for it? Invitations cannot be recalled once sent.',
				$count,
				'wpcredits-program-manager'
			),
			number_format_i18n( $count )
		);

		printf(
			'<form method="post" action="%1$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$enabled ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : ''
		);
		wp_nonce_field( self::ACTION_PROVISION );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_PROVISION ) );
		submit_button(
			$count > 0
				? sprintf(
					/* translators: %s: how many accounts. */
					_n( 'Create %s account', 'Create %s accounts', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count )
				)
				: __( 'Create the accounts', 'wpcredits-program-manager' ),
			'primary',
			'submit',
			false,
			$enabled ? array() : array( 'disabled' => 'disabled' )
		);
		echo '</form>';

		if ( $count > self::PROVISION_LIMIT ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: how many accounts one press creates. */
						__( 'One press creates %s of them and the rest stay listed here; press it again to carry on.', 'wpcredits-program-manager' ),
						number_format_i18n( self::PROVISION_LIMIT )
					)
				)
			);
		}
	}

	/**
	 * One row per Confirmed institution that does not have an account yet.
	 *
	 * Ready first, then the ones the gate holds, then the rest: a worklist reads from the top.
	 * No address is printed, here or anywhere on this screen, so the contact column says
	 * whether there is one and not what it is.
	 *
	 * @param array $reasons Record ID to block reason, from `provision_reasons()`.
	 */
	private function render_provision_rows( array $reasons ) {
		$order = array(
			'',
			WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT,
			WPCPM_Institutions_Sync::BLOCK_CONFLICT,
			WPCPM_Institutions_Sync::BLOCK_NO_EMAIL,
			WPCPM_Institutions_Sync::BLOCK_FORMER_MEMBER,
			WPCPM_Institutions_Sync::BLOCK_NOT_INDEXED,
		);

		$listed = array();

		foreach ( $order as $reason ) {
			foreach ( array_keys( $reasons, $reason, true ) as $record_id ) {
				$listed[ $record_id ] = $reason;
			}
		}

		if ( empty( $listed ) ) {
			echo '<p>' . esc_html__( 'Every Confirmed institution has an account.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list wpcpm-inst-provision"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Contact', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Account', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $listed as $record_id => $reason ) {
			$row = WPCPM_Institutions_Index::row( $record_id );
			$row = is_array( $row ) ? $row : array();

			echo '<tr>';
			printf(
				'<td class="wpcpm-inst-name"><span class="wpcpm-inst-name__text">%1$s</span><br /><code class="wpcpm-inst-record">%2$s</code></td>',
				esc_html( self::institution_name( $record_id ) ),
				esc_html( $record_id )
			);

			printf(
				'<td>%s</td>',
				empty( $row['contact_email'] )
					? '<span class="wpcpm-warning">' . esc_html__( 'no email', 'wpcredits-program-manager' ) . '</span>'
					: esc_html__( 'email on record', 'wpcredits-program-manager' )
			);

			echo '<td class="wpcpm-inst-provision__action">';

			if ( '' === $reason ) {
				$this->render_provision_row_button( $record_id );
			} else {
				printf( '<span class="wpcpm-inst-muted">%s</span>', esc_html( WPCPM_Institutions_Sync::provision_message( $reason ) ) );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The per-row control: create this one institution's account.
	 *
	 * The nonce is keyed to the institution, so a nonce harvested from one row cannot be
	 * posted for another.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	private function render_provision_row_button( $record_id ) {
		$confirm = sprintf(
			/* translators: %s: institution name. */
			__( 'Create an account for %s and email a password-set link to the address Airtable holds for it? The invitation cannot be recalled once sent.', 'wpcredits-program-manager' ),
			self::institution_name( $record_id )
		);

		printf(
			'<form method="post" action="%1$s" onsubmit="return confirm(\'%2$s\');">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_js( $confirm )
		);
		wp_nonce_field( self::ACTION_PROVISION_ONE . '_' . $record_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_PROVISION_ONE ) );
		printf( '<input type="hidden" name="wpcpm_institution" value="%s" />', esc_attr( $record_id ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Create account', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Why each Confirmed institution may not be provisioned, keyed as the index keys them.
	 *
	 * Only the Confirmed rows are asked about, and they are asked exactly once for the card
	 * and for whichever handler is running: `provision_block()` costs an option read and, for
	 * a row that gets past the cheap facts, two membership queries, and asking the other
	 * hundred rows what their stage already says would put those queries on every render.
	 *
	 * @param array $rows Index rows.
	 * @return array<string, string> Record ID to a `BLOCK_` constant, or '' when it is ready.
	 */
	private static function provision_reasons( array $rows ) {
		$reasons = array();

		foreach ( $rows as $record_id => $row ) {
			if ( ! isset( $row['stage'] ) || 'Confirmed' !== trim( (string) $row['stage'] ) ) {
				continue;
			}

			$reasons[ $record_id ] = WPCPM_Institutions_Sync::provision_block( $record_id );
		}

		return $reasons;
	}

	/**
	 * The reconciliation between the Students table and Students Reports.
	 *
	 * The numbers come from the students sync's last run; the two live counts are the tracked
	 * student accounts carrying no institution stamp, which should be zero and is a broken
	 * sync when it is not, and the institutions whose Contact Email belongs to no member.
	 *
	 * @param array $counts From `WPCPM_Roster_Index::counts()`.
	 * @param array $index  The pipeline index, for the contact addresses' read time.
	 * @param array $gaps   The membership counts, from `membership_gaps()`.
	 */
	private function render_reconciliation( array $counts, array $index, array $gaps ) {
		$read = isset( $counts['read'] ) ? (int) $counts['read'] : 0;
		$rec  = isset( $counts['reconciliation'] ) && is_array( $counts['reconciliation'] ) ? $counts['reconciliation'] : array();

		$without_reports  = isset( $rec['students_without_reports'] ) ? (array) $rec['students_without_reports'] : array();
		$without_students = isset( $rec['reports_without_students'] ) ? (array) $rec['reports_without_students'] : array();
		$disagreements    = isset( $rec['status_disagreements'] ) ? (int) $rec['status_disagreements'] : 0;
		$duplicates       = isset( $rec['duplicate_emails'] ) ? (array) $rec['duplicate_emails'] : array();
		$no_institution   = isset( $rec['no_institution'] ) ? (int) $rec['no_institution'] : 0;
		$no_start         = isset( $rec['no_start_date'] ) ? (array) $rec['no_start_date'] : array();
		$unstamped        = self::unstamped_students();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Reconciliation', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The Students table and Students Reports describe the same people and are joined by email. These are the rows where the two disagree or one side is missing, from the students sync\'s last run.', 'wpcredits-program-manager' ) . '</p>';

		$this->read_line( $read, __( 'Roster counts', 'wpcredits-program-manager' ) );

		echo '<table class="wpcpm-table wpcpm-inst-recon"><tbody>';

		$this->recon_row(
			__( 'Students rows with no reports row', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $without_reports ) ),
			self::breakdown( $without_reports )
		);
		$this->recon_row(
			__( 'Reports rows with no Students row', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $without_students ) ),
			self::breakdown( $without_students )
		);
		$this->recon_row( __( 'Status disagreements on joined rows', 'wpcredits-program-manager' ), $disagreements, '' );

		$duplicate_parts = array();

		foreach ( $duplicates as $record_id => $count ) {
			$duplicate_parts[] = sprintf( '%1$s (%2$s)', self::institution_name( (string) $record_id ), number_format_i18n( (int) $count ) );
		}

		$this->recon_row(
			__( 'Duplicate emails in the Students table', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $duplicates ) ),
			implode( ', ', $duplicate_parts )
		);
		$this->recon_row( __( 'Students rows with no institution', 'wpcredits-program-manager' ), $no_institution, '' );
		$this->recon_row(
			__( 'Students rows with no start date', 'wpcredits-program-manager' ),
			array_sum( array_map( 'intval', $no_start ) ),
			self::breakdown( $no_start )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td%2$s>%3$s <span class="wpcpm-inst-muted">%4$s</span></td></tr>',
			esc_html__( 'Tracked student accounts with no institution stamp', 'wpcredits-program-manager' ),
			$unstamped > 0 ? ' class="wpcpm-warning"' : '',
			esc_html( number_format_i18n( $unstamped ) ),
			$unstamped > 0
				? esc_html__( '(counted now; should be 0, anything else is a broken sync)', 'wpcredits-program-manager' )
				: esc_html__( '(counted now)', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s <span class="wpcpm-inst-muted">%3$s</span></td></tr>',
			esc_html__( 'Contacts who are not members', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( isset( $gaps['contact_not_member'] ) ? (int) $gaps['contact_not_member'] : 0 ) ),
			esc_html( self::membership_read_line( isset( $index['read'] ) ? (int) $index['read'] : 0 ) )
		);

		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'A Contact Email that belongs to no member is the address Airtable names for the institution and nobody who can act for them here. Adding that person in one click ships with the accounts phase; until then the sync provisions the address only for an institution that has never had a member, so a removed contact is not re-created every night.', 'wpcredits-program-manager' ) . '</p>';

		$unlinked = WPCPM_Roster_Index::unlinked();

		// Outside the list and not inside it: a sync that finished between the redirect and
		// this render empties the list, and an outcome nothing draws is one that sits in user
		// meta until some later page surprises somebody with it.
		$this->render_link_outcome();

		if ( ! empty( $unlinked ) ) {
			$this->render_unlinked( $unlinked );
		}

		echo '</div>';
	}

	/**
	 * The Students rows that name no institution, each with its Link control or its reason.
	 *
	 * Three rows today. A program manager links one from here and no institution ever does:
	 * the import's whole verdict design exists so that a school cannot learn which addresses
	 * outside its own roster the base knows about, and a self-service adopt control would
	 * hand it exactly that, one address at a time.
	 *
	 * A row is offered a control only when `link_block()` says the write is safe, and the
	 * reason is printed beside the row when it is not. The handler asks the same question
	 * again against a live read and refuses on its own: a disabled control is a courtesy to
	 * the person and not a check, and this list is only as fresh as the last sync.
	 *
	 * @param array $rows Rows from `WPCPM_Roster_Index::unlinked()`.
	 */
	private function render_unlinked( array $rows ) {
		$choices  = self::link_choices();
		$offered  = 0;
		$standing = 0;

		printf(
			'<h3 id="%1$s">%2$s</h3>',
			esc_attr( self::ANCHOR_UNLINKED ),
			esc_html__( 'Rows with no institution', 'wpcredits-program-manager' )
		);

		echo '<ul class="wpcpm-notices wpcpm-inst-unlinked">';

		foreach ( $rows as $row ) {
			$row       = is_array( $row ) ? $row : array();
			$record_id = isset( $row['record_id'] ) ? trim( (string) $row['record_id'] ) : '';
			$status    = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';
			$name      = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

			printf(
				'<li>%1$s <span class="wpcpm-inst-muted">%2$s</span>',
				esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
				esc_html( '' !== $status ? $status : __( '(no status)', 'wpcredits-program-manager' ) )
			);

			// A stored option is not a guaranteed shape. `WPCPM_Roster_Index` drops a row whose
			// record ID is not well-formed on the way in, but that is a promise made on the
			// write side about the runs that have happened since it was made, and a row this
			// site cannot address in Airtable is a row no control here could ever act on.
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				++$standing;
				echo '</li>';
				continue;
			}

			$reason = self::link_block( $row );

			if ( '' === $reason ) {
				++$offered;
				$this->render_link_form( $record_id, $name, $choices );
			} else {
				printf( ' <span class="wpcpm-warning">%s</span>', esc_html( self::link_message( $reason ) ) );
			}

			echo '</li>';
		}

		echo '</ul>';

		if ( $offered > 0 ) {
			echo '<p class="description">' . esc_html__( 'Linking writes Educational Institutions onto the Students row in Airtable and nothing else. Two rows are refused rather than linked: one carrying a mentor at a status the automation watches, and one whose address already has a Students Reports row. Either write would create a second Students Reports row, which is the duplicate this card exists to stop making more of. A linked row stays on this list until the next sync rebuilds it.', 'wpcredits-program-manager' ) . '</p>';
		}

		// The standing instruction, for a row this control cannot act on at all. It says the
		// same thing the three refusals above say in their own words: the link is made in the
		// base, by hand, and not from here.
		if ( $standing > 0 ) {
			echo '<p class="description">' . esc_html__( 'A row above carries no usable Airtable record ID, so nothing here can address it. Set Educational Institutions on that row in Airtable.', 'wpcredits-program-manager' ) . '</p>';
		}
	}

	/**
	 * The per-row control: link this Students row to one institution.
	 *
	 * The nonce is keyed to the Students row. Every one of these controls is on one page, and
	 * a nonce that covered the action rather than the row would let a token taken from a row
	 * the site is willing to link be posted for the row beside it, which is a row it refuses.
	 *
	 * The institution is a `<select>` and never a free-text record ID: the base holds
	 * near-collisions and ten names ending in a space, and a mistyped record ID is a link to
	 * nothing that no later screen would show as wrong.
	 *
	 * @param string $record_id Students record ID.
	 * @param string $name      The student's name, for the confirm.
	 * @param array  $choices   Institution record ID to name, from `link_choices()`.
	 */
	private function render_link_form( $record_id, $name, array $choices ) {
		$field   = 'wpcpm-link-' . $record_id;
		$confirm = sprintf(
			/* translators: %s: the student's name as the Students table holds it. */
			__( 'Link %s to the institution chosen here? This writes Educational Institutions onto the Students row in Airtable. The row is read again first and the write is refused if it would create a second Students Reports row.', 'wpcredits-program-manager' ),
			'' !== $name ? $name : $record_id
		);

		printf(
			' <form class="wpcpm-inst-link" method="post" action="%1$s" onsubmit="return confirm(\'%2$s\');">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_js( $confirm )
		);
		wp_nonce_field( self::ACTION_LINK . '_' . $record_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_LINK ) );
		printf( '<input type="hidden" name="wpcpm_student" value="%s" />', esc_attr( $record_id ) );
		printf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>',
			esc_attr( $field ),
			esc_html__( 'Institution to link this row to', 'wpcredits-program-manager' )
		);
		printf( '<select id="%s" name="wpcpm_institution" required>', esc_attr( $field ) );
		printf( '<option value="">%s</option>', esc_html__( 'Choose an institution', 'wpcredits-program-manager' ) );

		foreach ( $choices as $choice => $label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $choice ), esc_html( $label ) );
		}

		echo '</select>';
		printf( ' <button type="submit" class="button">%s</button>', esc_html__( 'Link', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * How the last press of Link ended, drawn inside the card that made it.
	 *
	 * Taken and cleared here rather than at the top of the screen with the other outcomes,
	 * because it is a sentence about one row in a list eight cards down: a manager who has to
	 * scroll back up to read what happened cannot see the row it happened to.
	 */
	private function render_link_outcome() {
		$flash  = WPCPM_Flash::take( self::FLASH_LINK );
		$status = ( is_array( $flash ) && isset( $flash['status'] ) ) ? (string) $flash['status'] : '';
		$detail = ( is_array( $flash ) && isset( $flash['detail'] ) ) ? (string) $flash['detail'] : '';

		if ( '' === $status ) {
			return;
		}

		if ( self::LINK_DONE === $status ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					'' !== $detail
						? sprintf(
							/* translators: %s: institution name. */
							__( 'The row is linked to %s. It is on that institution\'s roster now, and it stays on this list until the next sync rebuilds it.', 'wpcredits-program-manager' ),
							$detail
						)
						: self::link_message( $status )
				)
			);

			return;
		}

		// "Nothing was written" first, in every refusal. What a manager needs to know before
		// the reason is whether the base changed, because the answer decides whether the next
		// thing they do is fix the row or nothing at all.
		printf(
			'<div class="notice notice-error is-dismissible"><p>%1$s %2$s%3$s</p></div>',
			esc_html__( 'Nothing was written.', 'wpcredits-program-manager' ),
			esc_html( self::link_message( $status ) ),
			'' !== $detail ? ' <code>' . esc_html( $detail ) . '</code>' : ''
		);
	}

	/**
	 * The sentence for one `LINK_` outcome.
	 *
	 * One map for the card's row notes and the handler's flashes both, so a row that says why
	 * it is not offered a control and the refusal that follows pressing Link anyway say it in
	 * the same words.
	 *
	 * @param string $status A `LINK_` constant.
	 * @return string The sentence, or '' for a status this map does not know.
	 */
	private static function link_message( $status ) {
		$messages = array(
			self::LINK_DONE           => __( 'The row is linked. It is on that institution\'s roster now, and it stays on this list until the next sync rebuilds it.', 'wpcredits-program-manager' ),
			self::LINK_AUTOMATION     => __( 'This row carries a mentor and a status the Airtable automation watches, so writing an institution onto it now would create a second Students Reports row. Deal with the mentor or the status in the base first.', 'wpcredits-program-manager' ),
			self::LINK_REPORTS        => __( 'A Students Reports row already exists for this row\'s address, so writing an institution onto it now would leave the program holding two records for one student.', 'wpcredits-program-manager' ),
			self::LINK_NO_EMAIL       => __( 'This row carries no address, and the address is the only join between the two tables, so the site cannot tell whether a Students Reports row already exists for it.', 'wpcredits-program-manager' ),
			self::LINK_ALREADY        => __( 'This row already names an institution. This list is as old as the last sync; run one to rebuild it.', 'wpcredits-program-manager' ),
			self::LINK_BAD_RECORD     => __( 'That control did not name a Students row and an institution this site can act on.', 'wpcredits-program-manager' ),
			self::LINK_NO_INSTITUTION => __( 'That institution is not in the pipeline index. Run a sync and try again.', 'wpcredits-program-manager' ),
			self::LINK_READ_FAILED    => __( 'Airtable would not answer, so the checks that keep this control from creating a second Students Reports row could not be run.', 'wpcredits-program-manager' ),
			self::LINK_WRITE_FAILED   => __( 'Airtable refused the change, so the row is exactly where it was.', 'wpcredits-program-manager' ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : '';
	}

	/**
	 * Every institution the site has read, as record ID to the name to print.
	 *
	 * The whole index and not only the Confirmed rows: a Students row with no institution is
	 * most often older than the partnership it belongs to, and a picker that hid everything
	 * short of Confirmed would refuse the exact case this control is for.
	 *
	 * Names are trimmed by `institution_name()`, which also answers a nameless record with its
	 * record ID: ten names in the base end in a space and two Confirmed records have no name
	 * at all, and neither is a row a manager should have to pick blind.
	 *
	 * @return array<string, string>
	 */
	private static function link_choices() {
		$choices = array();

		foreach ( array_keys( WPCPM_Institutions_Index::rows() ) as $record_id ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$choices[ trim( (string) $record_id ) ] = self::institution_name( $record_id );
		}

		asort( $choices, SORT_NATURAL | SORT_FLAG_CASE );

		return $choices;
	}

	/**
	 * One row of the reconciliation table.
	 *
	 * @param string $label     What is counted.
	 * @param int    $count     The count.
	 * @param string $breakdown The split by status, or an empty string.
	 */
	private function recon_row( $label, $count, $breakdown ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s%3$s</td></tr>',
			esc_html( $label ),
			esc_html( number_format_i18n( (int) $count ) ),
			'' !== $breakdown ? ' <span class="wpcpm-inst-muted">(' . esc_html( $breakdown ) . ')</span>' : ''
		);
	}

	/**
	 * The consent report.
	 *
	 * The brief read 79 records with the required answer and 16 with the tick as consent
	 * dropped between form and record. It is a date boundary: the checkbox was added to the
	 * form on 20 July 2026. So the sentence here is the one the design spec fixes, computed
	 * from the index, and it never says anything went missing, because nothing was asked.
	 *
	 * @param array $index The pipeline index.
	 */
	private function render_consent( array $index ) {
		$rows   = isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array();
		$read   = isset( $index['read'] ) ? (int) $index['read'] : 0;
		$counts = self::consent_counts( $rows );

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</h2>';

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		printf(
			'<p class="wpcpm-inst-consent">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: number of records, 2: number of those at the Confirmed stage. */
					__( '%1$s institution records were collected before the consent question was added on 20 July 2026, %2$s of them at Confirmed.', 'wpcredits-program-manager' ),
					number_format_i18n( $counts['before'] ),
					number_format_i18n( $counts['before_confirmed'] )
				)
			)
		);

		echo '<p class="description">' . esc_html__( 'None of them carries the Privacy Policy Compliance tick because the form did not ask. For the Confirmed ones the signed Collaboration Agreement is the basis, recorded per institution in the pipeline\'s agreement column.', 'wpcredits-program-manager' ) . '</p>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of records. */
					_n(
						'Since then, %s record has been created without the tick, which is how a record entered by hand in the grid looks.',
						'Since then, %s records have been created without the tick, which is how records entered by hand in the grid look.',
						$counts['since_without'],
						'wpcredits-program-manager'
					),
					number_format_i18n( $counts['since_without'] )
				)
			)
		);

		echo '</div>';
	}

	/**
	 * Institutions whose site-side agreement state and Airtable's disagree.
	 *
	 * Each one is locked until the two agree, which is the point: a manager typing Revoked
	 * into the grid must lock, and a site-side revoke must survive the next rebuild.
	 *
	 * @param array $index The pipeline index, for the names.
	 */
	private function render_discrepancies( array $index ) {
		$discrepancies = WPCPM_Institution_Agreement::discrepancies();
		$read          = isset( $index['read'] ) ? (int) $index['read'] : 0;

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Agreement discrepancies', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $discrepancies ) ) )
		);
		echo '<p class="description">' . esc_html__( 'An institution is settled only when the site\'s record and Airtable\'s Agreement Status agree. These are the ones where they do not; each is locked until they do.', 'wpcredits-program-manager' ) . '</p>';

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $discrepancies ) ) {
			echo '<p>' . esc_html__( 'The site and Airtable agree on every agreement.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'On the site', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'In Airtable', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $discrepancies as $record_id => $sides ) {
			$site     = isset( $sides['site_state'] ) ? (string) $sides['site_state'] : '';
			$airtable = isset( $sides['airtable_status'] ) ? (string) $sides['airtable_status'] : '';

			printf(
				'<tr><td>%1$s<br /><code>%2$s</code></td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( self::institution_name( (string) $record_id ) ),
				esc_html( (string) $record_id ),
				esc_html( '' !== $site ? $site : __( '(nothing recorded)', 'wpcredits-program-manager' ) ),
				esc_html( '' !== $airtable ? $airtable : __( '(empty)', 'wpcredits-program-manager' ) )
			);
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Every semester report on the site: whose it is, which semester, and how far along.
	 *
	 * A manager's one list of what the institutions are writing. The reports themselves are
	 * edited on the dashboard and nowhere else, so each row opens there through the switcher
	 * rather than in wp-admin: there is no wp-admin screen for the post type, on purpose, and a
	 * second place to edit a document several people share is a second place for them to
	 * overwrite each other.
	 *
	 * Both dates are shown because they answer different questions. *Generated* is how old the
	 * numbers are; *last edited* is whether anybody has written anything since. A report
	 * generated in July and never edited is one nobody has picked up.
	 */
	private function render_semester_reports() {
		// `private` by name rather than `any`: it is the one status a report is ever written
		// with, and `any` would also bring back a report somebody had trashed by hand, which
		// is a document that has been withdrawn rather than one to list.
		$posts = get_posts(
			array(
				'post_type'        => WPCPM_Semester_Report::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => self::REPORTS_SHOWN,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$total = 0;

		// Counted rather than taken from the rows above, which are capped: a heading reading 60
		// beside a list of 60 is a heading that stops being true the day the sixty-first report
		// is written, and says nothing about it. Asked for only when there is something to
		// count, so a site whose institutions have not started writing runs one query and not two.
		if ( ! empty( $posts ) ) {
			$tally = wp_count_posts( WPCPM_Semester_Report::POST_TYPE );
			$total = isset( $tally->private ) ? (int) $tally->private : count( $posts );
		}

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Semester reports', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $total ) )
		);
		echo '<p class="description">' . esc_html__( 'What each institution has written about a semester, and where it has got to. Reports are edited on the institution dashboard, so each one opens there as that institution.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $posts ) ) {
			echo '<p>' . esc_html__( 'No institution has generated a report yet.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		if ( $total > count( $posts ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: how many reports are listed. */
						__( 'The %s most recently edited are listed.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $posts ) )
					)
				)
			);
		}

		// Design spec section 14, open question 2: the consent request is a program manager's
		// to send and nobody else's, so this is the only screen it is offered on. The
		// institution's own report says how many of its students have not answered and that a
		// manager can write to them; it never offers the send, because the party that gains
		// from a yes must not be the party that asks for it.
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: a number of messages. */
					__( 'Asking writes one short message to each student in that semester who wrote feedback and has not said whether it may be used. Nobody is asked twice in thirty days, and at most %s go out at a time; the rest follow shortly.', 'wpcredits-program-manager' ),
					number_format_i18n( WPCPM_Semester_Report_Screen::ASK_PER_RUN )
				)
			)
		);

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Institution', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Semester', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'State', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Generated', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Last edited', 'wpcredits-program-manager' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Consent', 'wpcredits-program-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $posts as $post ) {
			$this->render_semester_report_row( $post );
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * One row of the semester reports card.
	 *
	 * @param WP_Post $post The report.
	 */
	private function render_semester_report_row( WP_Post $post ) {
		$record = WPCPM_Semester_Report::institution_of( $post );
		$cohort = WPCPM_Semester_Report::cohort_of( $post );
		$name   = '' !== $record ? self::institution_name( $record ) : '';
		$name   = '' !== $name ? $name : __( '(institution not in the index)', 'wpcredits-program-manager' );

		// The switcher argument, because `resolve_institution()` is what places a manager on
		// somebody else's dashboard and it reads that one argument and nothing else. Without it
		// the link lands the manager on whichever institution is their fallback, which is a
		// different school's report under this row's name.
		$url = WPCPM_Semester_Report_Screen::report_url( $cohort );
		$url = ( '' !== $url && '' !== $record ) ? add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url ) : '';

		$label     = '' !== $cohort ? WPCPM_Cohort::label( $cohort ) : __( '(no semester recorded)', 'wpcredits-program-manager' );
		$generated = WPCPM_Semester_Report::generated_at( $post );
		$edited    = (int) get_post_modified_time( 'U', true, $post );
		$final     = WPCPM_Semester_Report::STATE_FINAL === WPCPM_Semester_Report::state( $post );
		$format    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		printf(
			'<tr><td>%1$s<br /><code>%2$s</code></td><td>%3$s%4$s</td><td>%5$s</td><td>%6$s</td><td>%7$s</td><td>',
			'' !== $url
				? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $name ) )
				: esc_html( $name ),
			esc_html( '' !== $record ? $record : '-' ),
			esc_html( $label ),
			'' !== $cohort ? sprintf( ' <code>%s</code>', esc_html( $cohort ) ) : '',
			esc_html(
				$final
					? __( 'Final', 'wpcredits-program-manager' )
					: __( 'Draft', 'wpcredits-program-manager' )
			),
			esc_html( $generated ? wp_date( $format, $generated ) : __( 'not yet', 'wpcredits-program-manager' ) ),
			esc_html( $edited ? wp_date( $format, $edited ) : __( 'never', 'wpcredits-program-manager' ) )
		);

		// Offered whatever state the report is in, including `final`, and deliberately: this
		// asks students a question, it changes no word of the document, and a school that has
		// marked a report final is exactly the school whose remaining students are worth
		// asking before the next one. The answers reach the document through
		// `ACTION_REFRESH_CONSENT`, which is allowed on a final report for the same reason.
		WPCPM_Semester_Report_Screen::render_ask_form( $post->ID );

		echo '</td></tr>';
	}

	/**
	 * The plugin's copy of the Collaboration Agreement: version, read date, source, checksum.
	 *
	 * No drift button in this phase. The Doc is world-editable, so a drift check is a button a
	 * manager presses on purpose and reads with care, never a schedule; it ships with the
	 * generate path.
	 *
	 * @param array $index The pipeline index, for the institutions listed per signed version.
	 */
	private function render_template( array $index ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Agreement template', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The program\'s Google Doc is the master for what the agreement says; the plugin\'s copy is the master for what the site generates. The version is the Doc\'s modified date when the copy was taken.', 'wpcredits-program-manager' ) . '</p>';

		$languages = WPCPM_Agreement_Template::languages();
		$generated = array();

		if ( empty( $languages ) ) {
			echo '<p class="wpcpm-warning">' . esc_html__( 'No agreement template file was found.', 'wpcredits-program-manager' ) . '</p>';
		}

		foreach ( $languages as $language ) {
			$template = WPCPM_Agreement_Template::load( $language );

			if ( is_wp_error( $template ) ) {
				printf(
					'<p class="wpcpm-warning"><code>%1$s</code>: %2$s</p>',
					esc_html( $language ),
					esc_html( $template->get_error_message() )
				);

				continue;
			}

			$generated[] = sprintf(
				/* translators: 1: template version, 2: language code. */
				__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
				WPCPM_Agreement_Template::version( $template ),
				$language
			);

			echo '<table class="wpcpm-table"><tbody>';
			printf( '<tr><th scope="row">%1$s</th><td><code>%2$s</code></td></tr>', esc_html__( 'Language', 'wpcredits-program-manager' ), esc_html( $language ) );
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Version', 'wpcredits-program-manager' ), esc_html( WPCPM_Agreement_Template::version( $template ) ) );
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Copied from the Doc on', 'wpcredits-program-manager' ), esc_html( isset( $template['read'] ) ? (string) $template['read'] : '' ) );
			// The template names where it came from in words; the address itself is a setting,
			// because the document is editable by anyone holding its link and this plugin's source
			// is public. A site that has been given the link shows it, and one that has not says
			// so rather than rendering an empty anchor.
			$doc = (string) WPCPM_Settings::get_value( 'agreement_doc_url', '' );

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s%3$s</td></tr>',
				esc_html__( 'Source', 'wpcredits-program-manager' ),
				esc_html( isset( $template['source'] ) ? (string) $template['source'] : '' ),
				'' !== $doc
					? sprintf(
						' <a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
						esc_url( $doc ),
						esc_html__( 'Open it', 'wpcredits-program-manager' )
					)
					: sprintf(
						' <span class="wpcpm-inst-muted">%s</span>',
						esc_html__( '(its address is a setting, not carried in the code)', 'wpcredits-program-manager' )
					)
			);
			printf(
				'<tr><th scope="row">%1$s</th><td><code>%2$s</code> <span class="wpcpm-inst-muted">%3$s</span></td></tr>',
				esc_html__( 'Checksum', 'wpcredits-program-manager' ),
				esc_html( substr( WPCPM_Agreement_Template::checksum( $template ), 0, 12 ) ),
				esc_html__( '(the first twelve characters of the sha256 the fixture pins)', 'wpcredits-program-manager' )
			);
			echo '</tbody></table>';
		}

		$this->render_template_versions(
			isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : array(),
			isset( $index['read'] ) ? (int) $index['read'] : 0,
			$generated
		);

		echo '</div>';
	}

	/**
	 * The institutions behind each Agreement Template Version, newest version first.
	 *
	 * Step four of keeping the plugin's copy in step with the Doc: after a wording change,
	 * the institutions that signed the old one are a list a manager reads and decides about.
	 * The module reports the split and never asks anybody to re-sign, because whether a
	 * changed sentence is worth a second signature is a program decision, not a plugin's.
	 *
	 * Only rows whose agreement block says something are grouped. A record at First Contact
	 * Made carries an empty block, and counting it beside the bespoke and legacy copies would
	 * claim the program holds an agreement it has never asked for. Among the ones it does
	 * hold, an empty version is a bespoke or a legacy agreement: signed paper that did not
	 * come from this template, and named as such rather than as "unknown".
	 *
	 * @param array $rows      Index rows.
	 * @param int   $read      Unix time the pipeline index was read.
	 * @param array $generated The version the plugin generates today, one entry per language.
	 */
	private function render_template_versions( array $rows, $read, array $generated ) {
		$by_version = array();

		foreach ( $rows as $row ) {
			$agreement = isset( $row['agreement'] ) && is_array( $row['agreement'] ) ? $row['agreement'] : array();

			if ( ! self::has_agreement( $agreement ) ) {
				continue;
			}

			$version = isset( $agreement['template_version'] ) ? trim( (string) $agreement['template_version'] ) : '';

			$by_version[ $version ][] = self::institution_name( isset( $row['record_id'] ) ? (string) $row['record_id'] : '' );
		}

		echo '<h3>' . esc_html__( 'Institutions per template version', 'wpcredits-program-manager' ) . '</h3>';

		if ( ! empty( $generated ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: version and language, e.g. "2025-11-04 (en)". */
						__( 'The site generates %s today, so anything listed below it was signed against wording the program has changed since.', 'wpcredits-program-manager' ),
						implode( ', ', $generated )
					)
				)
			);
		}

		$this->read_line( $read, __( 'Pipeline index', 'wpcredits-program-manager' ) );

		if ( empty( $by_version ) ) {
			echo '<p>' . esc_html__( 'No institution has an agreement recorded, so no template version has been signed yet.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		// Descending as strings: a version is the Doc's modified date, so the newest sorts
		// first, and the empty key lands last on its own without a rule of its own.
		krsort( $by_version, SORT_STRING );

		echo '<table class="wpcpm-table wpcpm-inst-versions"><tbody>';

		foreach ( $by_version as $version => $names ) {
			$version = trim( (string) $version );

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s <span class="wpcpm-inst-muted">(%3$s)</span></td></tr>',
				'' !== $version
					? esc_html( $version )
					: esc_html__( 'No version recorded (the bespoke and legacy agreements)', 'wpcredits-program-manager' ),
				esc_html( number_format_i18n( count( $names ) ) ),
				esc_html( implode( ', ', $names ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The storage card: what the host does with a direct request to the private directory.
	 */
	private function render_storage() {
		$result = WPCPM_Private_Files::probe_result();
		$path   = WPCPM_Private_Files::url_path();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Storage', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Signed agreements are stored under the uploads directory, in a folder whose name begins with a dot, and every file is encrypted before it is written. They are handed out only by the plugin, which checks who is asking. The probe writes a throwaway file, requests it the way a stranger would, and records the answer.', 'wpcredits-program-manager' ) . '</p>';

		// The encryption is the control that does not depend on the host, so it is stated first
		// and stated as a fact about this site rather than as a promise about the directory.
		if ( WPCPM_Private_Files::can_encrypt() ) {
			printf(
				'<p class="wpcpm-inst-status wpcpm-inst-status--ok">%s</p>',
				esc_html__( 'Stored files are encrypted with AES-256-GCM. The key is held on this site and never leaves it, so a copy of the file taken from disk is unreadable without it.', 'wpcredits-program-manager' )
			);
		} else {
			printf(
				'<p class="wpcpm-warning wpcpm-inst-status wpcpm-inst-status--warn">%s</p>',
				esc_html__( 'This site cannot encrypt stored files: PHP here has no OpenSSL support for AES-256-GCM. Uploads are refused rather than stored in the clear. Ask the host to enable it.', 'wpcredits-program-manager' )
			);
		}

		if ( null === $result ) {
			echo '<p>' . esc_html__( 'The probe has not run yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			$verdict = WPCPM_Private_Files::verdict( $result );
			$when    = $result['time'] ? wp_date( 'Y-m-d H:i', $result['time'] ) : '';
			$control = isset( $result['control_status'] ) ? (int) $result['control_status'] : 0;

			if ( 'blocked' === $verdict ) {
				printf(
					'<p class="wpcpm-inst-status wpcpm-inst-status--ok">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: HTTP status code, 2: date and time. */
							__( 'The host refuses direct requests to the private directory (HTTP %1$d on %2$s).', 'wpcredits-program-manager' ),
							$result['status'],
							$when
						)
					)
				);

				// Without the control the refusal could be the host blocking all of uploads, and
				// the card would be crediting the directory name for something else entirely.
				if ( $control >= 200 && $control < 300 ) {
					printf(
						'<p class="description">%s</p>',
						esc_html(
							sprintf(
								/* translators: %d: HTTP status code. */
								__( 'The same file in a folder without the leading dot answers HTTP %d on this host, so the dot is what makes the difference. It is a host behaviour rather than a promise, which is why the files are encrypted as well.', 'wpcredits-program-manager' ),
								$control
							)
						)
					);
				}
			} elseif ( 'served' === $verdict ) {
				printf(
					'<p class="wpcpm-warning wpcpm-inst-status wpcpm-inst-status--warn">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: HTTP status code, 2: date and time, 3: the directory path. */
							__( 'The host hands out files in the private directory to anyone who asks (HTTP %1$d on %2$s). What it hands over is encrypted, and the names are unguessable, so nothing readable is exposed. Even so, %3$s should not be reachable: tell the host, and do not store anything here that is not encrypted by this plugin.', 'wpcredits-program-manager' ),
							$result['status'],
							$when,
							$path
						)
					)
				);
			} else {
				printf(
					'<p class="wpcpm-inst-status wpcpm-inst-status--unknown">%s</p>',
					esc_html(
						'' !== $result['error']
							? sprintf(
								/* translators: 1: date and time, 2: error message. */
								__( 'The probe could not tell what the host does (on %1$s): %2$s', 'wpcredits-program-manager' ),
								$when,
								$result['error']
							)
							: sprintf(
								/* translators: 1: HTTP status code, 2: date and time. */
								__( 'The probe could not tell what the host does: it answered HTTP %1$d on %2$s, which is neither a refusal nor the file.', 'wpcredits-program-manager' ),
								$result['status'],
								$when
							)
					)
				);
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_PROBE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PROBE ) . '" />';
		submit_button( __( 'Run probe', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '</div>';
	}

	/*
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * Print when a set of numbers was read, so a stale count never looks fresh.
	 *
	 * @param int    $read Unix time the source was read, or 0 for never.
	 * @param string $what What was read, e.g. "Pipeline index".
	 */
	private function read_line( $read, $what ) {
		if ( ! $read ) {
			printf(
				'<p class="wpcpm-inst-read">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: what was read, e.g. "Pipeline index". */
						__( '%s: not read yet.', 'wpcredits-program-manager' ),
						$what
					)
				)
			);

			return;
		}

		printf(
			'<p class="wpcpm-inst-read">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: what was read, 2: date and time, 3: human-readable time difference. */
					__( '%1$s: read %2$s (%3$s ago).', 'wpcredits-program-manager' ),
					$what,
					wp_date( 'Y-m-d H:i', $read ),
					human_time_diff( $read, time() )
				)
			)
		);
	}

	/**
	 * A stage's name as printed, with the empty stage named.
	 *
	 * @param string $stage The stage as stored.
	 * @return string
	 */
	private static function stage_label( $stage ) {
		$stage = trim( (string) $stage );

		return '' !== $stage ? $stage : __( 'No stage', 'wpcredits-program-manager' );
	}

	/**
	 * An institution's name from the index, trimmed, or its record ID when the index has none.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record_id ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		if ( is_array( $row ) && isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ) {
			return trim( (string) $row['name'] );
		}

		return $record_id;
	}

	/**
	 * Whether a summary is one of the two states that settle an institution.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return bool
	 */
	private static function is_settled_summary( array $summary ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : '';

		return in_array( $state, array( WPCPM_Institution_Agreement::SUMMARY_ACCEPTED, WPCPM_Institution_Agreement::SUMMARY_ON_FILE ), true );
	}

	/**
	 * The Confirmed rows whose agreement is not recorded, keyed as the index keys them.
	 *
	 * The filter link's count and the filtered view read the same list, so they cannot
	 * disagree. Forty-two rows on day one, every real Confirmed institution being legacy.
	 *
	 * @param array $rows      Index rows.
	 * @param array $summaries Summaries keyed by record ID.
	 * @return array
	 */
	private static function agreement_gap( array $rows, array $summaries ) {
		$gap = array();

		foreach ( $rows as $record_id => $row ) {
			if ( ! isset( $row['stage'] ) || 'Confirmed' !== trim( (string) $row['stage'] ) ) {
				continue;
			}

			$summary = isset( $summaries[ $record_id ] ) && is_array( $summaries[ $record_id ] ) ? $summaries[ $record_id ] : array();

			if ( ! self::is_settled_summary( $summary ) ) {
				$gap[ $record_id ] = $row;
			}
		}

		return $gap;
	}

	/**
	 * Whether an index row's agreement block describes an agreement at all.
	 *
	 * Any one of the eight columns being filled is the base saying there is one; all of them
	 * empty is a record nobody has asked for an agreement from yet.
	 *
	 * @param array $agreement An index row's `agreement` block.
	 * @return bool
	 */
	private static function has_agreement( array $agreement ) {
		foreach ( $agreement as $value ) {
			if ( is_string( $value ) ? '' !== trim( $value ) : ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The two membership counts the manager backstop is measured by.
	 *
	 * One pass and one `members_of()` call per institution, because both counts need the
	 * same answer and that call is a query each. `members_of()` returns the accounts whose
	 * stamp names this institution and whose flag is live, so an institution missing from it
	 * has nobody at all: the count the design says pages a manager.
	 *
	 * A contact who is not a member is Airtable naming an address for the institution that
	 * belongs to no account acting for it. Both sides are lowercased and trimmed before they
	 * are compared: the index row's address already is, an account's is whatever the person
	 * typed when they registered, and a case difference is not a different person.
	 *
	 * @param array $rows Index rows.
	 * @return array{no_member: int, contact_not_member: int}
	 */
	private static function membership_gaps( array $rows ) {
		$gaps = array(
			'no_member'          => 0,
			'contact_not_member' => 0,
		);

		foreach ( $rows as $row ) {
			$members = WPCPM_Institution_Members::members_of( isset( $row['record_id'] ) ? (string) $row['record_id'] : '' );

			if ( empty( $members ) ) {
				++$gaps['no_member'];
			}

			$contact = isset( $row['contact_email'] ) ? strtolower( trim( (string) $row['contact_email'] ) ) : '';

			if ( '' === $contact ) {
				continue;
			}

			$is_member = false;

			foreach ( $members as $member ) {
				if ( $member instanceof WP_User && strtolower( trim( (string) $member->user_email ) ) === $contact ) {
					$is_member = true;
					break;
				}
			}

			if ( ! $is_member ) {
				++$gaps['contact_not_member'];
			}
		}

		return $gaps;
	}

	/**
	 * Where a count that joins the index to the live stamps came from.
	 *
	 * Two sources in one number: the institutions and the addresses Airtable names for them
	 * are as the last sync read them, the memberships are as they are right now. Saying only
	 * one of the two would let the stale half look as fresh as the live one.
	 *
	 * @param int $read Unix time the pipeline index was read, or 0 for never.
	 * @return string
	 */
	private static function membership_read_line( $read ) {
		if ( ! $read ) {
			return __( '(the pipeline index has not been read yet; memberships counted now)', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: date and time the pipeline index was read. */
			__( '(from the pipeline index read %s; memberships counted now)', 'wpcredits-program-manager' ),
			wp_date( 'Y-m-d H:i', $read )
		);
	}

	/**
	 * The consent report's numbers, from the index.
	 *
	 * Dates compare as strings: both sides are `Y-m-d`, and a timestamp comparison would let
	 * the site's timezone move a record created near midnight across the boundary.
	 *
	 * @param array $rows Index rows.
	 * @return array{before: int, before_confirmed: int, since_without: int}
	 */
	private static function consent_counts( array $rows ) {
		$counts = array(
			'before'           => 0,
			'before_confirmed' => 0,
			'since_without'    => 0,
		);

		foreach ( $rows as $row ) {
			$created = isset( $row['created'] ) ? (string) $row['created'] : '';
			$consent = ! empty( $row['consent'] );

			if ( $consent || '' === $created ) {
				continue;
			}

			if ( strcmp( $created, self::CONSENT_QUESTION_ADDED ) < 0 ) {
				++$counts['before'];

				if ( isset( $row['stage'] ) && 'Confirmed' === trim( (string) $row['stage'] ) ) {
					++$counts['before_confirmed'];
				}
			} else {
				++$counts['since_without'];
			}
		}

		return $counts;
	}

	/**
	 * A per-status split as one line, e.g. "Not moving forward 15, (empty) 7, Graduate 6".
	 *
	 * @param array $by_status Status => count.
	 * @return string
	 */
	private static function breakdown( array $by_status ) {
		$parts = array();

		arsort( $by_status, SORT_NUMERIC );

		foreach ( $by_status as $status => $count ) {
			$status  = trim( (string) $status );
			$parts[] = sprintf(
				'%1$s %2$s',
				'' !== $status ? $status : __( '(empty)', 'wpcredits-program-manager' ),
				number_format_i18n( (int) $count )
			);
		}

		return implode( ', ', $parts );
	}

	/**
	 * How many accounts the program tracks carry no institution stamp.
	 *
	 * `NOT EXISTS` and not `= ''`: the stamp is deleted rather than written empty when a
	 * student has no institution, precisely so this query has one meaning.
	 *
	 * Tracked is the whole point of the number. Holding the Student role is not enough:
	 * `revoke_departed()` deletes the stamp of a student the last run did not see at all,
	 * sets the active flag to 0 and leaves the role alone whenever `student_on_inactive` is
	 * `keep`, so counting by role would report every departed student as a broken sync
	 * forever. The active flag is the sync's own word for "this account is in the synced set
	 * right now", so that is what is counted.
	 *
	 * **A missing stamp is not by itself a fault.** The sync deletes the stamp whenever it
	 * cannot name one institution: the Students row exists and names none, or the address is
	 * filed under two schools and nobody can say which. Both of those are already counted on
	 * this card, as rows with no institution and as duplicate emails, and reporting them a
	 * second time as a broken sync would send a manager looking for a fault in the code that
	 * is not there. What every one of those outcomes has in common is that the sync wrote
	 * `institution_source` on the program meta: that key is its word for "I looked at this
	 * account and this is what I found". So the number here counts the accounts where the key
	 * is absent altogether - tracked, live, and never described by any run - which is the only
	 * state that means the sync itself did not do its job.
	 *
	 * @return int
	 */
	private static function unstamped_students() {
		$query = new WP_User_Query(
			array(
				'role'        => WPCPM_Roles::ROLE_STUDENT,
				'number'      => -1,
				'count_total' => false,
				'fields'      => 'ID',
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'     => WPCPM_Students_Sync::META_INSTITUTION,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => WPCPM_Students_Sync::META_ACTIVE,
						'value' => '1',
					),
				),
			)
		);

		$unstamped = 0;

		foreach ( (array) $query->get_results() as $user_id ) {
			$program = get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_PROGRAM, true );

			if ( is_array( $program ) && array_key_exists( 'institution_source', $program ) ) {
				continue;
			}

			++$unstamped;
		}

		return $unstamped;
	}

	/**
	 * An agreement summary in words: state, kind, acceptance date, and which route recorded it.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return string
	 */
	private static function describe_summary( array $summary ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Institution_Agreement::SUMMARY_NONE;
		$kind  = isset( $summary['kind'] ) ? (string) $summary['kind'] : '';
		$route = isset( $summary['route'] ) ? (string) $summary['route'] : '';

		$states = array(
			WPCPM_Institution_Agreement::SUMMARY_NONE      => __( 'Not started', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_GENERATED => __( 'Template generated', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_SUBMITTED => __( 'Awaiting review', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_RETURNED  => __( 'Returned', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_REVOKED   => __( 'Revoked', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_ACCEPTED  => __( 'Accepted', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::SUMMARY_ON_FILE   => __( 'On file', 'wpcredits-program-manager' ),
		);

		$kinds = array(
			WPCPM_Institution_Agreement::KIND_TEMPLATE => __( 'program template', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::KIND_OWN      => __( 'institution-specific', 'wpcredits-program-manager' ),
			WPCPM_Institution_Agreement::KIND_LEGACY   => __( 'legacy', 'wpcredits-program-manager' ),
		);

		$parts = array( isset( $states[ $state ] ) ? $states[ $state ] : $state );

		if ( isset( $kinds[ $kind ] ) ) {
			$parts[] = $kinds[ $kind ];
		}

		if ( ! empty( $summary['accepted_at'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: date. */
				__( 'accepted %s', 'wpcredits-program-manager' ),
				(string) $summary['accepted_at']
			);
		}

		if ( 'grid' === $route ) {
			$parts[] = __( 'recorded in the Airtable grid', 'wpcredits-program-manager' );
		} elseif ( 'site' === $route ) {
			$parts[] = __( 'recorded on the site', 'wpcredits-program-manager' );
		}

		return implode( ', ', $parts );
	}
}
