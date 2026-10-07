<?php
/**
 * Sponsors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 4 - Sponsors.
 *
 * The companies that fund mentors and offer their tools to students. Airtable is the record of
 * who a sponsor is; the site holds what Airtable cannot. This class is the sync module (the
 * three-hourly read into `WPCPM_Sponsors_Index`), the wp-admin screen, and the two things a
 * manager does on it: create the accounts of the Approved sponsors that have none, and attach or
 * remove the accounts that act for each. Nothing is ever provisioned by the sync (design spec of 4
 * September 2026, decision 9).
 */
class WPCPM_Sponsors extends WPCPM_Sync_Module {

	// The Accounts tab is the accounts screen every audience's module shares: its load hook, its
	// list's form and the presses made in it, a row's invitation and the rows-per-page choice. Two
	// of its methods this class answers itself and runs under other names: the load hook
	// (`load_screen()`), and the words for what a press left (`notice_sentence()`), which ask the
	// list's own wording first, then the application queue's.
	use WPCPM_Accounts_Screen {
		load_screen as private load_accounts_list;
		notice_sentence as protected list_notice_sentence;
	}

	const ACTION_SYNC   = 'wpcpm_sponsors_sync';
	const ACTION_CANCEL = 'wpcpm_sponsors_cancel';
	const ACTION_TICK   = 'wpcpm_sponsors_tick';

	/**
	 * The screen's tabs, slug => label, in the bar's order: by the job a manager opens the screen
	 * for, the queue first, which is the tab the screen opens on. English, as a constant has to hold
	 * them; `tab_labels()` translates them.
	 *
	 * The sync has no tab of its own: its card is drawn beside the sponsors it reads, on the
	 * Sponsors tab, which is where the health rows link this audience's sync.
	 */
	const TABS = array(
		'queue'            => 'Waiting for review',
		'sponsors'         => 'Sponsors',
		self::TAB_ACCOUNTS => 'Accounts',
		'offers'           => 'Offers and codes',
		'interests'        => 'Interests',
		'agreements'       => 'Agreements',
	);

	/** The name a sponsor's record ID travels under: the field the forms that act on one sponsor post it in. */
	const ARG_SPONSOR = 'wpcpm_sponsor';

	/** The screen's tab the accounts are on, which every link and form of the list returns to. */
	const TAB_ACCOUNTS = 'accounts';

	/**
	 * The user option the rows-per-page choice is kept in: the sponsor accounts table's own name,
	 * here too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_sponsors_per_page';

	/** The flash channel that carries what a press in the accounts list adds, beside the outcome. */
	const FLASH_DETAIL = 'sponsors_admin_detail';

	/** Admin-post action for one row's invitation, sent at once. */
	const ACTION_INVITE = 'wpcpm_sponsors_invite';

	/** Admin-post action for inviting every sponsor account that has never been invited. */
	const ACTION_BULK = 'wpcpm_sponsors_bulk_invite';

	/**
	 * How many accounts one Create account on the ticked sponsors creates.
	 *
	 * A ceiling and not a page size: each account made is told to Airtable, a request of its own,
	 * and ten of them stay well inside the time one request is given. Whatever is left is still
	 * listed on the No account view afterwards, so pressing it again is the way through.
	 */
	const PROVISION_LIMIT = 10;

	/** How many sponsors a Create account that could not give some an account names before "and N more". */
	const PROVISION_NAMES = 5;

	/** Create a sponsor's account. The nonce is keyed to the record: `wpcpm_sponsor_provision_<record>`. */
	const ACTION_PROVISION = 'wpcpm_sponsor_provision';

	/** Attach an existing account by address, or detach one; `wpcpm_op` says which. */
	const ACTION_MEMBERS = 'wpcpm_sponsor_members';

	/** The flash channel this screen reads. */
	const FLASH = 'sponsors_admin';

	/** The Airtable checkbox that says whether a sponsor has a site account. */
	const FIELD_DASHBOARD_ACCOUNT = 'Dashboard account';

	/** Audit kinds this class writes. */
	const LOG_PROVISIONED = 'provisioned';

	/** How many interest rows the log card shows. */
	const INTERESTS_SHOWN = 50;

	/** Seed a sponsor's first offer from the index, for the accounts provisioned before offers existed (plan ruling 7). */
	const ACTION_SEED = 'wpcpm_offer_seed';

	/** Void a claimed code so the person may claim again. */
	const ACTION_CLAIM_VOID = 'wpcpm_claim_void';

	/** The Offers card lists this many offers at most. */
	const OFFERS_SHOWN = 100;

	/**
	 * Where the menu bubble stops counting.
	 *
	 * Drawn on every admin page load for every manager, so the three queues behind it are each
	 * read under this ceiling; past it the exact number changes nothing a manager does next.
	 */
	const COUNT_MAX = 200;

	/** The menu bubble's cache: one transient, a minute long (clean-up 1.98.1). */
	const TRANSIENT_ATTENTION = 'wpcpm_sponsors_attention';
	const ATTENTION_SECONDS   = 60;

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'sponsors';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Sponsors', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_SPONSOR;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'The companies that sponsor mentors and offer their tools to students. Each has a Sponsor Dashboard, its own people and its offer; their accounts are created and invited on the Accounts tab.', 'wpcredits-program-manager' );
	}

	/**
	 * The menu label, with a bubble for what needs a manager (spec 5.8).
	 *
	 * @return string
	 */
	public function menu_label() {
		$pending = self::attention_count();

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
	 * Applications waiting, plus agreements awaiting review, plus posts pending.
	 *
	 * Each behind a guard, because the three classes ship in three phases and the bubble is
	 * drawn on every admin page: a class missing from a partial deploy must cost a zero, not a
	 * fatal on every screen in the site. Cached for a minute (clean-up 1.98.1): three post
	 * queries on every admin page for every manager was the S5 whole-branch review's finding.
	 *
	 * @return int
	 */
	public static function attention_count() {
		$cached = get_transient( self::TRANSIENT_ATTENTION );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = 0;

		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$count += (int) WPCPM_Sponsor_Application::pending_count( self::COUNT_MAX + 1 );
		}

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) && method_exists( 'WPCPM_Sponsor_Agreement', 'awaiting_review' ) ) {
			$count += count( (array) WPCPM_Sponsor_Agreement::awaiting_review( self::COUNT_MAX ) );
		}

		if ( class_exists( 'WPCPM_Sponsor_Posts' ) && method_exists( 'WPCPM_Sponsor_Posts', 'pending_all' ) ) {
			$count += count( (array) WPCPM_Sponsor_Posts::pending_all( self::COUNT_MAX ) );
		}

		// Three queries on every admin page was the S5 review's finding; a minute is short
		// enough that a manager sees their own decision land (the hooks below forget the cache
		// the moment a row changes) and long enough that a busy morning costs one count.
		set_transient( self::TRANSIENT_ATTENTION, $count, self::ATTENTION_SECONDS );

		return $count;
	}

	/** Drop the bubble's cache; the next admin page counts again. */
	public static function forget_attention() {
		delete_transient( self::TRANSIENT_ATTENTION );
	}

	/**
	 * A post of one of the three kinds the bubble counts changed status.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post The post.
	 */
	public static function forget_attention_on_status( $new, $old, $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, self::attention_post_types(), true ) ) {
			self::forget_attention();
		}
	}

	/**
	 * An application's or an agreement's state meta changed.
	 *
	 * @param int    $meta_id  Meta row ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 */
	public static function forget_attention_on_meta( $meta_id, $post_id, $meta_key ) {
		if ( in_array( (string) $meta_key, self::attention_meta_keys(), true ) ) {
			self::forget_attention();
		}
	}

	/**
	 * The post types the bubble counts, of the classes that exist.
	 *
	 * @return string[]
	 */
	private static function attention_post_types() {
		$types = array( 'post' );

		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$types[] = WPCPM_Sponsor_Application::POST_TYPE;
		}

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			$types[] = WPCPM_Sponsor_Agreement::POST_TYPE;
		}

		return $types;
	}

	/**
	 * The state meta keys whose change moves the bubble.
	 *
	 * @return string[]
	 */
	private static function attention_meta_keys() {
		$keys = array();

		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$keys[] = WPCPM_Sponsor_Application::META_STATE;
		}

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			$keys[] = WPCPM_Sponsor_Agreement::META_STATE;
		}

		return $keys;
	}

	/**
	 * The sync class.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return 'WPCPM_Sponsors_Sync';
	}

	/**
	 * The flash channel.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return self::FLASH;
	}

	/**
	 * The sponsor accounts table: the list the Accounts tab draws, whose role and stamp the
	 * invitations read, and whose rule the rows-per-page save keeps.
	 *
	 * @return string
	 */
	protected static function table_class() {
		return 'WPCPM_Sponsors_Table';
	}

	/**
	 * The words the screen prints for sponsor accounts, in the places every accounts screen prints
	 * its audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists. The warning while
	 * Airtable is not connected is printed from here by this screen's frame, above every tab
	 * (`render_admin_page()`), through the printer every accounts screen shares
	 * (`render_not_connected()`).
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter sponsor accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Sponsor accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Sponsor accounts list', 'wpcredits-program-manager' ),
			'not_connected'      => __( 'Airtable is not connected yet, so no sponsors can be synced.', 'wpcredits-program-manager' ),
			'list_heading'       => __( 'Sponsor accounts', 'wpcredits-program-manager' ),
			'page_label'         => __( 'Sponsor Dashboard:', 'wpcredits-program-manager' ),
			'page_missing'       => __( 'The Sponsor Dashboard page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ),
			'search'             => __( 'Search sponsor accounts', 'wpcredits-program-manager' ),
			'list_note'          => __( 'Accounts are created with a random password. "Send invite" emails the account a password-set link so its holder can set their own, and "Resend invite" sends a fresh one, which replaces the link in any earlier invitation.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The Sponsor Dashboard's address, which the list card names, or '' while the page is missing.
	 *
	 * Guarded, as every reference to the dashboard's class here is, so the screen draws with or
	 * without the front end.
	 *
	 * @return string
	 */
	protected function dashboard_url() {
		if ( ! class_exists( 'WPCPM_Sponsors_Dashboard' ) || ! method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ) {
			return '';
		}

		return (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) );
	}

	/**
	 * Send one sponsor account its invitation now, through the members class, which keeps the stamp
	 * the invitations read (`WPCPM_Sponsor_Members::send_invite()`), in place of the accounts screen's
	 * default (`WPCPM_Accounts_Screen::invite_one()`).
	 *
	 * Reached from a row's invitation, once the capability and the nonce keyed to the account have
	 * both been checked (`handle_invite()`).
	 *
	 * @param int $user_id The account.
	 * @return true|WP_Error
	 */
	protected function invite_one( $user_id ) {
		return WPCPM_Sponsor_Members::send_invite( $user_id );
	}

	/**
	 * Hooks. The front-end classes are guarded because the screen must draw with or without
	 * them: they ship in the same release, and the guard costs nothing once they do.
	 */
	public function boot() {
		WPCPM_Ceiling::init();

		foreach ( array( 'WPCPM_Sponsors_Dashboard', 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Tools', 'WPCPM_Sponsor_Interests', 'WPCPM_Sponsor_Mentors', 'WPCPM_Sponsor_Posts', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Agreement', 'WPCPM_Sponsor_Application' ) as $front ) {
			if ( class_exists( $front ) && method_exists( $front, 'init' ) ) {
				call_user_func( array( $front, 'init' ) );
			}
		}

		WPCPM_Sponsors_Sync::register_cron();

		// The retention run, put back on the clock whenever it is missing: see the method.
		add_action( 'init', array( __CLASS__, 'schedule_cron' ), 20 );

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_PROVISION, array( $this, 'handle_provision' ) );
		add_action( 'admin_post_' . self::ACTION_MEMBERS, array( $this, 'handle_members' ) );
		add_action( 'admin_post_' . self::ACTION_SEED, array( $this, 'handle_seed' ) );
		add_action( 'admin_post_' . self::ACTION_CLAIM_VOID, array( $this, 'handle_claim_void' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// Once per site, on the first admin page after 1.99.0: the accounts an older detach left
		// holding posting capabilities (FSPON-1). See the method.
		add_action( 'admin_init', array( 'WPCPM_Sponsor_Members', 'maybe_repair_detached' ) );

		// The menu bubble's cache, forgotten the moment a row it counts changes (clean-up 1.98.1).
		add_action( 'transition_post_status', array( __CLASS__, 'forget_attention_on_status' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'forget_attention_on_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'forget_attention_on_meta' ), 10, 3 );

		// The Accounts tab: a row's invitation and the invitations card's button, then the tab's own
		// load hook, once the menu exists, and its rows-per-page save.
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( $this, 'handle_bulk_invite' ) );
		$this->boot_screen();
	}

	/**
	 * Activation: the schedule and the page.
	 */
	public function activate() {
		WPCPM_Sponsors_Sync::activate();

		self::schedule_cron();

		// A fresh install can have no account an older detach() left holding a posting
		// capability, so the one-time repair in boot() has nothing to find; set its flag here
		// instead of letting its get_users() query run for nothing on the first admin page.
		update_option( WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED, 1, true );

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'ensure_page' ) ) {
			call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'ensure_page' ) );
		}

		// The application form's page, and deliberately not gated: see its `ensure_page()`.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			WPCPM_Sponsor_Application::ensure_page();
		}
	}

	/**
	 * Put the agreement's retention run and the application queue's on the clock, if they are not there already.
	 *
	 * **Called from `boot()` on every load, not only from `activate()`.** The activation hook
	 * fires on an explicit activation and on nothing else: not on the files being replaced, and
	 * not on the upgrader's silent reactivation, which is how this site is deployed
	 * (`wp plugin install --force`). Until this method existed the job was scheduled only in
	 * `activate()`, so a site installed or updated through the upgrader would never discard a
	 * withdrawn or returned document's file again, and nothing on any screen would say so. The
	 * sync already self-heals this way on every boot; this is the same rule for the retention
	 * run. `wp_next_scheduled()` makes it cheap and idempotent: one cache read on a normal load,
	 * one write when the job is missing.
	 *
	 * Daily, because the setting behind it is in days, and eight hours past activation: the
	 * institutions module's own nightly jobs sit at one to six hours past its, so a slow night
	 * never has two of them walking the private store at once.
	 */
	public static function schedule_cron() {
		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) && ! wp_next_scheduled( WPCPM_Sponsor_Agreement::CRON_DISCARD ) ) {
			wp_schedule_event( time() + ( 8 * HOUR_IN_SECONDS ), 'daily', WPCPM_Sponsor_Agreement::CRON_DISCARD );
		}

		// The application queue's retention run (Phase S5), nine hours past activation: an hour
		// clear of the agreement's discard above and three clear of the institutions module's
		// last nightly job, so a slow night never has two of them walking the posts at once.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) && ! wp_next_scheduled( WPCPM_Sponsor_Application::CRON_PURGE ) ) {
			wp_schedule_event( time() + ( 9 * HOUR_IN_SECONDS ), 'daily', WPCPM_Sponsor_Application::CRON_PURGE );
		}
	}

	/**
	 * Deactivation: off the clock.
	 */
	public function deactivate() {
		WPCPM_Sponsors_Sync::deactivate();

		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD );
		}

		// The application queue's retention run leaves the clock with the plugin, as the discard
		// does: a daily event with no listener is what deactivation would otherwise leave behind.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE );
		}
	}

	/**
	 * Uninstall: the options this module owns. Accounts, attachments and the audit rows are
	 * not this module's to delete (spec section 11).
	 */
	public function uninstall() {
		delete_option( WPCPM_Sponsors_Sync::OPT_STATE );
		delete_option( WPCPM_Sponsors_Sync::OPT_REPORT );
		delete_option( WPCPM_Sponsors_Sync::OPT_LAST );
		delete_option( WPCPM_Sponsors_Sync::OPT_ERROR );
		delete_option( WPCPM_Sponsors_Sync::OPT_LOCK );
		delete_option( WPCPM_Sponsors_Sync::OPT_SHRINK );

		WPCPM_Sponsors_Index::delete_all();

		if ( class_exists( 'WPCPM_Sponsor_Offers' ) ) {
			WPCPM_Sponsor_Offers::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsor_Claims' ) ) {
			WPCPM_Sponsor_Claims::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsor_Posts' ) ) {
			WPCPM_Sponsor_Posts::uninstall_accounts();
			WPCPM_Sponsor_Posts::delete_all();
		}

		// The posts and the options; the files and the key stay, listed in the manifest the
		// Institutions module mailed a moment ago (spec section 11).
		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			WPCPM_Sponsor_Agreement::delete_all();
			wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD );
		}

		// The applications and their unapproved files, the page option and the log; then the
		// approval locks, which nothing else would ever delete (spec section 11).
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			WPCPM_Sponsor_Application::delete_all();
			wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE );
		}

		if ( class_exists( 'WPCPM_Sponsor_Approval' ) ) {
			WPCPM_Sponsor_Approval::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
			delete_option( WPCPM_Sponsors_Dashboard::OPT_PAGE );
			delete_option( WPCPM_Sponsors_Dashboard::OPT_TITLE_FIXED );
		}

		wp_clear_scheduled_hook( WPCPM_Sponsors_Sync::CRON_DAILY );
		wp_clear_scheduled_hook( WPCPM_Sponsors_Sync::CRON_TICK );

		delete_transient( self::TRANSIENT_ATTENTION );

		// The stamps are the plugin's; the accounts are not (spec section 11, the same
		// bargain the Institutions module strikes for its own member meta).
		foreach ( array(
			WPCPM_Sponsor_Members::META_RECORD_ID,
			WPCPM_Sponsor_Members::META_ACTIVE,
			WPCPM_Sponsor_Members::META_RECORD_ID_WAS,
			WPCPM_Sponsor_Members::META_MEMBERSHIP,
			WPCPM_Sponsor_Members::META_INVITED,
			// A literal, because the constant is gone: 1.93.0 to 1.98.1 stamped this on every
			// account at attach and nothing ever read it (FSPON-6). The write is gone; the rows
			// those releases wrote are still on the sites that ran them.
			'wpcpm_sponsor_profile',
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * This screen's outcomes, in the reader's words.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice class and sentence.
	 */
	public static function messages() {
		$messages = array(
			'provisioned'        => array( 'success', __( 'The account was created and its invitation queued.', 'wpcredits-program-manager' ) ),
			'provision-attached' => array( 'success', __( 'An account with that address already existed and now acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'provision-admin'    => array( 'error', __( 'That address belongs to an administrator, who already reaches every sponsor.', 'wpcredits-program-manager' ) ),
			'provision-inactive' => array( 'error', __( 'Only an Approved sponsor can be given an account.', 'wpcredits-program-manager' ) ),
			'provision-no-email' => array( 'error', __( 'That sponsor has no Contact Email in Airtable. Add one there and sync.', 'wpcredits-program-manager' ) ),
			'provision-refused'  => array( 'error', __( 'That account cannot act for this sponsor.', 'wpcredits-program-manager' ) ),
			'provision-failed'   => array( 'error', __( 'The account could not be created.', 'wpcredits-program-manager' ) ),
			'airtable-failed'    => array( 'warning', __( 'Done on the site, but Airtable could not be told: the Dashboard account checkbox is out of step until the next attempt.', 'wpcredits-program-manager' ) ),
			'attached'           => array( 'success', __( 'The account now acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'attach-no-account'  => array( 'error', __( 'No account has that address. Attach account attaches an account that exists; a sponsor\'s first account is made with Create account on the No account view.', 'wpcredits-program-manager' ) ),
			'attach-refused'     => array( 'error', __( 'That account cannot act for this sponsor.', 'wpcredits-program-manager' ) ),
			'detached'           => array( 'success', __( 'The account no longer acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'detach-refused'     => array( 'error', __( 'That account could not be detached.', 'wpcredits-program-manager' ) ),
			'refused'            => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
			'posting-on'         => array( 'success', __( 'Posting is on for this sponsor: its accounts can write posts and submit them for review.', 'wpcredits-program-manager' ) ),
			'posting-off'        => array( 'success', __( 'Posting is off for this sponsor: its accounts can no longer write posts.', 'wpcredits-program-manager' ) ),
			'offer-seeded'       => array( 'success', __( 'The first offer was seeded from the base; the sponsor completes it and switches it on from the Sponsor Dashboard.', 'wpcredits-program-manager' ) ),
			'offer-seed-none'    => array( 'info', __( 'That sponsor already has an offer; nothing was seeded.', 'wpcredits-program-manager' ) ),
			'offer-seed-failed'  => array( 'error', __( 'The offer could not be seeded: the index does not hold that sponsor.', 'wpcredits-program-manager' ) ),
			'claim-voided'       => array( 'success', __( 'The claim was voided. The person may claim again; the code stays void for the count.', 'wpcredits-program-manager' ) ),
			'claim-void-none'    => array( 'info', __( 'That person holds no claim on that offer.', 'wpcredits-program-manager' ) ),
			'claim-void-busy'    => array( 'info', __( 'Another change to that offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
		);

		// The agreement's own outcomes, so a review pressed on this screen has words to print.
		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Agreement::manager_messages() );
		}

		// The application queue's own outcomes, so a decision pressed on this screen has words.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Application::manager_messages() );
		}

		return $messages;
	}

	/**
	 * The sentence one status prints, worded from what the press carried beside it: the accounts
	 * list's words for a press made in it, a row's Create account among them (the accounts screen's
	 * own reading, kept under another name: `list_notice_sentence()`), and the queue's `sapp-account`
	 * sentence, which names what the site said when the account step failed.
	 *
	 * Each detail is one-shot, so it is resolved here, on the one status being printed, and
	 * never inside `messages()`: this map is built by anything that wants a sentence out of it,
	 * `WPCPM_Sponsor_Approval::status_sentence()` among them, in the middle of the POST that
	 * set the detail (S5 review). The two travel on channels of their own, and each is taken
	 * whichever status is printed, so neither waits to surface under a later press; where the list
	 * words the status, its words stand, since the two never word the same one.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from `messages()`.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		$listed = $this->list_notice_sentence( $status, $sentence );
		$queued = class_exists( 'WPCPM_Sponsor_Application' ) ? WPCPM_Sponsor_Application::sentence_for( $status, $sentence ) : (string) $sentence;

		return (string) $sentence !== $listed ? $listed : $queued;
	}

	/**
	 * Create one sponsor's account: from a ready row's Create account on the Accounts tab's No
	 * account view, a link, or from a form that posts the sponsor.
	 *
	 * The capability, then the nonce keyed to the record, then the policy (a manager passes,
	 * and the ground goes in the log), then whether the sponsor has an account already, then the
	 * record's own facts. The record is the posted field's, or the address's when nothing is
	 * posted, as a row's link carries it, read with `WPCPM_Request::text()` and never a `key()`:
	 * `sanitize_key()` lowercases, and a record ID is case-sensitive. It is read before the nonce
	 * is checked only because the nonce is keyed to it; nothing is done with it until both checks
	 * have passed.
	 *
	 * The press comes back to the list as the link says it stood, its view, search, sort and page
	 * (`list_url()`), as a row's invitation comes back; a posted form carries no list, and comes
	 * back to the Accounts tab itself. What provisioning said beside a refusal or a failure, why the
	 * account at the address cannot act for the sponsor or what WordPress said of an account it
	 * would not make, travels with the outcome as its reason, and the Sponsors table words it
	 * (`WPCPM_Sponsors_Table::action_sentence()`).
	 *
	 * A sponsor that has a live account already is given none, whatever its contact address: a link
	 * left open while an account at another address was attached would otherwise make a second
	 * account and email its invitation, and the second press of a double click would report the
	 * account the first press made as one attached. It is a status of its own, `provision-already`,
	 * left with nothing beside it, as a Create account on the ticked sponsors leaves alone a sponsor
	 * that has had its account since the page was drawn.
	 */
	public function handle_provision() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Request::posted_text( self::ARG_SPONSOR );

		if ( '' === $record ) {
			$record = WPCPM_Request::text( self::ARG_SPONSOR );
		}

		check_admin_referer( self::ACTION_PROVISION . '_' . $record );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PROVISION, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Sponsor_Policy::refusal()->get_error_message() ), 403 );
		}

		if ( ! empty( WPCPM_Sponsor_Members::members_of( $record ) ) ) {
			$this->leave( $this->list_url(), 'provision-already' );
		}

		$result = self::provision( $record, get_current_user_id() );
		$reason = (string) $result['detail'];

		$this->leave( $this->list_url(), $result['status'], '' === $reason ? array() : array( 'reason' => $reason ) );
	}

	/**
	 * Queue an invitation for every sponsor account that has never had one, then back to the
	 * Accounts tab, as every other press on the tab comes back to it.
	 *
	 * Queued rather than sent here: one account's invitation goes at once (`invite_one()`), which is
	 * right for one row and would time out somewhere in the middle of a hundred. The queue is
	 * drained by cron a batch at a time, which is what it was built for.
	 *
	 * The count a press on the ticked rows carries is that press's alone, and this button leaves the
	 * same outcomes with none: `leave()` empties that channel, so a count never printed is not shown
	 * under these.
	 */
	public function handle_bulk_invite() {
		$this->verify( self::ACTION_BULK );

		$pending = self::never_invited();

		if ( empty( $pending ) ) {
			$this->leave( $this->accounts_url(), 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->leave( $this->accounts_url(), 'invites-queued' );
	}

	/**
	 * The account half of provisioning: the row's facts, the account, the membership.
	 *
	 * Lifted out of `provision()` for Phase S5 so that `WPCPM_Sponsor_Approval` runs the same
	 * body: the index row must be Approved with a contact address, an account at that address
	 * is attached rather than duplicated (never an administrator's), a missing one is made
	 * through `WPCPM_Roles::insert_user()`, and `attach()` decides under the rules of spec 5.1.
	 * "Already attached" is reported as `already` and is good news to both callers.
	 *
	 * @param string $record   Sponsor record ID.
	 * @param int    $actor_id The manager.
	 * @return array `status` (a key of `messages()`), `user_id`, `created`, `already`, `detail`.
	 */
	public static function provision_account( $record, $actor_id ) {
		$answer = array(
			'status'  => '',
			'user_id' => 0,
			'created' => false,
			'already' => false,
			'detail'  => '',
		);

		$row = WPCPM_Sponsors_Index::row( $record );

		if ( ! is_array( $row ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
			$answer['status'] = 'provision-inactive';

			return $answer;
		}

		$email = sanitize_email( $row['contact_email'] );

		if ( ! is_email( $email ) ) {
			$answer['status'] = 'provision-no-email';

			return $answer;
		}

		$existing = get_user_by( 'email', $email );
		$created  = false;

		if ( $existing instanceof WP_User && $existing->exists() ) {
			if ( WPCPM_Roles::user_has_role( $existing, WPCPM_Roles::ROLE_ADMIN ) ) {
				$answer['status'] = 'provision-admin';

				return $answer;
			}

			$user_id = (int) $existing->ID;
		} else {
			$name  = '' !== trim( $row['contact_person'] ) ? trim( $row['contact_person'] ) : trim( $row['name'] );
			$login = self::unique_login( $email );
			$made  = WPCPM_Roles::insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true, true ),
					'display_name' => '' !== $name ? $name : $login,
					'nickname'     => '' !== $name ? $name : $login,
					'role'         => WPCPM_Roles::ROLE_SPONSOR,
				)
			);

			if ( is_wp_error( $made ) ) {
				$answer['status'] = 'provision-failed';
				$answer['detail'] = $made->get_error_message();

				return $answer;
			}

			$user_id = (int) $made;
			$created = true;
		}

		$attached = WPCPM_Sponsor_Members::attach( $user_id, $record, WPCPM_Sponsor_Members::HOW_PROVISIONED, $actor_id );

		if ( is_wp_error( $attached ) ) {
			// Already attached here is the one refusal that is good news. in_array() rather than
			// a direct strict comparison on the error code: the sponsor policy suite scans this
			// module's file for a sponsor-prefixed string beside that operator, to keep record-ID
			// comparisons behind the policy's own helper alone, and a bare one here would read
			// as exactly that to a scan that cannot see this is an error code, not a record ID.
			if ( in_array( $attached->get_error_code(), array( 'wpcpm_sponsor_member_already' ), true ) ) {
				return array(
					'status'  => 'provision-attached',
					'user_id' => $user_id,
					'created' => $created,
					'already' => true,
					'detail'  => '',
				);
			}

			$answer['status'] = 'provision-refused';
			$answer['detail'] = $attached->get_error_message();

			return $answer;
		}

		return array(
			'status'  => $created ? 'provisioned' : 'provision-attached',
			'user_id' => $user_id,
			'created' => $created,
			'already' => false,
			'detail'  => '',
		);
	}

	/**
	 * Create or attach the account behind a sponsor's contact address, and everything that
	 * follows on this screen: the invitation, the first offer, the base's checkbox, the log.
	 *
	 * @param string $record   Sponsor record ID.
	 * @param int    $actor_id The manager.
	 * @return array `status` (a key of `messages()`), `user_id`, `detail`.
	 */
	public static function provision( $record, $actor_id ) {
		$account = self::provision_account( $record, $actor_id );

		// A refusal, or an account this sponsor already had: nothing follows either.
		if ( ! in_array( $account['status'], array( 'provisioned', 'provision-attached' ), true ) || ! empty( $account['already'] ) ) {
			return array(
				'status'  => $account['status'],
				'user_id' => (int) $account['user_id'],
				'detail'  => (string) $account['detail'],
			);
		}

		$user_id = (int) $account['user_id'];
		$created = ! empty( $account['created'] );

		// Queued rather than sent: the queue is what the mail log and the stop control are
		// built on, and `queue_invites()` drops an account already invited once.
		WPCPM_Mail::queue_invites( array( $user_id ) );

		// The first offer, from the index (spec 6.1). seed() refuses a sponsor that has one, so an
		// account attached to a sponsor already provisioned does not make a second.
		if ( class_exists( 'WPCPM_Sponsor_Offers' ) ) {
			WPCPM_Sponsor_Offers::seed( $record );
		}

		$marked = self::mark_dashboard_account( $record, true );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_PROVISIONED,
				'sponsor'  => $record,
				'subject'  => (string) $user_id,
				'actor'    => $actor_id,
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $created
					? __( 'A sponsor account was created from the contact address and its invitation queued.', 'wpcredits-program-manager' )
					: __( 'The existing account at the contact address was attached to the sponsor.', 'wpcredits-program-manager' ),
				'data'     => array(
					'user'     => $user_id,
					'created'  => $created,
					'airtable' => ! is_wp_error( $marked ),
				),
			)
		);

		if ( is_wp_error( $marked ) ) {
			return array(
				'status'  => 'airtable-failed',
				'user_id' => $user_id,
				'detail'  => $marked->get_error_message(),
			);
		}

		return array(
			'status'  => $created ? 'provisioned' : 'provision-attached',
			'user_id' => $user_id,
			'detail'  => '',
		);
	}

	/**
	 * The Approved sponsors that have no account yet, as the Accounts tab's No account view lists
	 * them: the ready ones first, then by name.
	 *
	 * Every index row whose status is Approved and that no live account acts for (the accounts
	 * `accounts_by_sponsor()` groups, one query for them all), each with its record ID as `id`; its
	 * name from the index, trimmed, or the record ID where the index has none, as `name`; its contact
	 * address masked, never whole, or '' when it has none `is_email()` accepts, as `contact`; whether
	 * an account already uses that address, which a Create account attaches rather than duplicates,
	 * as `existing`; why it may not be provisioned, as `reason`: '' when it may, `no-email` with no
	 * address, `admin` when the address is an administrator's, which provisioning refuses
	 * (`provision_account()`); and whether it may, as `ready`. That is what can be known before a
	 * press: an account at the address that is a student's, an institution member's or another
	 * sponsor's is refused by the members class only when the press attaches it, and the press says
	 * why (`provision_ticked()`). In the order a worklist reads from the top: what can be done now
	 * first, then the rest by name, read as a reader expects names, without regard to case or
	 * accents, the record ID settling two of one name.
	 *
	 * Costs the live accounts' one query and an account looked up by address for each sponsor with
	 * one, so the list reads it once a draw (`WPCPM_Sponsors_Table`).
	 *
	 * @return array[] Rows: `id`, `name`, `contact`, `existing`, `reason`, `ready`.
	 */
	public static function provision_worklist() {
		$accounts = self::accounts_by_sponsor();
		$rows     = array();

		foreach ( WPCPM_Sponsors_Index::rows() as $record => $row ) {
			$record = (string) $record;

			if ( ! is_array( $row ) || ! isset( $row['status'] ) || WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] || ! empty( $accounts[ $record ] ) ) {
				continue;
			}

			$name     = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$email    = sanitize_email( isset( $row['contact_email'] ) ? (string) $row['contact_email'] : '' );
			$valid    = (bool) is_email( $email );
			$holder   = $valid ? get_user_by( 'email', $email ) : false;
			$existing = $holder instanceof WP_User && $holder->exists();

			if ( ! $valid ) {
				$reason = 'no-email';
			} elseif ( $existing && WPCPM_Roles::user_has_role( $holder, WPCPM_Roles::ROLE_ADMIN ) ) {
				$reason = 'admin';
			} else {
				$reason = '';
			}

			$rows[] = array(
				'id'       => $record,
				'name'     => '' !== $name ? $name : $record,
				'contact'  => $valid ? WPCPM_Mail::mask_address( $email ) : '',
				'existing' => $existing,
				'reason'   => $reason,
				'ready'    => '' === $reason,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['ready'] !== $b['ready'] ) {
					return $a['ready'] ? -1 : 1;
				}

				$order = strcasecmp( remove_accents( $a['name'] ), remove_accents( $b['name'] ) );

				return 0 !== $order ? $order : strcmp( $a['id'], $b['id'] );
			}
		);

		return $rows;
	}

	/**
	 * Create the accounts of the sponsors ticked on the No account view, and say what came of it:
	 * the outcome and its detail, which the list carries back to the view.
	 *
	 * The ticked records, each once, are matched against the view as it is now
	 * (`provision_worklist()`): the ones it lists as ready are taken, in its order, and any other, a
	 * sponsor that has had its account since the page was drawn or one a stale page offered, is left
	 * alone; the checkbox a row that is not ready lacks is no guard
	 * (`WPCPM_Sponsors_Table::row_tickable()`). At most `PROVISION_LIMIT` are tried a press, and the
	 * ready ones past it stay listed on the view, counted as `left`. Each is decided by the sponsor
	 * policy, as the manager who pressed, and a refusal is that sponsor not given an account, named
	 * with the policy's sentence, never the end of the press; then made by provisioning
	 * (`provision()`), as a row's Create account makes it.
	 *
	 * What provisioning answered is counted: an account made, `provisioned`, under `created`; the
	 * account that already used the address, attached, `provision-attached`, under `attached`; one
	 * made or attached that Airtable could not be told about, `airtable-failed`, under `airtable`, and
	 * under `created` or `attached` too, by whether the view saw an account at the address. Anything
	 * else is a sponsor not given an account, named under `failed` with what provisioning said, or
	 * the sentence this screen keeps for its outcome where it said nothing (`messages()`): the first
	 * `PROVISION_NAMES` named, and how many more as `failed_more`.
	 *
	 * `provision-none`, with no detail, when no ticked sponsor was ready; `provision-failed` when any
	 * was not given an account; `provisioned` otherwise. The detail holds `created`, `attached`,
	 * `airtable`, `left` and `limit`, the ceiling, which the words for it name
	 * (`WPCPM_Sponsors_Table::action_sentence()`).
	 *
	 * Checks neither the capability nor a nonce: the accounts screen checks both before the table
	 * hands the ticked records over (`WPCPM_Accounts_Screen::handle_list_form()`), and the records are
	 * matched against the view the site holds.
	 *
	 * @param string[] $records The ticked sponsors' record IDs.
	 * @return array{0: string, 1: array} The outcome and its detail.
	 */
	public static function provision_ticked( array $records ) {
		$ticked = array_flip( array_unique( array_map( 'strval', $records ) ) );
		$ready  = array();

		foreach ( self::provision_worklist() as $row ) {
			if ( $row['ready'] && isset( $ticked[ $row['id'] ] ) ) {
				$ready[] = $row;
			}
		}

		if ( empty( $ready ) ) {
			return array( 'provision-none', array() );
		}

		$tried    = array_slice( $ready, 0, self::PROVISION_LIMIT );
		$messages = self::messages();
		$failed   = array();
		$detail   = array(
			'created'  => 0,
			'attached' => 0,
			'airtable' => 0,
			'left'     => count( $ready ) - count( $tried ),
			'limit'    => self::PROVISION_LIMIT,
		);

		foreach ( $tried as $row ) {
			$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_PROVISION, WPCPM_Sponsor_Policy::subject_sponsor( $row['id'] ) );

			if ( empty( $decision['allowed'] ) ) {
				$failed[] = self::failed_named( $row['name'], WPCPM_Sponsor_Policy::refusal()->get_error_message() );
				continue;
			}

			$result = self::provision( $row['id'], get_current_user_id() );
			$status = (string) $result['status'];

			if ( 'provisioned' === $status ) {
				++$detail['created'];
			} elseif ( 'provision-attached' === $status ) {
				++$detail['attached'];
			} elseif ( 'airtable-failed' === $status ) {
				++$detail['airtable'];
				++$detail[ $row['existing'] ? 'attached' : 'created' ];
			} else {
				$said     = '' !== (string) $result['detail'] ? (string) $result['detail'] : ( isset( $messages[ $status ][1] ) ? (string) $messages[ $status ][1] : '' );
				$failed[] = self::failed_named( $row['name'], $said );
			}
		}

		if ( empty( $failed ) ) {
			return array( 'provisioned', $detail );
		}

		return array(
			'provision-failed',
			$detail + array(
				'failed'      => array_slice( $failed, 0, self::PROVISION_NAMES ),
				'failed_more' => max( 0, count( $failed ) - self::PROVISION_NAMES ),
			),
		);
	}

	/**
	 * A sponsor not given an account, as a press's detail names it: its name, then why, with the
	 * reason's last stop dropped, since the sentence that lists them ends in its own.
	 *
	 * @param string $name The sponsor's name.
	 * @param string $why  Why it was not given an account, as provisioning or the policy said it.
	 * @return string
	 */
	private static function failed_named( $name, $why ) {
		$why = rtrim( trim( (string) $why ), '.!' );

		if ( '' === $why ) {
			return (string) $name;
		}

		return sprintf(
			/* translators: 1: the sponsor's name, 2: why it was not given an account. */
			__( '%1$s: %2$s', 'wpcredits-program-manager' ),
			(string) $name,
			$why
		);
	}

	/**
	 * Attach an existing account by address, or detach one.
	 */
	public function handle_members() {
		$this->verify( self::ACTION_MEMBERS );

		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );
		$op     = WPCPM_Request::posted_key( 'wpcpm_op' );

		$decision = WPCPM_Sponsor_Policy::decide( WPCPM_Sponsor_Policy::ACT_MANAGE_MEMBERS, WPCPM_Sponsor_Policy::subject_sponsor( $record ) );

		if ( empty( $decision['allowed'] ) || ! in_array( $op, array( 'attach', 'detach' ), true ) ) {
			$this->redirect_back( 'refused' );
		}

		if ( 'attach' === $op ) {
			$email = sanitize_email( WPCPM_Request::posted_text( 'wpcpm_email' ) );
			$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

			if ( ! $user instanceof WP_User || ! $user->exists() ) {
				$this->redirect_back( 'attach-no-account' );
			}

			$attached = WPCPM_Sponsor_Members::attach( $user->ID, $record, WPCPM_Sponsor_Members::HOW_MANAGER, get_current_user_id() );

			if ( is_wp_error( $attached ) ) {
				$this->redirect_back( 'attach-refused' );
			}

			WPCPM_Mail::queue_invites( array( (int) $user->ID ) );

			$this->redirect_back( is_wp_error( self::mark_dashboard_account( $record, true ) ) ? 'airtable-failed' : 'attached' );
		}

		$user_id  = WPCPM_Request::posted_id( 'wpcpm_user' );
		$detached = WPCPM_Sponsor_Members::is_member( $user_id, $record )
			? WPCPM_Sponsor_Members::detach( $user_id, WPCPM_Sponsor_Members::REASON_REMOVED, get_current_user_id() )
			: WPCPM_Sponsor_Policy::refusal();

		if ( is_wp_error( $detached ) ) {
			$this->redirect_back( 'detach-refused' );
		}

		$status = 'detached';

		if ( empty( WPCPM_Sponsor_Members::members_of( $record ) ) && is_wp_error( self::mark_dashboard_account( $record, false ) ) ) {
			$status = 'airtable-failed';
		}

		$this->redirect_back( $status );
	}

	/**
	 * Seed a sponsor's first offer from the index. A manager's action: the sponsors
	 * provisioned in S1 predate offers, and the button is how they get theirs.
	 */
	public function handle_seed() {
		$record = WPCPM_Request::posted_text( 'wpcpm_sponsor' );
		$this->verify( self::ACTION_SEED . '_' . $record );

		// Spec section 4: every sponsor action is decided by the policy, and this was the one
		// that was not (final review of Phase S2, finding 9). The capability check above already
		// held it to managers; the claim adds the index check and the recorded ground.
		$claim = WPCPM_Sponsor_Roster::claim( $record, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );

		if ( is_wp_error( $claim ) ) {
			$this->redirect_back( 'refused' );
		}

		$record = $claim['record'];
		$seeded = WPCPM_Sponsor_Offers::seed( $record );

		if ( is_wp_error( $seeded ) ) {
			$this->redirect_back( 'offer-seed-failed' );
		}

		if ( false === $seeded ) {
			$this->redirect_back( 'offer-seed-none' );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => WPCPM_Sponsor_Offers::LOG_SEEDED,
				'sponsor'  => $record,
				'subject'  => (string) $seeded,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'The first offer was seeded from the base by a manager.', 'wpcredits-program-manager' ),
				'data'     => array( 'offer' => (int) $seeded ),
			)
		);

		$this->redirect_back( 'offer-seeded' );
	}

	/**
	 * Void a person's claim. The capability and the nonce, then the sponsor is read from the
	 * offer (never from the form) and claimed with ACT_VIEW_CLAIMANTS: a manager's ground.
	 */
	public function handle_claim_void() {
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );
		$user_id  = WPCPM_Request::posted_id( 'wpcpm_user' );
		$this->verify( self::ACTION_CLAIM_VOID . '_' . $offer_id . '_' . $user_id );

		$offer = WPCPM_Sponsor_Offers::read( $offer_id );

		if ( null === $offer ) {
			$this->redirect_back( 'refused' );
		}

		$claim = WPCPM_Sponsor_Roster::claim( $offer['sponsor'], WPCPM_Sponsor_Policy::ACT_VIEW_CLAIMANTS );

		if ( is_wp_error( $claim ) ) {
			$this->redirect_back( 'refused' );
		}

		// void_claim() answers true, false or a WP_Error (Task 4's fix round: the pool's lock can
		// be another request's for a moment), which is not the same "nothing to void" as a person
		// holding no claim, so the busy answer gets its own status rather than folding into
		// 'claim-void-none'.
		$voided = WPCPM_Sponsor_Claims::void_claim( $offer_id, $user_id, get_current_user_id() );

		if ( is_wp_error( $voided ) ) {
			$this->redirect_back( 'claim-void-busy' );
		}

		$this->redirect_back( $voided ? 'claim-voided' : 'claim-void-none' );
	}

	/**
	 * Every offer with its state and counts, who claimed from it (decision 7: managers read
	 * names), a Void button per claim, and a Seed button for a sponsor with an account and no
	 * offer.
	 *
	 * @param array $rows     The index rows, by record.
	 * @param array $accounts Accounts by sponsor (accounts_by_sponsor()).
	 */
	private function render_offers( array $rows, array $accounts ) {
		$offers = WPCPM_Sponsor_Offers::all();

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-offers">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Offers and codes', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $offers ) ) ) );
		echo '<p class="description">' . esc_html__( 'Every sponsor offer, its state and its codes, and who claimed one, for support. Sponsors see counts only. Voiding a claim lets the person claim again; the code stays void for the count.', 'wpcredits-program-manager' ) . '</p>';

		foreach ( $rows as $record => $row ) {
			if ( empty( $accounts[ $record ] ) || ! empty( WPCPM_Sponsor_Offers::offers_of( $record ) ) ) {
				continue;
			}

			printf( '<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr__( 'Seeding', 'wpcredits-program-manager' ) );
			wp_nonce_field( self::ACTION_SEED . '_' . $record );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SEED ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'offers' ) );
			/* translators: %s: sponsor name. */
			printf( '<button type="submit" class="button">%s</button>', esc_html( sprintf( __( 'Seed the first offer for %s from the base', 'wpcredits-program-manager' ), '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record ) ) );
			echo '</form> ';
		}

		if ( empty( $offers ) ) {
			echo '<p>' . esc_html__( 'Nothing yet.', 'wpcredits-program-manager' ) . '</p></div>';
			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';

		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Offer', 'wpcredits-program-manager' ), __( 'Kind', 'wpcredits-program-manager' ), __( 'State', 'wpcredits-program-manager' ), __( 'Available', 'wpcredits-program-manager' ), __( 'Claimed', 'wpcredits-program-manager' ), __( 'Void', 'wpcredits-program-manager' ), __( 'Warns at', 'wpcredits-program-manager' ), __( 'Last day', 'wpcredits-program-manager' ), __( 'Claimed by', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( array_slice( $offers, 0, self::OFFERS_SHOWN, true ) as $offer ) {
			$counts  = WPCPM_Sponsor_Codes::counts( $offer['id'] );
			$sponsor = isset( $rows[ $offer['sponsor'] ] ) ? trim( (string) $rows[ $offer['sponsor'] ]['name'] ) : $offer['sponsor'];

			echo '<tr>';
			echo '<td>' . esc_html( '' === $sponsor ? $offer['sponsor'] : $sponsor ) . '</td>';
			echo '<td>' . esc_html( $offer['title'] ) . ( $offer['primary'] ? ' <span class="description">' . esc_html__( '(in the base)', 'wpcredits-program-manager' ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? __( 'Codes', 'wpcredits-program-manager' ) : __( 'Shared', 'wpcredits-program-manager' ) ) . '</td>';
			echo '<td>' . esc_html( WPCPM_Sponsor_Offers::state_label( $offer['state'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['available'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['claimed'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $counts['void'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $offer['low'] ) ) . '</td>';
			echo '<td>' . esc_html( '' !== $offer['expires'] ? $offer['expires'] : '' ) . '</td>';
			echo '<td>';

			$claimants = WPCPM_Sponsor_Claims::claimants( $offer['id'] );

			if ( empty( $claimants ) ) {
				echo esc_html__( 'Nobody yet', 'wpcredits-program-manager' );
			} else {
				echo '<ul class="wpcpm-list">';

				foreach ( $claimants as $who ) {
					echo '<li>';
					printf(
						'%1$s <span class="description">%2$s</span> %3$s <code>%4$s</code> ',
						esc_html( '' !== $who['name'] ? $who['name'] : (string) $who['user_id'] ),
						esc_html( $who['email'] ),
						esc_html( wp_date( 'Y-m-d', (int) $who['at'] ) ),
						/* translators: %s: the code's last four characters. */
						esc_html( sprintf( __( 'ending %s', 'wpcredits-program-manager' ), $who['last4'] ) )
					);
					printf( '<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s" onsubmit="return confirm( \'%3$s\' );">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr__( 'Voiding', 'wpcredits-program-manager' ), esc_js( __( 'Void this claim? The person may then claim again.', 'wpcredits-program-manager' ) ) );
					wp_nonce_field( self::ACTION_CLAIM_VOID . '_' . $offer['id'] . '_' . $who['user_id'] );
					printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CLAIM_VOID ) );
					printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
					printf( '<input type="hidden" name="wpcpm_user" value="%d" />', (int) $who['user_id'] );
					printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'offers' ) );
					printf( '<button type="submit" class="button button-small">%s</button>', esc_html__( 'Void', 'wpcredits-program-manager' ) );
					echo '</form>';
					echo '</li>';
				}

				echo '</ul>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		if ( count( $offers ) > self::OFFERS_SHOWN ) {
			/* translators: %d: how many offers are listed. */
			echo '<p class="description">' . esc_html( sprintf( __( 'The oldest %d are listed.', 'wpcredits-program-manager' ), self::OFFERS_SHOWN ) ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Tell the base whether a sponsor has a site account, and the index at once.
	 *
	 * The one place the `Dashboard account` column is written (spec section 12): the provision
	 * handler (true), the members handler when the last account goes (false) and the sync's
	 * revoke phase (false).
	 *
	 * @param string $record Sponsor record ID.
	 * @param bool   $flag   Whether it has an account.
	 * @return true|WP_Error
	 */
	public static function mark_dashboard_account( $record, $flag ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return new WP_Error( 'wpcpm_sponsor_bad_record', __( 'That is not an Airtable record ID.', 'wpcredits-program-manager' ) );
		}

		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $record,
					'fields' => array( self::FIELD_DASHBOARD_ACCOUNT => (bool) $flag ),
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		WPCPM_Sponsors_Index::patch( $record, array( 'dashboard_account' => (bool) $flag ) );

		return true;
	}

	/**
	 * Before the screen draws its Accounts tab: the list, built and read as the accounts screen every
	 * audience shares builds it (`WPCPM_Accounts_Screen::load_screen()`), which this class keeps under
	 * another name and runs from its own: the load hook the shared screen adds names this method.
	 * Unless the address names a sponsor, whose accounts are drawn in the list's place
	 * (`render_sponsor_accounts()`).
	 *
	 * That view draws no list, so none is built for it: no account or sponsor is read for a list
	 * nobody sees, and Screen Options offers no rows-per-page choice or columns for it.
	 */
	public function load_screen() {
		if ( '' !== self::view_record() ) {
			return;
		}

		$this->load_accounts_list();
	}

	/**
	 * The sponsor whose accounts the request asks for: the record the Accounts tab's address names,
	 * with its case, or '' when the tab shown is another or the address names none.
	 *
	 * Read with `WPCPM_Request::text()` and never a `key()`: `sanitize_key()` lowercases, and a record
	 * ID is case-sensitive. Whether it names a sponsor the index holds is the view's to ask
	 * (`render_sponsor_accounts()`).
	 *
	 * @return string
	 */
	private static function view_record() {
		if ( self::TAB_ACCOUNTS !== self::tab() ) {
			return '';
		}

		return WPCPM_Request::text( self::ARG_SPONSOR );
	}

	/**
	 * One sponsor's accounts: the Accounts tab with the sponsor's record in its address, where they
	 * are drawn in the list's place (`render_sponsor_accounts()`) and where every press made on them
	 * comes back to.
	 *
	 * Public, because the Sponsor Dashboard's People card and the posting switch's handler send a
	 * manager there too, beside the Sponsors card's Manage accounts. The address is written out rather
	 * than asked of the module: the module's own address is an instance method (`admin_url()`), and
	 * the screen's page slug is fixed. The record is encoded as a query value; whether it names a
	 * sponsor is the view's to ask.
	 *
	 * @param string $record The sponsor's record ID, with its case.
	 * @return string
	 */
	public static function accounts_view_url( $record ) {
		return admin_url( 'admin.php?page=wpcpm-sponsors&tab=' . self::TAB_ACCOUNTS . '&' . self::ARG_SPONSOR . '=' . rawurlencode( trim( (string) $record ) ) );
	}

	/**
	 * Back to the screen with the outcome flashed, as every press here comes back
	 * (`WPCPM_Sync_Module::redirect_back()`), except that a press made on one sponsor's accounts comes
	 * back to them.
	 *
	 * Such a press posts the Accounts tab and the sponsor both, as Remove and Attach account do there,
	 * and leaves for `accounts_view_url()`; a press that posts either alone comes back to the tab it
	 * names, as any other does. The rule lives here rather than in the members handler: each of that
	 * handler's outcomes leaves through this method, and the forms that post to it name both fields,
	 * so the one rule brings every outcome back without the handler changing. It leaves through
	 * `leave()`, which also empties the channel a press on the ticked rows writes its count on, so no
	 * count waits there to be printed under this press's notice.
	 *
	 * @param string $status An outcome key the screen's message map knows.
	 */
	protected function redirect_back( $status ) {
		$record = WPCPM_Request::posted_text( self::ARG_SPONSOR );

		if ( self::TAB_ACCOUNTS === WPCPM_Request::posted_key( self::TAB_FIELD ) && '' !== $record ) {
			$this->leave( self::accounts_view_url( $record ), $status );
		}

		parent::redirect_back( $status );
	}

	/**
	 * The tabs as the bar prints them: TABS, each label translated.
	 *
	 * Each is written out here, because the translation tools collect a string only where it is
	 * written as one; a tab this does not name keeps the English of TABS. The screen's own, in place
	 * of the one the accounts screen every audience shares writes out (`WPCPM_Accounts_Screen`), which
	 * knows two tabs and calls the second Sync.
	 *
	 * The tab shown is the shared screen's reading (`tab()`): the one the address names, or the first
	 * of TABS, the queue, so the menu, which opens the screen at its own address, and every link that
	 * names no tab, the managers' mail about a new application and the way back from an opened
	 * application among them, open the queue.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private static function tab_labels() {
		$translated = array(
			'queue'            => __( 'Waiting for review', 'wpcredits-program-manager' ),
			'sponsors'         => __( 'Sponsors', 'wpcredits-program-manager' ),
			self::TAB_ACCOUNTS => __( 'Accounts', 'wpcredits-program-manager' ),
			'offers'           => __( 'Offers and codes', 'wpcredits-program-manager' ),
			'interests'        => __( 'Interests', 'wpcredits-program-manager' ),
			'agreements'       => __( 'Agreements', 'wpcredits-program-manager' ),
		);

		return array_merge( self::TABS, array_intersect_key( $translated, self::TABS ) );
	}

	/**
	 * Render the Sponsors screen: the outcome of the last press, the warning while Airtable is not
	 * connected, the tab bar, then the tab shown.
	 *
	 * Six tabs by job, in TABS: Waiting for review holds the sponsor applications, the sponsor posts
	 * and the signed agreements waiting, read here and decided on the Administrator Dashboard, or one
	 * application opened from them in their place; Sponsors the last sync's error, the Airtable sync
	 * and every sponsor; Accounts the accounts locked for the day, the invitations and the sponsor
	 * accounts list, or one sponsor's accounts in their place; Offers and codes every offer with its
	 * codes and claims; Interests what sponsors said they would like to support; and Agreements every
	 * Approved sponsor's agreement.
	 *
	 * The outcome notice is the whole screen's: one map of every outcome a press here can flash,
	 * printed above the bar on whichever tab is shown, as the warning while Airtable is not connected
	 * is. A press comes back to the tab it was made on, so its sentence prints there; the agreement
	 * forms come back by their referer, which is the tab they were pressed on. One whose way back
	 * names no tab lands on the screen's own address, which is the queue: a decision on an opened
	 * application. Each tab reads only what it draws.
	 */
	public function render_admin_page() {
		$tab = self::tab();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$this->render_status_notice( $this->outcome_messages( $tab ) );
		$this->render_not_connected();

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		switch ( $tab ) {
			case 'sponsors':
				$this->render_tab_sponsors();
				break;
			case self::TAB_ACCOUNTS:
				$this->render_tab_accounts();
				break;
			case 'offers':
				$this->render_tab_offers();
				break;
			case 'interests':
				$this->render_tab_interests();
				break;
			case 'agreements':
				$this->render_tab_agreements();
				break;
			default:
				$this->render_tab_queue();
		}

		echo '</div>';
	}

	/**
	 * Every outcome a press on this screen can flash on its channel, in the words the reader gets:
	 * one map, printed on whichever tab the press comes back to.
	 *
	 * The screen's own map (`messages()`), the outcomes of the presses made in the accounts list, worded
	 * once for every audience's accounts screen (`accounts_messages()`), which come back to the
	 * Accounts tab, and the two a Create account leaves when it makes nothing: on the ticked sponsors
	 * when none of them was ready, on one sponsor when it has an account already. `error` is worded
	 * by the tab it comes back to: a failed start of the sync comes back to the Sponsors tab, where
	 * the sync's words point to the last sync's error printed below them; a row's invitation that
	 * could not be sent comes back to the Accounts tab, which words it for the invitation; the other
	 * four tabs print no error below, so there the words send the reader to the screen again
	 * (`failed_message()`).
	 *
	 * @param string $tab The tab shown.
	 * @return array<string, array> Status => notice type and sentence.
	 */
	private function outcome_messages( $tab ) {
		$messages = self::messages();

		if ( 'sponsors' !== $tab ) {
			$messages['error'] = self::failed_message();
		}

		// Left by a Create account alone, which comes back to the list, so worded beside the list's
		// other outcomes rather than among the ones the forms on every tab flash.
		$messages['provision-none']    = array( 'info', __( 'There was nothing to create.', 'wpcredits-program-manager' ) );
		$messages['provision-already'] = array( 'info', __( 'That sponsor already has an account.', 'wpcredits-program-manager' ) );

		$invitations = $this->accounts_messages();

		if ( self::TAB_ACCOUNTS !== $tab ) {
			unset( $invitations['error'] );
		}

		return array_merge( $messages, $invitations );
	}

	/**
	 * The Waiting for review tab: the three kinds of item a manager decides, each in a card of its
	 * own in the Administrator Dashboard's order, or one application opened from them in their place.
	 *
	 * The tab reads and the Administrator Dashboard decides, a decision's one home: the sponsor
	 * applications, the sponsor posts and the signed agreements waiting are listed here, and each
	 * links to its place there. While the dashboard's page is missing there is nowhere to link, so
	 * the sentence its class keeps for that prints once, above the cards, and no card or row links.
	 *
	 * The opened application is a view of this tab, drawn instead of the cards rather than above
	 * them, as the Institutions screen draws its own; its "Back to the queue" is the screen's own
	 * address, which opens this tab, and it says where it is decided in its own place. An address
	 * that names no application draws the cards. Its renderers are the application class's, so the
	 * Administrator Dashboard and this screen draw one set of decisions.
	 */
	private function render_tab_queue() {
		$open = class_exists( 'WPCPM_Sponsor_Application' ) ? WPCPM_Sponsor_Application::open_from_request() : null;

		if ( $open instanceof WP_Post ) {
			WPCPM_Sponsor_Application::render_open( $open, $this->admin_url() );

			return;
		}

		if ( '' === WPCPM_Administrators_Dashboard::page_url() ) {
			// The dashboard class's sentence, which every screen that links there prints in the
			// address's place.
			echo '<p class="wpcpm-warning">' . esc_html( WPCPM_Administrators_Dashboard::page_missing() ) . '</p>';
		}

		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			WPCPM_Sponsor_Application::render_queue( $this->admin_url() );
		}

		$this->render_queue_posts();
		$this->render_queue_agreements();
	}

	/**
	 * The Sponsors tab: the last sync's error, the Airtable sync, then every sponsor.
	 *
	 * The error is said on the tab that runs the sync, above the card that runs it: it is that card's
	 * to explain, and its button is the way out of it. The sync's progress and its last read are
	 * asked here and on no other tab.
	 */
	private function render_tab_sponsors() {
		$rows     = WPCPM_Sponsors_Index::rows();
		$progress = WPCPM_Sponsors_Sync::progress();

		if ( ! empty( $progress['error'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( (string) $progress['error'] )
			);
		}

		$this->render_sync_panel( $progress, WPCPM_Sponsors_Sync::last_read() );
		$this->render_index( $rows, self::accounts_by_sponsor() );
	}

	/**
	 * The Accounts tab: the sponsor accounts locked out of changes for the rest of the day, the
	 * invitations card, then the sponsor accounts list, whose No account view lists the Approved
	 * sponsors with no account yet and creates the missing ones; or, when the address names a sponsor,
	 * that sponsor's accounts in their place (`render_sponsor_accounts()`).
	 *
	 * The screen's own, in place of the shared screen's: the notices of the presses made on the tab
	 * and the warning while Airtable is not connected are this screen's frame's, printed above the
	 * bar on every tab (`render_admin_page()`), so the tab prints neither again. The locked accounts
	 * are the list's own reading of them, which its Status cells read too, so one draw of the tab
	 * asks the ceiling once (`WPCPM_Sponsors_Table::locked_accounts()`) and the notice goes with the
	 * list, not above one sponsor's accounts; the list is the one the screen's load hook built, or one
	 * built and read now (`table()`).
	 */
	private function render_tab_accounts() {
		$record = self::view_record();

		if ( '' !== $record ) {
			$this->render_sponsor_accounts( $record );

			return;
		}

		$this->render_locked_accounts( $this->table()->locked_accounts() );
		$this->render_invitations();
		$this->render_accounts_list();
	}

	/**
	 * One sponsor's accounts, a view of the Accounts tab with the sponsor in its address, drawn in
	 * place of the accounts locked today, the invitations card and the list: the sponsor's name, the
	 * way back to the list as it stood, its accounts, each by name and address with Remove, then, for
	 * an Approved sponsor, the form that attaches an existing account by its address and the posting
	 * switch; for a sponsor that is not Approved, one sentence saying neither is offered in their
	 * place.
	 *
	 * Reached from Manage accounts on a row of the list and on the Sponsors card, and from the Sponsor
	 * Dashboard's People card. Every press made on it comes back to it: each form posts the Accounts
	 * tab and the sponsor, which `redirect_back()` reads for Remove and Attach account, and the
	 * switch's handler comes back here by itself (`WPCPM_Sponsor_Posts::handle_flags()`). The record
	 * is the address's, so it is looked up in the sponsors index, with its case, before anything is
	 * drawn for it, and a value the index does not hold, well formed or not, names no sponsor and is
	 * never printed: the view says so and names the remedy, the sponsors sync, on its tab. A press can
	 * still land on such a view, when a sync dropped the record after the view was drawn; its outcome
	 * is printed above the bar like any other.
	 *
	 * The accounts' addresses are printed here, as the Sponsor Dashboard's People card prints them: a
	 * manager attaches and removes accounts by address. The list prints none. A sponsor that is not
	 * Approved keeps the accounts it has until they are removed, so they are listed with Remove; the
	 * form that attaches one and the switch are drawn for an Approved sponsor alone, as the screen has
	 * always drawn them.
	 *
	 * @param string $record The record ID the address names, as given.
	 */
	private function render_sponsor_accounts( $record ) {
		echo '<div class="wpcpm-card">';

		// `row()` asks whether the value is a record ID before it looks, so a value that is none costs
		// no read of the index and is answered as one the index lacks.
		$row = WPCPM_Sponsors_Index::row( $record );

		if ( null === $row ) {
			printf(
				'<p>%1$s %2$s</p>',
				esc_html__( 'No sponsor record has that ID.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: the name of the Sponsors tab, as a link to it. */
					esc_html__( 'If it should be here, run the sponsors sync on the %s tab.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( $this->tab_url( 'sponsors' ) ) . '">' . esc_html( self::tab_labels()['sponsors'] ) . '</a>'
				)
			);
			$this->render_back_to_accounts();
			echo '</div>';

			return;
		}

		$members = WPCPM_Sponsor_Members::members_of( $record );
		$name    = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );

		printf( '<h2>%s</h2>', esc_html( $name ) );
		$this->render_back_to_accounts();

		if ( empty( $members ) ) {
			echo '<p class="description">' . esc_html__( 'No account yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<ul class="wpcpm-list">';

			foreach ( $members as $member ) {
				echo '<li>';
				printf( '%1$s (%2$s) ', esc_html( $member->display_name ), esc_html( $member->user_email ) );
				printf(
					'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s" onsubmit="return confirm(%3$s);">',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr__( 'Removing', 'wpcredits-program-manager' ),
					esc_attr( wp_json_encode( __( 'Remove this account from the sponsor?', 'wpcredits-program-manager' ) ) )
				);
				wp_nonce_field( self::ACTION_MEMBERS );
				printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_MEMBERS ) );
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ARG_SPONSOR ), esc_attr( $record ) );
				echo '<input type="hidden" name="wpcpm_op" value="detach" />';
				printf( '<input type="hidden" name="wpcpm_user" value="%d" />', (int) $member->ID );
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( self::TAB_ACCOUNTS ) );
				printf( '<button type="submit" class="button-link-delete">%s</button>', esc_html__( 'Remove', 'wpcredits-program-manager' ) );
				echo '</form></li>';
			}

			echo '</ul>';
		}

		if ( WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
			echo '<p class="description">' . esc_html__( 'This sponsor is not Approved, so no account can be attached to it and its posting cannot be switched.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Attaching', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_MEMBERS );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_MEMBERS ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ARG_SPONSOR ), esc_attr( $record ) );
		echo '<input type="hidden" name="wpcpm_op" value="attach" />';
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( self::TAB_ACCOUNTS ) );
		printf( '<label class="screen-reader-text" for="wpcpm-attach-%1$s">%2$s</label>', esc_attr( $record ), esc_html__( 'Email address', 'wpcredits-program-manager' ) );
		printf( '<input type="email" id="wpcpm-attach-%1$s" name="wpcpm_email" placeholder="%2$s" required /> ', esc_attr( $record ), esc_attr__( 'name@company.example', 'wpcredits-program-manager' ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Attach account', 'wpcredits-program-manager' ) );
		echo '</form>';

		// The posting flag: on by default, switched here and applied to every live account at once.
		// The nonce is keyed to the record like Create account's. The form posts the tab beside the
		// sponsor as every form here does; its handler builds its own way back, to this view.
		if ( class_exists( 'WPCPM_Sponsor_Posts' ) ) {
			$posting = WPCPM_Sponsor_Posts::posting_enabled( $record );

			printf(
				'<p class="description">%s</p>',
				esc_html(
					$posting
						? sprintf(
							/* translators: %s: how many accounts. */
							_n( 'Posting is on: %s account can write posts in wp-admin; a program manager publishes them.', 'Posting is on: %s accounts can write posts in wp-admin; a program manager publishes them.', count( $members ), 'wpcredits-program-manager' ),
							number_format_i18n( count( $members ) )
						)
						: __( 'Posting is off for this sponsor.', 'wpcredits-program-manager' )
				)
			);
			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Switching', 'wpcredits-program-manager' )
			);
			wp_nonce_field( WPCPM_Sponsor_Posts::ACTION_FLAGS . '_' . $record );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Sponsor_Posts::ACTION_FLAGS ) );
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::ARG_SPONSOR ), esc_attr( $record ) );
			printf( '<input type="hidden" name="wpcpm_on" value="%s" />', esc_attr( $posting ? '0' : '1' ) );
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( self::TAB_ACCOUNTS ) );
			printf( '<button type="submit" class="button">%s</button>', esc_html( $posting ? __( 'Turn posting off', 'wpcredits-program-manager' ) : __( 'Turn posting on', 'wpcredits-program-manager' ) ) );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The way back from one sponsor's accounts to the list as it stood when Manage accounts was
	 * followed: a row's link carried the list's view, search, sort and page (`list_state()`), so they
	 * are this request's, which `list_url()` reads. From the Sponsors card or the People card, and
	 * after a press on the view, whose way back is the view's own address
	 * (`accounts_view_url()`), there is no list to return to, and the way back is the Accounts tab's
	 * first page.
	 */
	private function render_back_to_accounts() {
		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( $this->list_url() ),
			esc_html__( 'Back to the accounts', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The invitations card, on the Accounts tab: every sponsor account never invited.
	 *
	 * Its button posts the tab, so the press comes back to it. Its Stop and Dismiss are the mail
	 * layer's own forms, which name no tab: they come back to the address they were pressed on, this
	 * tab, and Stop's outcome waits on the flash channel this screen reads, which the card names in
	 * its form, so "Sending stopped." prints here. Its button counts accounts, not people, in words
	 * of its own.
	 */
	private function render_invitations() {
		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => self::never_invited(),
				'noun'    => __( 'sponsor accounts', 'wpcredits-program-manager' ),
				/* translators: %s: how many accounts. */
				'button'  => _n_noop( 'Invite %s sponsor account that has never been invited', 'Invite %s sponsor accounts that have never been invited', 'wpcredits-program-manager' ),
				'hidden'  => array( self::TAB_FIELD => self::TAB_ACCOUNTS ),
				'flash'   => $this->flash_key(),
			)
		);
	}

	/**
	 * The Offers and codes tab: every offer with its codes and its claims, and Seed for a sponsor
	 * that has an account and no offer.
	 */
	private function render_tab_offers() {
		$this->render_offers( WPCPM_Sponsors_Index::rows(), self::accounts_by_sponsor() );
	}

	/**
	 * The Interests tab: what sponsors said they would like to support, each under its sponsor's name.
	 */
	private function render_tab_interests() {
		$this->render_interests( WPCPM_Sponsors_Index::rows() );
	}

	/**
	 * The Agreements tab: every Approved sponsor's agreement, and where a document waiting to be read
	 * is listed.
	 */
	private function render_tab_agreements() {
		$this->render_agreements( WPCPM_Sponsors_Index::rows() );
	}

	/**
	 * The sponsor posts waiting for review, oldest first, to be read: each is published or returned
	 * on the Administrator Dashboard, and each row ends with the way to its own item there.
	 *
	 * The rows are the dashboard card's own read, `WPCPM_Administrators_Cards::LIMIT` of them, so no
	 * row links to an item that card does not draw: the read applies its limit before it leaves out
	 * a post whose sponsor stamp is not a record ID, so the first rows of a longer read could differ.
	 * The heading counts from a second read, one more than `COUNT_MAX`, the menu bubble's ceiling,
	 * and the list says so whenever it shows fewer than wait. A row says in one sentence what the
	 * dashboard's card says of the post, and offers Preview, the one way to read it before deciding.
	 */
	private function render_queue_posts() {
		if ( ! class_exists( 'WPCPM_Sponsor_Posts' ) ) {
			return;
		}

		$waiting = count( (array) WPCPM_Sponsor_Posts::pending_all( self::COUNT_MAX + 1 ) );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-posts">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Sponsor posts to review', 'wpcredits-program-manager' ),
			esc_html( self::queue_count( $waiting ) )
		);
		echo '<p class="description">' . esc_html__( 'Posts that sponsors wrote and submitted for review, oldest first. A post is published or returned on the Administrator Dashboard.', 'wpcredits-program-manager' ) . '</p>';

		if ( 0 === $waiting ) {
			echo '<p>' . esc_html__( 'Nothing is waiting.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		$rows = (array) WPCPM_Sponsor_Posts::pending_all( (int) WPCPM_Administrators_Cards::LIMIT );

		self::render_queue_window( count( $rows ), $waiting );

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $row ) {
			echo '<li class="wpcpm-queue-item">';
			printf( '<h3 class="wpcpm-queue-title">%s</h3>', esc_html( (string) $row['title'] ) );
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: the sponsor's name, 2: the name of the account that wrote the post, 3: how long ago, as a human-readable time difference. */
						__( 'From %1$s, by %2$s. Submitted %3$s ago.', 'wpcredits-program-manager' ),
						(string) $row['company'],
						'' !== (string) $row['author'] ? (string) $row['author'] : __( 'somebody whose account is gone', 'wpcredits-program-manager' ),
						human_time_diff( (int) $row['at'], time() )
					)
				)
			);

			if ( '' !== (string) $row['preview'] ) {
				printf(
					'<p><a class="button" href="%1$s">%2$s</a></p>',
					esc_url( (string) $row['preview'] ),
					esc_html__( 'Preview', 'wpcredits-program-manager' )
				);
			}

			WPCPM_Return::render_dashboard_link( 'sponsor-posts', 'wpcpm-sponsor-post-' . (int) $row['id'] );
			echo '</li>';
		}

		echo '</ol>';
		echo '</div>';
	}

	/**
	 * The signed agreements waiting to be read, oldest first: each document's review block as a
	 * reviewer reads it, ending with the way to its own item on the Administrator Dashboard, which
	 * accepts or returns it (`render_agreement_review()`).
	 *
	 * Counted as the posts card is, from one more than `COUNT_MAX`, and drawn no further than the
	 * dashboard's card lists: the first `WPCPM_Administrators_Cards::LIMIT` of one read, which leaves
	 * nothing out after its limit, so they are the card's own. The blocks wear the institution
	 * panel's `wpcpm-review*` classes, which `assets/css/admin.css` already dresses in wp-admin: two
	 * review blocks that look different would be two things to learn for one job.
	 */
	private function render_queue_agreements() {
		if ( ! class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			return;
		}

		$queue   = (array) WPCPM_Sponsor_Agreement::awaiting_review( self::COUNT_MAX + 1 );
		$waiting = count( $queue );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-agreements">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Signed agreements', 'wpcredits-program-manager' ),
			esc_html( self::queue_count( $waiting ) )
		);
		echo '<p class="description">' . esc_html__( 'Signed agreements that companies uploaded and that wait to be read, oldest first. Each is accepted or returned on the Administrator Dashboard.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $queue ) ) {
			echo '<p>' . esc_html__( 'Nothing is waiting to be read.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		$queue = array_slice( $queue, 0, (int) WPCPM_Administrators_Cards::LIMIT );

		self::render_queue_window( count( $queue ), $waiting );

		foreach ( $queue as $post_id ) {
			$this->render_agreement_review( (int) $post_id );
		}

		echo '</div>';
	}

	/**
	 * The line over a list on the queue tab that stops before the queue does, so nobody reads the
	 * rows as the whole of what is waiting; nothing while the list holds all of it.
	 *
	 * @param int $shown   How many the list draws.
	 * @param int $waiting How many are waiting, as the card's count read them.
	 */
	private static function render_queue_window( $shown, $waiting ) {
		if ( (int) $waiting <= (int) $shown ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: how many are drawn, 2: how many are waiting in total, or the most the count reaches with a plus sign. */
					_n(
						'Showing the oldest %1$s of %2$s. As this one is decided on the Administrator Dashboard, the next takes its place.',
						'Showing the oldest %1$s of %2$s. As these are decided on the Administrator Dashboard, the next of them take their place.',
						(int) $shown,
						'wpcredits-program-manager'
					),
					number_format_i18n( (int) $shown ),
					self::queue_count( $waiting )
				)
			)
		);
	}

	/**
	 * A sponsor queue's count as it is printed: the number, or, once the count's read, which asks
	 * for one more than `COUNT_MAX`, returns more, the menu bubble's form, `COUNT_MAX` and a plus
	 * sign. The posts and signed agreements cards on the Waiting for review tab print it in their
	 * headings and window lines, and so do the Administrator Dashboard's tiles and cards for the same
	 * two queues and the Overview's counts of them. The applications are counted in full and print
	 * their number as it stands, on the applications card here and on those pages alike.
	 *
	 * Less the rows a list draws, it is the number a line under that list gives for the rest: past
	 * the ceiling, `COUNT_MAX` less those rows with the plus sign, the least the read vouches for.
	 * The number itself is `queue_number()`'s, which is the one the line's singular or plural
	 * follows, so the words and the grammar cannot part.
	 *
	 * @param int $count How many the count's read returned.
	 * @param int $less  How many of them a list draws, for a line that counts the rest; 0 for the count.
	 * @return string
	 */
	public static function queue_count( $count, $less = 0 ) {
		$number = number_format_i18n( self::queue_number( $count, $less ) );

		if ( (int) $count <= self::COUNT_MAX ) {
			return $number;
		}

		return sprintf(
			/* translators: %s: the largest number the menu bubble counts to, or that number less the rows a list draws. */
			__( '%s+', 'wpcredits-program-manager' ),
			$number
		);
	}

	/**
	 * The number a sponsor queue's count prints, less the rows a list draws: the count's read, or
	 * past `COUNT_MAX` the ceiling, less those rows. `queue_count()` puts it into words.
	 *
	 * @param int $count How many the count's read returned.
	 * @param int $less  How many of them a list draws; 0 for the count itself.
	 * @return int
	 */
	public static function queue_number( $count, $less = 0 ) {
		return min( (int) $count, self::COUNT_MAX ) - (int) $less;
	}

	/**
	 * The documents out of force, oldest first, as the Administrator Dashboard's card reads them:
	 * read once for a draw of the Agreements tab, by the first sponsor out of force it draws, and
	 * forgotten when the tab starts its next draw. Null until read.
	 *
	 * @var int[]|null
	 */
	private $out_of_force = null;

	/**
	 * Where a sponsor's agreement out of force is put back in force: on the Administrator Dashboard
	 * while that page's list of agreements out of force holds its latest revoked document, and here
	 * once the document is past that list.
	 *
	 * The dashboard lists the oldest `WPCPM_Administrators_Cards::LIMIT` documents out of force on the
	 * whole site, and that list does not drain: a document leaves it only when it is reinstated, and
	 * one that is not its sponsor's latest never can be. So the latest can stand past it, on no list
	 * at all; it is reinstated here then, by the form the dashboard's row posts, with the same action,
	 * the same nonce keyed to the document and the dashboard's word on its button, under the sentence
	 * that says why. None of that needs the dashboard's page, so the form stays while the page is
	 * missing. Inside the list the block says where it is done, with the way to the document's own
	 * item there, or the page-missing sentence in the link's place.
	 *
	 * Its place is one read of the documents out of force, oldest first, the one the card makes, up to
	 * `WPCPM_Administrators_Cards::LIMIT`, made once for the whole tab however many sponsors are out of
	 * force: a document found in it is one the list holds, and one not found is past it.
	 *
	 * @param int $post_id The sponsor's latest revoked document.
	 */
	private function render_reinstate_here( $post_id ) {
		$window = (int) WPCPM_Administrators_Cards::LIMIT;

		if ( null === $this->out_of_force ) {
			$this->out_of_force = array_map( 'intval', (array) WPCPM_Sponsor_Agreement::revoked_all( $window ) );
		}

		if ( in_array( (int) $post_id, $this->out_of_force, true ) ) {
			echo '<p>' . esc_html__( 'It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.', 'wpcredits-program-manager' ) . '</p>';

			if ( '' === WPCPM_Administrators_Dashboard::page_url() ) {
				echo '<p class="wpcpm-warning">' . esc_html( WPCPM_Administrators_Dashboard::page_missing() ) . '</p>';
			} else {
				WPCPM_Return::render_dashboard_link( 'sponsor-agreements', 'wpcpm-sponsor-agreement-' . (int) $post_id );
			}

			return;
		}

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: how many agreements out of force the Administrator Dashboard lists. */
					_n(
						'The Administrator Dashboard lists the %s oldest agreement out of force, and this one is past it, so it is reinstated here.',
						'The Administrator Dashboard lists the %s oldest agreements out of force, and this one is past them, so it is reinstated here.',
						$window,
						'wpcredits-program-manager'
					),
					number_format_i18n( $window )
				)
			)
		);

		$this->render_agreement_form(
			WPCPM_Sponsor_Agreement::ACTION_REINSTATE,
			WPCPM_Sponsor_Agreement::ACTION_REINSTATE . '_' . (int) $post_id,
			'wpcpm-review__form',
			__( 'Reinstating', 'wpcredits-program-manager' ),
			array( WPCPM_Sponsor_Agreement::FIELD_POST => (int) $post_id ),
			__( 'Reinstate', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Agreement::reinstate_question(),
			''
		);
	}

	/**
	 * The accounts locked out for the rest of today, if any.
	 *
	 * @param WP_User[] $locked The accounts locked today, as the accounts list read them for its
	 *                          Status cells (`WPCPM_Sponsors_Table::locked_accounts()`).
	 */
	private function render_locked_accounts( array $locked ) {
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
					_n( '%d sponsor account is locked out of changes for the rest of today.', '%d sponsor accounts are locked out of changes for the rest of today.', count( $locked ), 'wpcredits-program-manager' ),
					count( $locked )
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: the accounts, as display name (username), comma-separated. */
					__( 'Each was refused more requests than the daily ceiling allows: %s. The sponsor\'s log has the row, and the lock lifts by itself tomorrow.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				)
			)
		);
	}

	/**
	 * Sync controls and live progress: the institutions panel's markup, which assets/js/admin.js polls.
	 *
	 * @param array $progress From `WPCPM_Sponsors_Sync::progress()`.
	 * @param int   $last     When the last run finished.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Reads the Team Members table, then every sponsor\'s columns, copies each Approved sponsor\'s logo into the Media Library, and reviews the accounts of sponsors that are no longer Approved. It never creates an account.', 'wpcredits-program-manager' ) . '</p>';

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
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-wpcpm-once data-wpcpm-busy="' . esc_attr__( 'Canceling', 'wpcredits-program-manager' ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'sponsors' ) );
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

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-wpcpm-once data-wpcpm-busy="' . esc_attr__( 'Starting', 'wpcredits-program-manager' ) . '">';
			wp_nonce_field( self::ACTION_SYNC );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SYNC ) . '" />';
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'sponsors' ) );
			submit_button( __( 'Sync sponsors now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Every live account, grouped by the sponsor it acts for, one query for the whole render.
	 *
	 * `render_index()` and `render_members()` used to call `WPCPM_Sponsor_Members::members_of()`
	 * once per sponsor row, each its own `get_users()` query; grouping `live_accounts()`'s one
	 * query by `sponsor_of()` here does the same job in one pass over one list.
	 *
	 * @return array<string, WP_User[]> Record ID to its accounts.
	 */
	private static function accounts_by_sponsor() {
		$grouped = array();

		foreach ( WPCPM_Sponsor_Members::live_accounts() as $account ) {
			$record = WPCPM_Sponsor_Members::sponsor_of( $account );

			if ( '' === $record ) {
				continue;
			}

			$grouped[ $record ][] = $account;
		}

		return $grouped;
	}

	/**
	 * Every sponsor, with its status, manager, accounts and logo, and in the last column its Manage
	 * accounts, which opens its accounts on the Accounts tab, where they are attached and removed and
	 * its posting is switched; a screen reader hears the column's heading, Manage accounts, and the
	 * sponsor's name after the link's words. A sponsor's account is created on the Accounts tab's No
	 * account view.
	 *
	 * @param array $rows     The index rows.
	 * @param array $accounts From `accounts_by_sponsor()`.
	 */
	private function render_index( array $rows, array $accounts ) {
		echo '<div class="wpcpm-card">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Sponsors', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $rows ) ) ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No sponsors have been read yet. Run the sync.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';
		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Status', 'wpcredits-program-manager' ), __( 'Product', 'wpcredits-program-manager' ), __( 'Program contact', 'wpcredits-program-manager' ), __( 'Accounts', 'wpcredits-program-manager' ), __( 'Logo', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		// The column of links is named for a screen reader and drawn without a visible heading.
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Manage accounts', 'wpcredits-program-manager' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $record => $row ) {
			$members = isset( $accounts[ $record ] ) ? $accounts[ $record ] : array();
			$manager = WPCPM_Sponsors_Index::manager_of( $record );
			$logo    = WPCPM_Sponsors_Index::logo_record( $record );
			$name    = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
			$link    = self::dashboard_link( $record );

			echo '<tr>';
			echo '<td>' . ( '' !== $link ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html( $name ) ) : esc_html( $name ) ) . '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['product_type'] ) . '</td>';
			echo '<td>' . esc_html( is_array( $manager ) ? $manager['name'] : '' ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( count( $members ) ) ) . '</td>';
			echo '<td>' . esc_html( $logo['colour'] > 0 ? __( 'On the site', 'wpcredits-program-manager' ) : ( empty( $row['logo'] ) ? __( 'None', 'wpcredits-program-manager' ) : __( 'Not copied yet', 'wpcredits-program-manager' ) ) ) . '</td>';
			echo '<td>';
			printf(
				'<a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( self::accounts_view_url( $record ) ),
				esc_html__( 'Manage accounts', 'wpcredits-program-manager' ),
				/* translators: %s: the sponsor's name, read out after "Manage accounts" so each row's link says whose accounts it opens. */
				esc_html( sprintf( __( 'of %s', 'wpcredits-program-manager' ), $name ) )
			);
			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * The last interests sponsors expressed, across every sponsor.
	 *
	 * @param array $rows The index rows, for the names.
	 */
	private function render_interests( array $rows ) {
		$entries = array_merge(
			WPCPM_Institution_Audit::sponsor_entries( 'sponsor_interest', self::INTERESTS_SHOWN ),
			WPCPM_Institution_Audit::sponsor_entries( 'sponsor_interest_mentor', self::INTERESTS_SHOWN )
		);

		usort(
			$entries,
			static function ( $a, $b ) {
				return (int) $b['time'] - (int) $a['time'];
			}
		);

		$entries = array_slice( $entries, 0, self::INTERESTS_SHOWN );

		echo '<div class="wpcpm-card">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Interests', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $entries ) ) ) );
		echo '<p class="description">' . esc_html__( 'What sponsors said they would like to support, from the Sponsor Dashboard. Each was mailed to the assigned program manager when it was sent.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nothing yet.', 'wpcredits-program-manager' ) . '</p></div>';

			return;
		}

		echo '<table class="wpcpm-table widefat striped"><thead><tr>';
		foreach ( array( __( 'When', 'wpcredits-program-manager' ), __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Who', 'wpcredits-program-manager' ), __( 'What', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$sponsor = isset( $rows[ $entry['sponsor'] ] ) ? trim( $rows[ $entry['sponsor'] ]['name'] ) : $entry['sponsor'];
			$actor   = $entry['actor'] > 0 ? get_user_by( 'id', $entry['actor'] ) : false;

			echo '<tr>';
			echo '<td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ) . '</td>';
			echo '<td>' . esc_html( '' === $sponsor ? $entry['sponsor'] : $sponsor ) . '</td>';
			echo '<td>' . esc_html( $actor instanceof WP_User ? $actor->display_name : '' ) . '</td>';
			echo '<td>' . esc_html( $entry['message'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * The agreements card: one block per Approved sponsor, its standing state and what can be done
	 * about it.
	 *
	 * A document a company uploaded is read on the Waiting for review tab and decided on the
	 * Administrator Dashboard, so this card names that tab, linked to the card there, and draws no
	 * review block. What stays here is what the dashboard does not offer, each the one control its
	 * sponsor can take next: On file for a company whose signed copy lives in the program's Drive,
	 * and Take it out of force for one whose agreement is in force. One out of force is reinstated
	 * on the dashboard while that page's list holds its document, and here once the document is past
	 * that list (`render_reinstate_here()`).
	 *
	 * @param array $rows The index rows.
	 */
	private function render_agreements( array $rows ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			return;
		}

		// The documents out of force are read afresh on each draw of the tab, once, by the first
		// sponsor out of force it draws (`render_reinstate_here()`).
		$this->out_of_force = null;

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Agreements', 'wpcredits-program-manager' ) . '</h2>';
		printf(
			'<p class="description">%1$s %2$s</p>',
			esc_html__( 'A sponsor agreement is optional: a company\'s Sponsor Dashboard, offers and codes work without one. This is the state of every Approved sponsor\'s agreement.', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: the name of the Waiting for review tab, as a link to it. */
				esc_html__( 'A document a company uploaded waits on the %s tab.', 'wpcredits-program-manager' ),
				'<a href="' . esc_url( $this->tab_url( 'queue' ) . '#wpcpm-sponsor-agreements' ) . '">' . esc_html( self::tab_labels()['queue'] ) . '</a>'
			)
		);

		foreach ( $rows as $record => $row ) {
			if ( WPCPM_Sponsors_Index::STATUS_APPROVED !== $row['status'] ) {
				continue;
			}

			$this->render_agreement_state( (string) $record, $row );
		}

		echo '</div>';
	}

	/**
	 * The reviewer's block for one document waiting in the queue, to be read.
	 *
	 * The facts, then three things to look at, then the scan and what it is worth, then the download,
	 * then the way to the same document on the Administrator Dashboard, which accepts or returns it:
	 * this screen draws neither decision. The three are read, not ticked, in the list the institution
	 * panel prints for a document of an institution's own: there is no program template to compare a
	 * signed copy against, so the first says to read the whole document, and the other two say whom
	 * it names and what it must not commit the program to.
	 *
	 * @param int $post_id Agreement post ID.
	 */
	private function render_agreement_review( $post_id ) {
		$facts = (array) WPCPM_Sponsor_Agreement::review_facts( $post_id );

		if ( empty( $facts ) ) {
			return;
		}

		printf( '<section class="wpcpm-review" id="wpcpm-review-%d">', (int) $post_id );

		printf(
			'<h3 class="wpcpm-review__title">%s</h3>',
			esc_html(
				sprintf(
					/* translators: %s: company name. */
					__( 'Review the signed agreement from %s', 'wpcredits-program-manager' ),
					(string) $facts['sponsor_name']
				)
			)
		);

		printf(
			'<p class="wpcpm-review__facts">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the uploader's name, 2: date, 3: file size. */
					__( 'Uploaded by %1$s on %2$s, %3$s.', 'wpcredits-program-manager' ),
					'' === $facts['uploaded_by'] ? __( 'somebody at the company', 'wpcredits-program-manager' ) : (string) $facts['uploaded_by'],
					(string) $facts['uploaded_at'],
					size_format( (int) $facts['size'] )
				)
			)
		);

		echo '<ul class="wpcpm-review__checklist">';
		printf( '<li>%s</li>', esc_html__( 'Read the whole document. There is no program template to compare it against.', 'wpcredits-program-manager' ) );
		printf( '<li>%s</li>', esc_html__( 'It names the WordPress Foundation and the company.', 'wpcredits-program-manager' ) );
		printf( '<li>%s</li>', esc_html__( 'It commits the program to nothing a program manager has not agreed to.', 'wpcredits-program-manager' ) );
		echo '</ul>';

		$flags = array_values( array_filter( array_map( 'strval', (array) $facts['flags'] ) ) );

		printf(
			'<p class="wpcpm-review__flags">%s</p>',
			esc_html(
				$flags
					? sprintf(
						/* translators: %s: a comma-separated list of PDF feature names. */
						__( 'The scan noticed these in the file: %s.', 'wpcredits-program-manager' ),
						implode( ', ', $flags )
					)
					: __( 'The scan noticed none of the features it looks for in the file.', 'wpcredits-program-manager' )
			)
		);
		printf(
			'<p class="wpcpm-review__courtesy">%s</p>',
			esc_html__( 'The scan is a courtesy and not evidence: a PDF can carry things a bounded scan will not find. What protects you is that this site never opens the file in your browser, and that the download is handed to a viewer of your own choosing.', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-agreement-panel__download"><a href="%1$s">%2$s</a></p>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD,
							'post'   => (int) $post_id,
						),
						admin_url( 'admin-post.php' )
					),
					WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD . '_' . (int) $post_id
				)
			),
			esc_html__( 'Download the signed agreement', 'wpcredits-program-manager' )
		);

		// The block ends with the way to the same document on the Administrator Dashboard, where both
		// decisions are made, in the line the institution review block ends with; nothing while that
		// page is missing, which the queue tab says once above its cards.
		WPCPM_Return::render_dashboard_link( 'sponsor-agreements', 'wpcpm-sponsor-agreement-' . (int) $post_id, 'wpcpm-review__open' );

		echo '</section>';
	}

	/**
	 * One Approved sponsor's standing agreement state, and the one control it can take next.
	 *
	 * @param string $record Sponsors record ID.
	 * @param array  $row    The index row.
	 */
	private function render_agreement_state( $record, array $row ) {
		$summary = (array) WPCPM_Sponsor_Agreement::summary( $record );
		$state   = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Sponsor_Agreement::SUMMARY_NONE;
		$name    = '' === trim( (string) $row['name'] ) ? $record : trim( (string) $row['name'] );

		echo '<h3>' . esc_html( $name ) . '</h3>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: what this site holds, 2: what Airtable says. */
					__( 'This site: %1$s. Airtable: %2$s.', 'wpcredits-program-manager' ),
					self::agreement_word( $state, (string) $summary['accepted_at'] ),
					'' === trim( (string) $summary['airtable_status'] ) ? __( 'Not started', 'wpcredits-program-manager' ) : (string) $summary['airtable_status']
				)
			)
		);

		if ( WPCPM_Sponsor_Agreement::SUMMARY_ACCEPTED === $state || WPCPM_Sponsor_Agreement::SUMMARY_ON_FILE === $state ) {
			$this->render_agreement_form(
				WPCPM_Sponsor_Agreement::ACTION_REVOKE,
				WPCPM_Sponsor_Agreement::ACTION_REVOKE . '_' . (int) $summary['agreement_id'],
				'wpcpm-review__form',
				__( 'Revoking', 'wpcredits-program-manager' ),
				array( WPCPM_Sponsor_Agreement::FIELD_POST => (int) $summary['agreement_id'] ),
				__( 'Take it out of force', 'wpcredits-program-manager' ),
				'',
				__( 'Why it is out of force, in your own words. This is emailed to everybody at the company exactly as you write it. Nothing about their dashboard changes.', 'wpcredits-program-manager' )
			);

			return;
		}

		if ( WPCPM_Sponsor_Agreement::SUMMARY_REVOKED === $state ) {
			$latest = 0;

			foreach ( WPCPM_Sponsor_Agreement::posts_for( $record ) as $post ) {
				if ( WPCPM_Sponsor_Agreement::STATE_REVOKED === (string) get_post_meta( $post->ID, WPCPM_Sponsor_Agreement::META_STATE, true ) ) {
					$latest = (int) $post->ID;
					break;
				}
			}

			// A reinstatement acts on the latest revoked document: on the Administrator Dashboard
			// while its list of agreements out of force holds it, here past that list.
			if ( $latest > 0 ) {
				$this->render_reinstate_here( $latest );
			}

			return;
		}

		// Nothing accepted: the on-file route, for a company whose signed copy predates this
		// site and lives in the program's Drive.
		$this->render_agreement_form(
			WPCPM_Sponsor_Agreement::ACTION_ON_FILE,
			WPCPM_Sponsor_Agreement::ACTION_ON_FILE . '_' . $record,
			'wpcpm-review__form',
			__( 'Recording', 'wpcredits-program-manager' ),
			array( 'wpcpm_sponsor' => $record ),
			__( 'Record it as on file', 'wpcredits-program-manager' ),
			'',
			'',
			$record
		);
	}

	/**
	 * One of the review card's forms: the nonce, the action, the hidden fields, one control.
	 *
	 * Written once rather than three times, for Take it out of force, Record it as on file and the
	 * reinstatement past the Administrator Dashboard's list (`render_reinstate_here()`), because the
	 * three differ only in their action, their label and whether they carry a note box, the Drive
	 * link or a confirm. Every one is keyed to the object it acts on, and every one carries the
	 * double-submit guard.
	 *
	 * @param string $action  The `admin_post_` action.
	 * @param string $nonce   The nonce action, keyed to the post or the record.
	 * @param string $css     The form's classes.
	 * @param string $busy    What the pressed control says while the request is in flight.
	 * @param array  $hidden  Hidden field name to value.
	 * @param string $label   The button.
	 * @param string $confirm A confirm sentence, or '' for none.
	 * @param string $note    A note box's label, or '' for no note box.
	 * @param string $drive   A record ID when this form asks for a Drive link, else ''.
	 */
	private function render_agreement_form( $action, $nonce, $css, $busy, array $hidden, $label, $confirm, $note, $drive = '' ) {
		printf(
			'<form method="post" action="%1$s" class="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $css ),
			esc_attr( $busy )
		);
		wp_nonce_field( $nonce );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );

		foreach ( $hidden as $name => $value ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		// The mark is built here and passed through kses at each use, so phpcs sees an escaped argument.
		$required = wp_kses( '<span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>', array( 'span' => array( 'class' => array() ) ) );

		if ( '' !== $drive ) {
			printf(
				'<p class="wpcpm-review__note"><label for="wpcpm-drive-%1$s">%2$s %4$s</label> <input type="url" id="wpcpm-drive-%1$s" name="%3$s" placeholder="https://drive.google.com/..." required /></p>',
				esc_attr( $drive ),
				esc_html__( 'The link to the signed copy in the program\'s Drive folder.', 'wpcredits-program-manager' ),
				esc_attr( WPCPM_Sponsor_Agreement::FIELD_DRIVE ),
				wp_kses( $required, array( 'span' => array( 'class' => array() ) ) )
			);

			// Optional: the day the company signed, when the paper copy says so. The handler
			// stores it as the document's signed-on date and leaves it out when blank.
			printf(
				'<p class="wpcpm-review__note"><label for="wpcpm-signed-%1$s">%2$s</label> <input type="date" id="wpcpm-signed-%1$s" name="wpcpm_sponsor_agr_signed_on" /></p>',
				esc_attr( $drive ),
				esc_html__( 'The day it was signed, if the copy says so.', 'wpcredits-program-manager' )
			);
		}

		if ( '' !== $note ) {
			printf(
				'<p class="wpcpm-review__note"><label for="wpcpm-note-%1$s">%2$s %6$s</label> <textarea id="wpcpm-note-%1$s" name="%3$s" rows="4" minlength="%4$d" maxlength="%5$d" required></textarea></p>',
				esc_attr( sanitize_html_class( $nonce ) ),
				esc_html( $note ),
				esc_attr( WPCPM_Sponsor_Agreement::FIELD_NOTE ),
				(int) WPCPM_Sponsor_Agreement::MIN_NOTE,
				(int) WPCPM_Sponsor_Agreement::MAX_NOTE,
				wp_kses( $required, array( 'span' => array( 'class' => array() ) ) )
			);
		}

		if ( '' !== $confirm ) {
			printf(
				'<button type="submit" class="button button-primary" onclick="return confirm(%1$s)">%2$s</button>',
				esc_attr( wp_json_encode( $confirm ) ),
				esc_html( $label )
			);
		} else {
			printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
		}

		echo '</form>';
	}

	/**
	 * One summary state in a manager's words.
	 *
	 * @param string $state    A `SUMMARY_*` value.
	 * @param string $accepted The date it was accepted, or ''.
	 * @return string
	 */
	private static function agreement_word( $state, $accepted ) {
		switch ( $state ) {
			case WPCPM_Sponsor_Agreement::SUMMARY_SUBMITTED:
				return __( 'a document is waiting to be read', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_RETURNED:
				return __( 'the last document was returned', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_REVOKED:
				return __( 'the accepted agreement was taken out of force', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_ACCEPTED:
				return '' === $accepted
					? __( 'an agreement is accepted', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: %s: the date it was accepted. */
						__( 'an agreement is accepted, since %s', 'wpcredits-program-manager' ),
						$accepted
					);
			case WPCPM_Sponsor_Agreement::SUMMARY_ON_FILE:
				return __( 'the program holds a signed copy, recorded by hand', 'wpcredits-program-manager' );
		}

		return __( 'nothing on file', 'wpcredits-program-manager' );
	}

	/**
	 * A sponsor's dashboard, through the switcher, or '' before the front end exists.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	private static function dashboard_link( $record ) {
		if ( ! class_exists( 'WPCPM_Sponsors_Dashboard' ) || ! method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ) {
			return '';
		}

		$page = (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) );

		return '' === $page ? '' : add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $page );
	}

	/**
	 * A login nobody holds yet, from the address's local part.
	 *
	 * @param string $email The address.
	 * @return string
	 */
	private static function unique_login( $email ) {
		$base  = sanitize_user( strtolower( (string) strstr( $email, '@', true ) ), true );
		$base  = '' === $base ? 'sponsor' : $base;
		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			$login = $base . '-' . ( ++$n );
		}

		return $login;
	}
}
