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
 * The companies that fund mentors and offer their tools to students. Since 1.111.0 the site is
 * the record of who a sponsor is: one private post per company, kept by `WPCPM_Sponsors_Index`,
 * added and edited on this screen or made by approving an application (design of 23 September
 * 2026). This class is the wp-admin screen and what a manager does on it: add a sponsor, edit
 * what a manager owns of its record, create the accounts of the Approved sponsors that have none,
 * attach or remove the accounts that act for each, and run the move onto the site again while it
 * is incomplete. An account is only ever made by a manager's press (design spec of 4 September
 * 2026, decision 9). The annual sponsor report's Reports card and its editor (1.115.0) are
 * `WPCPM_Sponsor_Report_Screen`'s.
 *
 * The screen is the tabs of `TABS`, on the accounts screen every audience's module shares
 * (`WPCPM_Accounts_Screen`): its Accounts tab lists the sponsor accounts, invites them and
 * creates the missing ones, and holds one sponsor's accounts as a view of it.
 *
 * It has no sync. It still extends `WPCPM_Sync_Module` for the two things that class still gives
 * it: the way back to the tab a press was made on (`redirect_back()`), and the notice the press
 * leaves there (`render_status_notice()`). `sync_class()` names no class, the sync's outcomes are
 * none of this screen's (`sync_messages()`), and the sync handlers it inherits are hooked by
 * nothing.
 */
class WPCPM_Sponsors extends WPCPM_Sync_Module {

	// The Accounts tab is the accounts screen every audience's module shares: its load hook, its
	// list's form and the presses made in it, a row's invitation and the rows-per-page choice. Two
	// of its methods this class answers itself and runs under other names: the load hook
	// (`load_screen()`), and the words for what a press left (`notice_sentence()`), which ask the
	// report screen's words and the migration's first, then the list's own wording, then the
	// application queue's.
	use WPCPM_Accounts_Screen {
		load_screen as private load_accounts_list;
		notice_sentence as protected list_notice_sentence;
	}

	/**
	 * The screen's tabs, slug => label, in the bar's order: by the job a manager opens the screen
	 * for, the queue first, which is the tab the screen opens on. English, as a constant has to hold
	 * them; `tab_labels()` translates them, and leaves out the last, Handbook listing, while the
	 * tiers switch is off (`listing_shown()`).
	 *
	 * No tab holds a sync, since the sponsors are the site's own records: they are listed and added
	 * on the Sponsors tab.
	 */
	const TABS = array(
		'queue'            => 'Waiting for review',
		'sponsors'         => 'Sponsors',
		self::TAB_ACCOUNTS => 'Accounts',
		'offers'           => 'Offers and codes',
		'interests'        => 'Interests',
		'reports'          => 'Reports',
		'agreements'       => 'Agreements',
		'listing'          => 'Handbook listing',
	);

	/** The name a sponsor's key travels under: the field the forms that act on one sponsor post it in. */
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
	 * A ceiling and not a page size: each account made is invited, given its first offer and marked
	 * on its sponsor's record, and ten of them stay well inside the time one request is given.
	 * Whatever is left is still listed on the No account view afterwards, so pressing it again is
	 * the way through.
	 */
	const PROVISION_LIMIT = 10;

	/** How many sponsors a Create account that could not give some an account names before "and N more". */
	const PROVISION_NAMES = 5;

	/** Create a sponsor's account. The nonce is keyed to the record: `wpcpm_sponsor_provision_<record>`. */
	const ACTION_PROVISION = 'wpcpm_sponsor_provision';

	/** Attach an existing account by address, or detach one; `wpcpm_op` says which. */
	const ACTION_MEMBERS = 'wpcpm_sponsor_members';

	/** Add a sponsor that never applied on the site. One nonce for the form: `wpcpm_sponsor_add`. */
	const ACTION_ADD = 'wpcpm_sponsor_add';

	/** Save a sponsor's Edit form. The nonce is keyed to the sponsor: `wpcpm_sponsor_edit_<key>`. */
	const ACTION_EDIT = 'wpcpm_sponsor_edit';

	/** Run the move onto the site again, from the migration box. */
	const ACTION_MIGRATE = 'wpcpm_sponsors_migrate';

	/** The query argument that draws a sponsor's Edit form on the Sponsors tab: `?edit=<key>`. */
	const ARG_EDIT = 'edit';

	/** The flash channel this screen reads. */
	const FLASH = 'sponsors_admin';

	/** Audit kinds this class writes. */
	const LOG_PROVISIONED = 'provisioned';
	const LOG_ADDED       = 'sponsor_added';
	const LOG_EDITED      = 'sponsor_edited';

	/** The status a sponsor added here starts in, unless the manager chose another (design section 5). */
	const STATUS_ADDED = 'In review';

	/**
	 * What a manager edits of a sponsor's record, in the forms' order (design section 4). The rest
	 * of the row is the sponsor's own or the site's: the free text, the consent, the day it was
	 * created, the agreement, the interests and the account flag. `event_allowance` is posted as a
	 * number and kept with the term's last day (`clean_fields()`).
	 */
	const EDITABLE = array( 'name', 'website', 'status', 'test', 'contact_person', 'contact_email', 'option', 'support', 'product_type', 'manager', 'tier', 'tier_until', 'event_allowance', 'funded_line', 'mentors', 'notes' );

	/** The two lists of `EDITABLE`, posted as ticked boxes. */
	const LISTS = array( 'support', 'mentors' );

	/** The longest name, contact person or funded line the forms take, as typed. */
	const MAX_LINE = 200;

	/** The longest note, as typed. */
	const MAX_NOTES = 4000;

	/**
	 * The typed texts of `EDITABLE`: each with the most characters it holds as the manager typed it
	 * (`WPCPM_Typed_Text::typed_length()`), the status a changed one over that refuses a save with, and
	 * the status a changed one the cleaner would take words from refuses it with
	 * (`WPCPM_Typed_Text::cleaner_loses()`).
	 */
	const TYPED = array(
		'name'           => array( self::MAX_LINE, 'name-too-long', 'name-loss' ),
		'contact_person' => array( self::MAX_LINE, 'contact-too-long', 'contact-loss' ),
		'funded_line'    => array( self::MAX_LINE, 'funded-too-long', 'funded-loss' ),
		'notes'          => array( self::MAX_NOTES, 'notes-too-long', 'notes-loss' ),
	);

	/**
	 * The most extra events the Tier box grants one term. A ceiling rather than a judgment, as the
	 * group sessions' are: with the tier's own, one a month across the term's twelve, and a typo of
	 * a hundred is refused rather than granted.
	 */
	const MAX_EXTRA_EVENTS = 11;

	/**
	 * A ticked value worth looking up: printable, or empty for the one hidden value each list
	 * carries to say it was drawn. `clean_fields()` keeps only the values the form offers.
	 */
	const LIST_VALUE = '/\A[^\x00-\x1f\x7f]{0,200}\z/';

	/** How many interest rows the log card shows. */
	const INTERESTS_SHOWN = 50;

	/** Seed a sponsor's first offer from the index, for the accounts provisioned before offers existed. */
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
	 * Applications waiting, plus agreements awaiting review, plus posts pending, plus, while the
	 * sponsors' events are in use, the events waiting for the program's OK, which the Administrator
	 * Dashboard's strip counts on its Events waiting tile, so the bubble and the strip agree.
	 *
	 * Each behind a guard, because the classes ship in phases and the bubble is drawn on every admin
	 * page: a class missing from a partial deploy must cost a zero, not a fatal on every screen in the
	 * site. Cached for a minute (clean-up 1.98.1): three post queries on every admin page for every
	 * manager was the S5 whole-branch review's finding.
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

		// The offers and the changes to live ones waiting for review (1.123.18), whatever the tiers
		// switch says. The offers class forgets the cache itself at each move of the review.
		if ( class_exists( 'WPCPM_Sponsor_Offer_Review' ) && method_exists( 'WPCPM_Sponsor_Offer_Review', 'waiting_all' ) ) {
			$count += count( (array) WPCPM_Sponsor_Offer_Review::waiting_all( self::COUNT_MAX ) );
		}

		// None while the events are not in use, which the queue's count asks itself.
		if ( class_exists( 'WPCPM_Sponsor_Event_Queue' ) && method_exists( 'WPCPM_Sponsor_Event_Queue', 'count_waiting' ) ) {
			$count += (int) WPCPM_Sponsor_Event_Queue::count_waiting();
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
	 * A post of one of the kinds whose status moves the bubble changed status.
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
	 * An application's, an agreement's or a sponsor event's state meta changed.
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
	 * The post types whose status moves the bubble, of the classes that exist; an event moves it by
	 * its state meta (`attention_meta_keys()`), since its post stays private.
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
	 * The state meta keys whose change moves the bubble. An event's state is written as it is
	 * planned, and again as it is approved, returned or canceled, and the event's post itself stays
	 * private throughout, so its state key, not its status, is what tells the bubble.
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

		if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
			$keys[] = WPCPM_Sponsor_Event::META_STATE;
		}

		return $keys;
	}

	/**
	 * No sync: the module has had none since 1.111.0, when the sponsor record moved onto the site.
	 *
	 * The three sync handlers this class inherits are hooked by nothing, so nothing asks for the
	 * name, and it is empty rather than the name of a class that no longer runs.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return '';
	}

	/**
	 * None of the sync's outcomes: no sync runs on this screen, so neither `started` nor `cancelled`
	 * is anything a press here can leave, and the sync's `error`, which points to a sync's error
	 * printed below the notice, would point at nothing.
	 *
	 * The notice every press leaves is printed from the screen's own map over this one
	 * (`render_status_notice()`), so an outcome left on the channel that the screen does not word
	 * prints nothing; the screen words its own `error` per tab (`outcome_messages()`).
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice type and sentence: none.
	 */
	public static function sync_messages() {
		return array();
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
	 * its audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists, but `not_connected`.
	 * That one is the warning a screen whose accounts arrive by a sync prints while the connection the
	 * sync needs is missing (`render_not_connected()`); this screen prints none, since its sponsors
	 * are the site's own records and its accounts are made on the site, so nothing reads it.
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter sponsor accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Sponsor accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Sponsor accounts list', 'wpcredits-program-manager' ),
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

		// The store's post type, at a priority ahead of anything that could query it on the same
		// request (1.111.0).
		add_action( 'init', array( 'WPCPM_Sponsors_Index', 'register_post_type' ), 5 );

		// The held index becomes posts on the first request after the upgrade, right after the
		// post type exists and before anything reads a sponsor (design decision 7). Once the
		// report says complete, this is one read of an autoloaded option per request.
		add_action( 'init', array( 'WPCPM_Sponsors_Migration', 'maybe_run' ), 6 );

		// A test sponsor is never listed publicly (1.123.18): the sponsors the store held before
		// the flag whose names start with the word "TEST" are marked once, before anything public
		// is drawn.
		add_action( 'init', array( 'WPCPM_Sponsors_Index', 'maybe_mark_tests' ), 7 );

		// A program manager's booking link, a field on their own profile (design decision 5).
		WPCPM_Manager_Booking::init();

		// The public feed the Tracker and the Monthly Report switch to reading, in their own
		// releases (design decision 8).
		WPCPM_Sponsors_Feed::init();

		foreach ( array( 'WPCPM_Sponsors_Dashboard', 'WPCPM_Sponsor_Profile', 'WPCPM_Sponsor_Offers', 'WPCPM_Sponsor_Offer_Review', 'WPCPM_Sponsor_Usage', 'WPCPM_Sponsor_Tools', 'WPCPM_Sponsor_Tier_Card', 'WPCPM_Sponsor_Event', 'WPCPM_Sponsor_Event_Planner', 'WPCPM_Sponsor_Event_Queue', 'WPCPM_Sponsor_Event_List', 'WPCPM_Sponsors_Listing', 'WPCPM_Sponsors_Page', 'WPCPM_Sponsor_Badge', 'WPCPM_Sponsor_Mentors', 'WPCPM_Sponsor_Posts', 'WPCPM_Sponsor_Logo', 'WPCPM_Sponsor_Agreement', 'WPCPM_Sponsor_Application', 'WPCPM_Sponsor_Tiers_Run', 'WPCPM_Sponsor_Report', 'WPCPM_Sponsor_Report_Files', 'WPCPM_Sponsor_Report_Screen' ) as $front ) {
			if ( class_exists( $front ) && method_exists( $front, 'init' ) ) {
				call_user_func( array( $front, 'init' ) );
			}
		}

		// The module's daily jobs, each put back on the clock whenever it is missing: see the method.
		add_action( 'init', array( __CLASS__, 'schedule_cron' ), 20 );

		add_action( 'admin_post_' . self::ACTION_PROVISION, array( $this, 'handle_provision' ) );
		add_action( 'admin_post_' . self::ACTION_MEMBERS, array( $this, 'handle_members' ) );
		add_action( 'admin_post_' . self::ACTION_ADD, array( $this, 'handle_add' ) );
		add_action( 'admin_post_' . self::ACTION_EDIT, array( $this, 'handle_edit' ) );
		add_action( 'admin_post_' . self::ACTION_MIGRATE, array( $this, 'handle_migrate' ) );
		add_action( 'admin_post_' . self::ACTION_SEED, array( $this, 'handle_seed' ) );
		add_action( 'admin_post_' . self::ACTION_CLAIM_VOID, array( $this, 'handle_claim_void' ) );

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
	 * Activation: the migration, the schedule, the pages, and the sponsor events' drain.
	 */
	public function activate() {
		// A site that already holds an index migrates at once: this request's `init`, where
		// boot() hooks the migration, fired before the plugin was activated.
		WPCPM_Sponsors_Migration::maybe_run();

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

		// The Partners page, made where there is none and then put where the tiers' switch says: in
		// draft until the switch publishes it, and published at once on a site activated after the
		// launch. Made first, since set_published() makes no page when it is asked for a draft.
		if ( class_exists( 'WPCPM_Sponsors_Page' ) ) {
			WPCPM_Sponsors_Page::ensure_page();
			WPCPM_Sponsors_Page::set_published( WPCPM_Sponsor_Tiers::live() );
		}

		// The badge's rule. The activation hook flushes the rules once every module has activated,
		// in a request where this plugin's init has never run, so the rule is not in memory to be
		// written; with its version gone, the next request's WPCPM_Sponsor_Badge::maybe_flush()
		// writes the rules again with the rule in them.
		if ( class_exists( 'WPCPM_Sponsor_Badge' ) ) {
			delete_option( WPCPM_Sponsor_Badge::OPT_REWRITE );
		}

		// Deactivation took the drain off the clock with the module's other jobs, and the people a
		// move left still to tell must not wait for the next move to put it back.
		if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
			WPCPM_Sponsor_Event::resume_drain();
		}
	}

	/**
	 * Put the module's three daily jobs on the clock, if they are not there already: the agreement's
	 * retention run, the application queue's, and the tier ladder's daily run.
	 *
	 * **Called from `boot()` on every load, not only from `activate()`.** The activation hook
	 * fires on an explicit activation and on nothing else: not on the files being replaced, and
	 * not on the upgrader's silent reactivation, which is how this site is deployed
	 * (`wp plugin install --force`). Until this method existed the job was scheduled only in
	 * `activate()`, so a site installed or updated through the upgrader would never discard a
	 * withdrawn or returned document's file again, and nothing on any screen would say so. The
	 * other modules' syncs self-heal this way on every boot; this is the same rule for the
	 * retention run. `wp_next_scheduled()` makes it cheap and idempotent: one cache read on a
	 * normal load, one write when the job is missing.
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

		// The tier ladder's daily run (1.112.0), ten hours past the load that first schedules it. When
		// it is scheduled in the same request as the application purge above, as on activation, the
		// extra hour keeps the two jobs apart. Otherwise, as on a site updated in place, where the
		// purge keeps the time it already had, the run simply lands ten hours after the first load
		// that scheduled it.
		if ( class_exists( 'WPCPM_Sponsor_Tiers_Run' ) && ! wp_next_scheduled( WPCPM_Sponsor_Tiers_Run::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( 10 * HOUR_IN_SECONDS ), 'daily', WPCPM_Sponsor_Tiers_Run::CRON_HOOK );
		}
	}

	/**
	 * Deactivation: off the clock.
	 */
	public function deactivate() {
		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD );
		}

		// The application queue's retention run leaves the clock with the plugin, as the discard
		// does: a daily event with no listener is what deactivation would otherwise leave behind.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE );
		}

		// And so does the tier ladder's daily run (1.112.0).
		if ( class_exists( 'WPCPM_Sponsor_Tiers_Run' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Tiers_Run::CRON_HOOK );
		}

		// And the sponsor events' drain (1.117.0), which activation puts back while rows wait for it,
		// and the reminders' retry, whose window the next daily run covers: the edge stays where the
		// run that left an event to the retry began.
		if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Event::CRON_DRAIN );
			wp_clear_scheduled_hook( WPCPM_Sponsor_Event::CRON_RETRY );
		}
	}

	/**
	 * Uninstall: the options this module owns. Accounts, attachments and the audit rows are
	 * not this module's to delete (spec section 11).
	 */
	public function uninstall() {
		// What the old sponsors sync kept, since the sync went in 1.111.0: the move onto the site
		// removes all eight the moment it completes, so these are for a site uninstalled before it
		// did. The names are the migration's, the same two lists its clean-up reads.
		foreach ( WPCPM_Sponsors_Migration::SYNC_OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( WPCPM_Sponsors_Migration::SYNC_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// The minute the migration waits between runs, for a site uninstalled inside it, and the
		// lock of a run that died holding it.
		delete_transient( WPCPM_Sponsors_Migration::TRANSIENT_RUNNING );
		delete_option( WPCPM_Sponsors_Migration::OPT_LOCK );

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

		// The reports, then every lock a request left behind; the store forgets each one's attached
		// file itself as it deletes the post.
		if ( class_exists( 'WPCPM_Sponsor_Report' ) ) {
			WPCPM_Sponsor_Report::delete_all();
		}

		// The events, each with the people on it and its call link, a trashed one too, and every lock
		// a request left behind. The people keep their accounts, as every account stays (spec
		// section 11).
		if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
			WPCPM_Sponsor_Event::delete_all();
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

		// The tier ladder's daily run, off the clock with the module's other two (1.112.0).
		if ( class_exists( 'WPCPM_Sponsor_Tiers_Run' ) ) {
			wp_clear_scheduled_hook( WPCPM_Sponsor_Tiers_Run::CRON_HOOK );
		}

		if ( class_exists( 'WPCPM_Sponsor_Approval' ) ) {
			WPCPM_Sponsor_Approval::delete_all();
		}

		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
			delete_option( WPCPM_Sponsors_Dashboard::OPT_PAGE );
			delete_option( WPCPM_Sponsors_Dashboard::OPT_TITLE_FIXED );
		}

		if ( class_exists( 'WPCPM_Sponsors_Page' ) ) {
			delete_option( WPCPM_Sponsors_Page::OPT_PAGE );
		}

		// The rewrite version the badge's rule was flushed for; the rule itself leaves the stored
		// rules at the next flush, once nothing adds it.
		if ( class_exists( 'WPCPM_Sponsor_Badge' ) ) {
			delete_option( WPCPM_Sponsor_Badge::OPT_REWRITE );
		}

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

		// A program manager's booking link (1.111.0) is the plugin's row on an account that is
		// not the plugin's, the same bargain as the stamps above.
		if ( class_exists( 'WPCPM_Manager_Booking' ) ) {
			delete_metadata( 'user', 0, WPCPM_Manager_Booking::META, '', true );
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
			'provision-no-email' => array( 'error', __( 'That sponsor has no contact address. Add one on its Edit form, then try again.', 'wpcredits-program-manager' ) ),
			'provision-refused'  => array( 'error', __( 'That account cannot act for this sponsor.', 'wpcredits-program-manager' ) ),
			'provision-failed'   => array( 'error', __( 'The account could not be created.', 'wpcredits-program-manager' ) ),
			'record-unmarked'    => array( 'warning', __( 'The account acts for the sponsor, and its invitation is queued unless it already had one. The sponsor\'s record could not be found, so it is not marked as having an account. If the Sponsors tab lists the sponsor, saving it on its Edit form marks it.', 'wpcredits-program-manager' ) ),
			'attached'           => array( 'success', __( 'The account now acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'attach-no-account'  => array( 'error', __( 'No account has that address. Attach account attaches an account that exists; a sponsor\'s first account is made with Create account on the No account view.', 'wpcredits-program-manager' ) ),
			'attach-refused'     => array( 'error', __( 'That account cannot act for this sponsor.', 'wpcredits-program-manager' ) ),
			'detached'           => array( 'success', __( 'The account no longer acts for the sponsor.', 'wpcredits-program-manager' ) ),
			'detach-refused'     => array( 'error', __( 'That account could not be detached.', 'wpcredits-program-manager' ) ),
			'refused'            => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
			'posting-on'         => array( 'success', __( 'Posting is on for this sponsor: its accounts can write posts and submit them for review.', 'wpcredits-program-manager' ) ),
			'posting-off'        => array( 'success', __( 'Posting is off for this sponsor: its accounts can no longer write posts.', 'wpcredits-program-manager' ) ),
			'offer-seeded'       => array( 'success', __( 'The first offer was created in draft; the sponsor completes it and switches it on from the Sponsor Dashboard.', 'wpcredits-program-manager' ) ),
			'offer-seed-none'    => array( 'info', __( 'That sponsor already has an offer; nothing was seeded.', 'wpcredits-program-manager' ) ),
			'offer-seed-failed'  => array( 'error', __( 'The offer could not be seeded: the index does not hold that sponsor.', 'wpcredits-program-manager' ) ),
			'claim-voided'       => array( 'success', __( 'The claim was voided. The person may claim again; the code stays void for the count.', 'wpcredits-program-manager' ) ),
			'claim-void-none'    => array( 'info', __( 'That person holds no claim on that offer.', 'wpcredits-program-manager' ) ),
			'claim-void-busy'    => array( 'info', __( 'Another change to that offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'sponsor-added'      => array( 'success', __( 'The sponsor was added. Nothing else happens until its status is Approved and its account is created.', 'wpcredits-program-manager' ) ),
			'sponsor-saved'      => array( 'success', __( 'The sponsor was saved.', 'wpcredits-program-manager' ) ),
			'sponsor-unchanged'  => array( 'info', __( 'Nothing changed, so nothing was saved.', 'wpcredits-program-manager' ) ),
			'sponsor-rejected'   => array(
				'error',
				sprintf(
					/* translators: %d: the most extra events the Tier box grants one term. */
					__( 'Nothing was saved: one of the values could not be accepted. A sponsor needs a name; the website has to be a web address, the contact email an email address, Extra events this term a whole number from 0 to %d, and every choice one this form offers.', 'wpcredits-program-manager' ),
					self::MAX_EXTRA_EVENTS
				),
			),
			// A typed text over its limit is named, with the limit (`long_sentence()`).
			'name-too-long'      => array( 'error', self::long_sentence( __( 'Name', 'wpcredits-program-manager' ), self::MAX_LINE ) ),
			'contact-too-long'   => array( 'error', self::long_sentence( __( 'Contact person', 'wpcredits-program-manager' ), self::MAX_LINE ) ),
			'funded-too-long'    => array( 'error', self::long_sentence( __( 'What the sponsorship funds', 'wpcredits-program-manager' ), self::MAX_LINE ) ),
			'notes-too-long'     => array( 'error', self::long_sentence( __( 'Notes', 'wpcredits-program-manager' ), self::MAX_NOTES ) ),
			// A typed text the cleaner would take words from is named, with how to keep every word: the
			// sentence every box of typed text refuses a loss with (`WPCPM_Typed_Text::loss_message()`).
			'name-loss'          => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Name', 'wpcredits-program-manager' ) ) ),
			'contact-loss'       => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Contact person', 'wpcredits-program-manager' ) ) ),
			'funded-loss'        => array( 'error', WPCPM_Typed_Text::loss_message( __( 'What the sponsorship funds', 'wpcredits-program-manager' ) ) ),
			'notes-loss'         => array( 'error', WPCPM_Typed_Text::loss_message( __( 'Notes', 'wpcredits-program-manager' ) ) ),
			'sponsor-detached'   => array( 'warning', __( 'Saved. The sponsor is no longer Approved, so its accounts were detached, as the setting says.', 'wpcredits-program-manager' ) ),
			'sponsor-not-saved'  => array( 'error', __( 'Nothing was saved. The site could not save the sponsor just now. Try again in a moment.', 'wpcredits-program-manager' ) ),
			// The counts are the report's, read when the notice is printed: see notice_sentence().
			'migrated'           => array( 'info', __( 'The move onto the site ran again.', 'wpcredits-program-manager' ) ),
			'migrate-busy'       => array( 'info', __( 'The move onto the site is already running. Give it a minute, then look at this box again.', 'wpcredits-program-manager' ) ),
		);

		// The agreement's own outcomes, so a review pressed on this screen has words to print.
		if ( class_exists( 'WPCPM_Sponsor_Agreement' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Agreement::manager_messages() );
		}

		// The application queue's own outcomes, so a decision pressed on this screen has words.
		if ( class_exists( 'WPCPM_Sponsor_Application' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Application::manager_messages() );
		}

		// A sponsor report's PDF outcomes, so an attach or a removal pressed on a report has words.
		if ( class_exists( 'WPCPM_Sponsor_Report_Files' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Report_Files::manager_messages() );
		}

		// And the report's own, so a draft, a save, a state move or Not a renewal has words too.
		if ( class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			$messages = array_merge( $messages, WPCPM_Sponsor_Report_Screen::messages() );
		}

		return $messages;
	}

	/**
	 * The sentence a save refused for one typed text's length prints: the box, by its label on the
	 * form, and the most characters it holds, as the box counts them.
	 *
	 * The one builder of that sentence. `messages()` asks it for the Edit form's four boxes, and the
	 * sponsor report's editor asks it for the narrative a Save refused, with the label of the box
	 * (`WPCPM_Sponsor_Report_Screen::sentence_for()`), so the two print one wording.
	 *
	 * @param string $label The box's label.
	 * @param int    $limit The most characters it holds, as typed.
	 * @return string
	 */
	public static function long_sentence( $label, $limit ) {
		return sprintf(
			/* translators: 1: a box's label on the form it was typed in, 2: the most characters it holds. */
			_n( 'Nothing was saved: "%1$s" is longer than %2$s character. Shorten it and save again.', 'Nothing was saved: "%1$s" is longer than %2$s characters. Shorten it and save again.', $limit, 'wpcredits-program-manager' ),
			$label,
			number_format_i18n( $limit )
		);
	}

	/**
	 * The sentence one status prints, worded from what the press carried beside it: Not a
	 * renewal's `sreport-owed-cleared`, which names the last day of the term it cleared (the report
	 * screen's own reading, `WPCPM_Sponsor_Report_Screen::sentence_for()`); the accounts list's
	 * words for a press made in it, a row's Create account among them (the accounts screen's own
	 * reading, kept under another name: `list_notice_sentence()`); and the queue's `sapp-account`
	 * sentence, which names what the site said when the account step failed. Run again's
	 * `migrated` carries nothing beside it, and is worded with the counts of the run it made, from
	 * the migration's report.
	 *
	 * Each detail is one-shot, so it is resolved here, on the one status being printed, and
	 * never inside `messages()`: this map is built by anything that wants a sentence out of it,
	 * `WPCPM_Sponsor_Approval::status_sentence()` among them, in the middle of the POST that
	 * set the detail. The three travel on channels of their own, and each is taken
	 * whichever status is printed, so none waits to surface under a later press; where the list
	 * words the status, its words stand, since the list and the queue never word the same one.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from `messages()`.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		// First, whatever the status, so the day a Not a renewal carried is taken with its notice.
		if ( class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			$sentence = WPCPM_Sponsor_Report_Screen::sentence_for( $status, $sentence );
		}

		// Run again's outcome, with the counts of the run it just made: the flash carries a status
		// alone, and the report is where the run left its numbers. Kept rather than returned, so the
		// list's and the queue's details below are taken on this press too.
		if ( 'migrated' === $status ) {
			$report = WPCPM_Sponsors_Migration::report();
			$counts = sprintf(
				/* translators: 1: how many sponsors are posts on the site, 2: how many rows the old index held. */
				_n(
					'The move onto the site ran again: %1$s of %2$s row is a post.',
					'The move onto the site ran again: %1$s of %2$s rows are posts.',
					$report['rows'],
					'wpcredits-program-manager'
				),
				number_format_i18n( $report['posts'] ),
				number_format_i18n( $report['rows'] )
			);

			$sentence = $report['complete'] ? $counts . ' ' . __( 'Every sponsor is on the site now.', 'wpcredits-program-manager' ) : $counts;
		}

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
	 * `sanitize_key()` lowercases, and a sponsor's key is case-sensitive. It is read before the nonce
	 * is checked only because the nonce is keyed to it; nothing is done with it until both checks
	 * have passed.
	 *
	 * The press comes back to the list as the link says it stood, its view, search, sort and page
	 * (`list_url()`), as a row's invitation comes back; a posted form carries no list, and comes
	 * back to the Accounts tab itself. What provisioning said beside a refusal or a failure, why the
	 * account at the address cannot act for the sponsor or what WordPress said of an account it
	 * would not make, travels with the outcome as its reason, and the Sponsors table words it
	 * (`WPCPM_Sponsors_Table::action_sentence()`). An account made or attached whose sponsor's record
	 * could not then be marked, `record-unmarked`, carries nothing beside it: the screen's own
	 * sentence says the account is in place and how the record is marked (`messages()`).
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
	 * @param string $record   Sponsor key.
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

		$email = self::contact_address( $row );

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
			// module's file for a sponsor-prefixed string beside that operator, to keep sponsor-key
			// comparisons behind the policy's own helper alone, and a bare one here would read
			// as exactly that to a scan that cannot see this is an error code, not a sponsor key.
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
	 * follows on this screen: the invitation, the first offer, the account flag, the log.
	 *
	 * The account made or attached acts for the sponsor whether or not its flag can then be set:
	 * when the sponsor's record cannot be marked as having an account (`mark_dashboard_account()`
	 * answers false: the store no longer holds the record the press read, as after a stray trash),
	 * the answer is `record-unmarked`, and `created` says whether the account was made or attached,
	 * as the status itself says it for `provisioned` and `provision-attached`, so a caller counts it
	 * as either.
	 *
	 * @param string $record   Sponsor key.
	 * @param int    $actor_id The manager.
	 * @return array `status` (a key of `messages()`), `user_id`, `created`, `detail`.
	 */
	public static function provision( $record, $actor_id ) {
		$account = self::provision_account( $record, $actor_id );

		// A refusal, or an account this sponsor already had: nothing follows either.
		if ( ! in_array( $account['status'], array( 'provisioned', 'provision-attached' ), true ) || ! empty( $account['already'] ) ) {
			return array(
				'status'  => $account['status'],
				'user_id' => (int) $account['user_id'],
				'created' => ! empty( $account['created'] ),
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
					'user'    => $user_id,
					'created' => $created,
				),
			)
		);

		if ( ! $marked ) {
			return array(
				'status'  => 'record-unmarked',
				'user_id' => $user_id,
				'created' => $created,
				'detail'  => '',
			);
		}

		return array(
			'status'  => $created ? 'provisioned' : 'provision-attached',
			'user_id' => $user_id,
			'created' => $created,
			'detail'  => '',
		);
	}

	/**
	 * A sponsor's contact address as provisioning reads it from the row: every entity the row holds
	 * read back, named or numbered, as a browser reads the Edit form's Contact email box and as
	 * `WPCPM_Typed_Text::same_text()` reads it (`html_entity_decode()`), then `sanitize_email()`.
	 *
	 * A row can hold its address with an entity, `&amp;` for the "&" of the address or `&#43;` for
	 * its "+", as text written by other means is stored, and `sanitize_email()` drops the ";" of such
	 * an entity and keeps the rest, which is another address: the account would be made there, or the
	 * account somebody else holds there attached. Read back first, it is the address the row means,
	 * the one the Edit form's Contact email box shows (`render_fields()`). Create account
	 * (`provision_account()`) and the No account view (`provision_worklist()`) read it here alike; the
	 * row keeps it as stored.
	 *
	 * An address `sanitize_email()` would have to change is not the one the row means either: read back,
	 * `sam&#59;co@example.org` is "sam;co@example.org", and what `sanitize_email()` leaves of it,
	 * "samco@example.org", is another address, perhaps somebody's. Nothing is made of it: the address
	 * reads as none, which Create account and the No account view already answer.
	 *
	 * @param array $row The sponsor's index row.
	 * @return string The address, or '' when nothing an address can be made of is left.
	 */
	private static function contact_address( array $row ) {
		$stored = isset( $row['contact_email'] ) && is_scalar( $row['contact_email'] ) ? trim( (string) $row['contact_email'] ) : '';

		$decoded = html_entity_decode( $stored, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$email   = sanitize_email( $decoded );

		return $email === $decoded ? $email : '';
	}

	/**
	 * The Approved sponsors that have no account yet, as the Accounts tab's No account view lists
	 * them: the ready ones first, then by name.
	 *
	 * Every index row whose status is Approved and that no live account acts for (the accounts
	 * `accounts_by_sponsor()` groups, one query for them all), each with its key as `id`; its
	 * name from the index, trimmed, or the key where the index has none, as `name`; its contact
	 * address masked, never whole, or '' when it has none `is_email()` accepts, as `contact`; whether
	 * an account already uses that address, which a Create account attaches rather than duplicates,
	 * as `existing`; why it may not be provisioned, as `reason`: '' when it may, `no-email` with no
	 * address, `admin` when the address is an administrator's, which provisioning refuses
	 * (`provision_account()`); and whether it may, as `ready`. That is what can be known before a
	 * press: an account at the address that is a student's, an institution member's or another
	 * sponsor's is refused by the members class only when the press attaches it, and the press says
	 * why (`provision_ticked()`). In the order a worklist reads from the top: what can be done now
	 * first, then the rest by name, read as a reader expects names, without regard to case or
	 * accents, the key settling two of one name.
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
			$email    = self::contact_address( $row );
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
	 * account that already used the address, attached, `provision-attached`, under `attached`. An
	 * account made or attached whose sponsor's record could not then be found to mark as having an
	 * account, `record-unmarked`, is counted under `created` or `attached`, by what provisioning says
	 * it did, and under `unmarked` too: the sponsor has its account, and its record waits for its
	 * mark. Those sponsors are named under `unmarked_names`, the first `PROVISION_NAMES` in the order
	 * pressed, and how many more as `unmarked_more`, so the words say which records to look for.
	 * Anything else is a sponsor not given an account, named under `failed` with what provisioning
	 * said, or the sentence this screen keeps for its outcome where it said nothing (`messages()`): the
	 * first `PROVISION_NAMES` named, and how many more as `failed_more`.
	 *
	 * `provision-none`, with no detail, when no ticked sponsor was ready; `provision-failed` when any
	 * was not given an account; `provisioned` otherwise. The detail holds `created`, `attached`,
	 * `unmarked`, `left` and `limit`, the ceiling; `unmarked_names` and `unmarked_more` too when a
	 * record could not be found, and `failed` and `failed_more` when a sponsor was not given an
	 * account. The words for it are the table's (`WPCPM_Sponsors_Table::action_sentence()`).
	 *
	 * Checks neither the capability nor a nonce: the accounts screen checks both before the table
	 * hands the ticked records over (`WPCPM_Accounts_Screen::handle_list_form()`), and the records are
	 * matched against the view the site holds.
	 *
	 * @param string[] $records The ticked sponsors' keys.
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
		$unmarked = array();
		$detail   = array(
			'created'  => 0,
			'attached' => 0,
			'unmarked' => 0,
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
			} elseif ( 'record-unmarked' === $status ) {
				++$detail['unmarked'];
				++$detail[ empty( $result['created'] ) ? 'attached' : 'created' ];
				$unmarked[] = (string) $row['name'];
			} else {
				$said     = '' !== (string) $result['detail'] ? (string) $result['detail'] : ( isset( $messages[ $status ][1] ) ? (string) $messages[ $status ][1] : '' );
				$failed[] = self::failed_named( $row['name'], $said );
			}
		}

		if ( ! empty( $unmarked ) ) {
			$detail += array(
				'unmarked_names' => array_slice( $unmarked, 0, self::PROVISION_NAMES ),
				'unmarked_more'  => max( 0, count( $unmarked ) - self::PROVISION_NAMES ),
			);
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
	 *
	 * A last Remove clears the record's account flag (`mark_dashboard_account()`) and answers
	 * `detached` whatever the clear answers, since the account no longer acts for the sponsor. When
	 * the store no longer holds the record the clear fails, and the flag then stays true on a record
	 * that is out of the store; the one place that flag shows is the Administrator Dashboard's figure
	 * of the sponsors with an account.
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

			$this->redirect_back( self::mark_dashboard_account( $record, true ) ? 'attached' : 'record-unmarked' );
		}

		$user_id  = WPCPM_Request::posted_id( 'wpcpm_user' );
		$detached = WPCPM_Sponsor_Members::is_member( $user_id, $record )
			? WPCPM_Sponsor_Members::detach( $user_id, WPCPM_Sponsor_Members::REASON_REMOVED, get_current_user_id() )
			: WPCPM_Sponsor_Policy::refusal();

		if ( is_wp_error( $detached ) ) {
			$this->redirect_back( 'detach-refused' );
		}

		// The account is removed by now, so the press says so whatever the clear answers.
		if ( empty( WPCPM_Sponsor_Members::members_of( $record ) ) ) {
			self::mark_dashboard_account( $record, false );
		}

		$this->redirect_back( 'detached' );
	}

	/**
	 * Add a sponsor that never applied on the site: what a manager owns of the record, cleaned by
	 * `clean_fields()`, under a key the site mints, and In review unless the manager chose another
	 * status. Nothing else happens: the account comes from Create account once the sponsor is
	 * Approved, as it does for any sponsor (design section 5).
	 *
	 * A refusal comes back to the tab the form names, the Sponsors tab (`redirect_back()`), with the
	 * sentence that names a typed text over its limit, or the one that lists the rules
	 * (`refusal()`); the sponsor added opens on its Edit form, which that tab draws in place of its
	 * cards (`redirect_to_edit()`).
	 *
	 * The typed texts are the Edit form's (`handle_edit()`): before any field is cleaned, the first in
	 * the form's order that the cleaner would take words from refuses the Add with the sentence that
	 * names it (`TYPED`). The form is drawn empty, so no box comes back as drawn holding what the
	 * cleaner would take. Every refusal keeps the whole form as it was posted, for the one time the
	 * Sponsors tab draws it again (`keep_typing()`), under the Add form's own name.
	 */
	public function handle_add() {
		$this->verify( self::ACTION_ADD );

		// No mentors: the picker is the Edit form's, once the sponsor exists. No Tier box either:
		// it is Edit's alone (design section 4), its extra events with it.
		$fields   = array_diff( self::EDITABLE, array( 'mentors', 'tier', 'tier_until', 'event_allowance', 'funded_line' ) );
		$defaults = array( 'status' => self::STATUS_ADDED );
		$form     = self::typed_form( '' );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = self::typed_post( $fields );
		$lost  = self::lost_box( $typed, array() );

		if ( '' !== $lost ) {
			self::keep_typing( $form, $typed, $defaults );
			$this->redirect_back( self::TYPED[ $lost ][2] );
		}

		$clean = self::clean_fields( self::posted_fields( $fields ), $defaults );

		if ( ! $clean['ok'] ) {
			self::keep_typing( $form, $typed, $defaults );
			$this->redirect_back( self::refusal( $clean ) );
		}

		$key = WPCPM_Sponsors_Index::mint_key();
		$row = array_merge(
			$clean['row'],
			array(
				'record_id'         => $key,
				'slug'              => '',
				'created'           => wp_date( 'Y-m-d' ),
				'consent'           => false,
				'dashboard_account' => false,
			)
		);

		if ( ! WPCPM_Sponsors_Index::insert( $row ) ) {
			$this->redirect_back( 'sponsor-not-saved' );
		}

		$filled = self::filled( $row );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_ADDED,
				'sponsor'  => $key,
				'subject'  => $key,
				'actor'    => get_current_user_id(),
				'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: %s: the fields set, comma-separated. */
					__( 'A sponsor was added on the Sponsors screen, with: %s.', 'wpcredits-program-manager' ),
					implode( ', ', $filled )
				),
				// The field names, never the values: a contact's address is the sponsor's data, and
				// the log is not the place to copy it.
				'data'     => array( 'fields' => $filled ),
			)
		);

		$this->redirect_to_edit( 'sponsor-added', $key );
	}

	/**
	 * Save a sponsor's Edit form: the fields it was given, cleaned by `clean_fields()`, patched
	 * onto the record and logged by name.
	 *
	 * The account flag is the site's own fact, which the form does not post: a save marks the record
	 * of a sponsor an account acts for when the record says it has none, the mark a Create account or
	 * an Attach account could not save (`record-unmarked`), on a save that changes no field too, which
	 * is then saved and logs nothing, since no field a manager owns moved. A record that says it has
	 * an account is left as it is: the last account's removal clears the flag where it happens.
	 *
	 * A status set away from Approved applies the setting "When a sponsor is no longer Approved"
	 * here, where the status now changes: with `revoke`, every account that acts
	 * for the sponsor is detached and the account flag cleared. A new name renames the sponsor's
	 * category as well, and keeps its slug.
	 *
	 * A save that moves the last day of a term is a renewal, which the thirty-day mail asks for, and
	 * often comes before the term ends: the term it moves off keeps its annual report, recorded on
	 * the row as `report_owed` when its tier carries one and none exists yet
	 * (`WPCPM_Sponsor_Report::owed_after_save()`, which also keeps an owed term still without its
	 * report over a second save). The log names the fields the manager changed, not the owed term.
	 *
	 * Every outcome comes back to the sponsor's Edit form (`redirect_to_edit()`), but a save for a key
	 * the store does not hold, which has no form to come back to: it comes back to the tab the form
	 * names, the Sponsors tab (`redirect_back()`).
	 *
	 * Before any field is cleaned, each of the typed texts of `TYPED` that the manager changed is asked
	 * whether the cleaner would take words from what was typed (`WPCPM_Typed_Text::cleaner_loses()`):
	 * "Kids <12 free, adults >18 pay" would be stored as "Kids 18 pay". The first such box, in the
	 * form's order, refuses the whole save, by the one-save rule of `clean_fields()`, with the sentence
	 * that names it (`TYPED`). A box posted back as it was drawn is the stored text and is not asked:
	 * the row can hold what the cleaner would take, such as a percent octet brought over from the
	 * program records, and only what a manager changed is theirs to be told about. It is not cleaned
	 * and written back either, which would lose those words without a word said, so it keeps its
	 * stored text whatever its length.
	 *
	 * A save refused for a loss, for a typed text's length or for any other value keeps the whole form
	 * as it was posted, for the one time the form is drawn again (`keep_typing()`): one value refuses
	 * the whole save, and the manager makes the change the refusal names and loses none of the others.
	 */
	public function handle_edit() {
		$key = WPCPM_Request::posted_text( 'wpcpm_sponsor' );

		$this->verify( self::ACTION_EDIT . '_' . $key );

		$current = WPCPM_Sponsors_Index::row( $key );

		if ( ! is_array( $current ) ) {
			$this->redirect_back( 'refused' );
		}

		$form = self::typed_form( $key );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = self::typed_post( self::EDITABLE );
		$drawn = self::drawn_typed( $typed, $current );
		$lost  = self::lost_box( $typed, $drawn );

		if ( '' !== $lost ) {
			self::keep_typing( $form, $typed, $current );
			$this->redirect_to_edit( self::TYPED[ $lost ][2], $key );
		}

		// A box posted back as drawn is left out, and so keeps its stored text (see `clean_fields()`).
		$clean = self::clean_fields( array_diff_key( self::posted_fields( self::EDITABLE ), array_flip( $drawn ) ), $current );

		if ( ! $clean['ok'] ) {
			self::keep_typing( $form, $typed, $current );
			$this->redirect_to_edit( self::refusal( $clean ), $key );
		}

		// Asked only of a record that says it has no account: the one that can be waiting for its mark.
		$unmarked = empty( $current['dashboard_account'] ) && ! empty( WPCPM_Sponsor_Members::members_of( $key ) );

		if ( empty( $clean['changed'] ) && ! $unmarked ) {
			$this->redirect_to_edit( 'sponsor-unchanged', $key );
		}

		$fields = array_intersect_key( $clean['row'], array_flip( $clean['changed'] ) );

		if ( $unmarked ) {
			$fields['dashboard_account'] = true;
		}

		// Guarded like the module's other classes: the screen saves with or without the report's store.
		if ( class_exists( 'WPCPM_Sponsor_Report' ) ) {
			$owed = WPCPM_Sponsor_Report::owed_after_save( $key, $current, $clean['row'] );

			if ( ! empty( $owed ) ) {
				$fields['report_owed'] = $owed;
			}
		}

		if ( ! WPCPM_Sponsors_Index::patch( $key, $fields ) ) {
			$this->redirect_to_edit( 'sponsor-not-saved', $key );
		}

		// The sponsor's category carries its name, and this form is the one place a sponsor is
		// renamed since the sync that used to rename the term went (1.111.0); the slug stays.
		if ( in_array( 'name', $clean['changed'], true ) && class_exists( 'WPCPM_Sponsor_Posts' ) ) {
			WPCPM_Sponsor_Posts::rename_terms( array( $key => $current ), array( $key => $clean['row'] ) );
		}

		$actor = get_current_user_id();

		// The trail names the fields a manager changed. A save that only marked the record changed
		// none, and the account it marks was logged when it was made or attached.
		if ( ! empty( $clean['changed'] ) ) {
			WPCPM_Institution_Audit::record_sponsor(
				array(
					'kind'     => self::LOG_EDITED,
					'sponsor'  => $key,
					'subject'  => $key,
					'actor'    => $actor,
					'ground'   => WPCPM_Institution_Audit::GROUND_MANAGER,
					'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
					'message'  => sprintf(
						/* translators: %s: the fields changed, comma-separated. */
						__( 'Sponsor fields changed on the Sponsors screen: %s.', 'wpcredits-program-manager' ),
						implode( ', ', $clean['changed'] )
					),
					// The field names, never the values, as the profile card logs its own saves.
					'data'     => array( 'fields' => $clean['changed'] ),
				)
			);
		}

		$status  = 'sponsor-saved';
		$leaving = WPCPM_Sponsors_Index::STATUS_APPROVED === $current['status'] && WPCPM_Sponsors_Index::STATUS_APPROVED !== $clean['row']['status'];

		// The accounts go with the status only when the setting says so; keep is its default,
		// because a pause is often short. The notice says accounts were detached only when one was.
		if ( $leaving && 'revoke' === WPCPM_Settings::get_value( 'sponsor_on_inactive', 'keep' ) ) {
			$status = self::revoke_accounts( $key, $actor ) > 0 ? 'sponsor-detached' : $status;
		}

		$this->redirect_to_edit( $status, $key );
	}

	/**
	 * Run the move onto the site again, from the migration box: the run the first request after an
	 * upgrade makes, pressed by hand.
	 *
	 * Through the migration's lock like every run: a press while another
	 * run holds it moves nothing, and the notice says the move is already running rather than
	 * claiming a run that did not happen. Either way the press comes back to the tab the form names,
	 * the Sponsors tab (`redirect_back()`), which draws the box while the move is still incomplete;
	 * once a run has completed it the box is gone, and the tab draws the Sponsors card alone under
	 * the notice.
	 */
	public function handle_migrate() {
		$this->verify( self::ACTION_MIGRATE );

		$report = WPCPM_Sponsors_Migration::run();

		$this->redirect_back( null === $report ? 'migrate-busy' : 'migrated' );
	}

	/**
	 * The one set of rules Add and Edit share: every field a manager owns, cleaned against the
	 * record it would change.
	 *
	 * Only the fields in `$posted` are read, and one it does not carry keeps its value, so a form
	 * changes the fields it is given and nothing else. A value identical to the one on the record
	 * is kept as it is, even where the rule for its field would not produce it: two sponsors came
	 * onto the site with a blank status and a linked mentor can leave the mentors index, and a save
	 * must neither refuse nor quietly rewrite what nobody touched. Anything new must pass its rule,
	 * and one value that does not refuses the whole save, as on the profile card (design spec of
	 * 4 September 2026, section 5.5).
	 *
	 * - `name`: trimmed, required, `MAX_LINE` characters at most as typed; `contact_person` the
	 *   same, not required (`clean_typed()`).
	 * - `website`, `contact_email`, `product_type`: the profile card's own rules, so a manager and
	 *   the sponsor save the same values: a web address read as typed, its percent codes kept
	 *   (`posted_fields()`), through `WPCPM_Field_Value::clean_url()` that names no user, an address
	 *   `is_email()` accepts, one of the product types; or ''.
	 * - `status`: one of `WPCPM_Sponsors_Index::STATUSES`.
	 * - `test`: `0` or `1`, kept as the flag (1.123.18).
	 * - `option`: one of the application form's `OPTION_CHOICES`, or ''.
	 * - `support`: the ticked values `WPCPM_Sponsor_Interests::CHOICES` holds.
	 * - `manager`: '' or the ID of a user `WPCPM_Institutions::managers()` lists.
	 * - `tier`: one of `WPCPM_Sponsor_Tiers::KEYS`, or ''.
	 * - `tier_until`: a real calendar date (`WPCPM_Sponsor_Tiers::is_date()`), or ''.
	 * - `event_allowance`: a whole number from 0 to `MAX_EXTRA_EVENTS`, an empty box read as 0, kept
	 *   as `until`, the term's last day as the save leaves it, and `extra`, the number, or as nothing
	 *   for 0 (`allowance_after()`).
	 * - `funded_line`: one line, `MAX_LINE` characters at most as typed, the same rule as the
	 *   contact person's.
	 * - `mentors`: the ticked record IDs the mentors index, `WPCPM_Mentors_Sync::sponsorship()`, holds.
	 * - `notes`: `sanitize_textarea_field()`, `MAX_NOTES` characters at most as typed, each line
	 *   break one.
	 *
	 * The four typed texts of `TYPED` are kept as stored when they read the same as the stored text,
	 * whatever their length, and a changed one is counted as the manager typed it and refused, never
	 * cut, one over its limit (`clean_typed()`); the refusal names it.
	 *
	 * A Friend has no term (spec section 4): once every field above passes its own rule, a
	 * `tier_until` left on a Friend's row - its own already, or one the tier was just
	 * changed to - is dropped rather than refused, and `changed` reflects the drop.
	 *
	 * The extra events are read once that rule has run, since they are kept with the term's last day
	 * the save leaves, which it can clear. A number the same as the one the Tier box was drawn with,
	 * the events the record grants the term ending on its own Term ends day while that term runs
	 * (`granted()`), is the box as drawn and keeps the row's allowance as it is, so a renewal that
	 * leaves the box alone leaves the number with the term it was granted for; a number with no term
	 * to keep it for is dropped, as a Friend's day is.
	 *
	 * @param array $posted  Field => what was posted: a string, or a list of strings for the two
	 *                       lists.
	 * @param array $current The record as the store holds it, or the new record's defaults; a key it
	 *                       lacks is `WPCPM_Sponsors_Index::empty_row()`'s.
	 * @return array `ok` (bool), `row` (`$current` with every accepted change), `changed` (the keys
	 *               whose value changed, in the forms' order), and for a refusal `long`: the typed
	 *               text of `TYPED` refused for its length, or '' for any other refusal.
	 */
	public static function clean_fields( array $posted, array $current ) {
		$current = array_merge( WPCPM_Sponsors_Index::empty_row(), $current );
		$refused = array(
			'ok'      => false,
			'row'     => $current,
			'changed' => array(),
			'long'    => '',
		);
		$row     = $current;
		$changed = array();

		foreach ( self::EDITABLE as $field ) {
			// The extra events are read after the Friend's rule below: see the docblock.
			if ( ! array_key_exists( $field, $posted ) || 'event_allowance' === $field ) {
				continue;
			}

			$value = self::clean_field( $field, $posted[ $field ], $current[ $field ] );

			if ( null === $value ) {
				// A typed text is refused for its length alone (`clean_typed()`), and the notice
				// names it.
				if ( isset( self::TYPED[ $field ] ) && is_scalar( $posted[ $field ] ) ) {
					$refused['long'] = $field;
				}

				return $refused;
			}

			if ( $value !== $current[ $field ] ) {
				$row[ $field ] = $value;
				$changed[]     = $field;
			}
		}

		// A Friend has no term (spec section 4): a date left in the box is dropped, not
		// refused, whether the tier is Friend already or was just set to it.
		if ( ( '' === $row['tier'] || WPCPM_Sponsor_Tiers::MENTOR === $row['tier'] ) && '' !== $row['tier_until'] ) {
			$row['tier_until'] = '';
			$changed           = array_values( array_diff( $changed, array( 'tier_until' ) ) );

			if ( '' !== (string) $current['tier_until'] ) {
				$changed[] = 'tier_until';
			}
		}

		if ( array_key_exists( 'event_allowance', $posted ) ) {
			$extra = self::clean_field( 'event_allowance', $posted['event_allowance'], $current['event_allowance'] );

			if ( null === $extra ) {
				return $refused;
			}

			$allowance = self::allowance_after( $extra, $current, (string) $row['tier_until'] );

			// Put in its place among the fields changed, which the loop above keeps in the forms' order.
			if ( $allowance !== $current['event_allowance'] ) {
				$row['event_allowance'] = $allowance;
				$changed                = array_values( array_intersect( self::EDITABLE, array_merge( $changed, array( 'event_allowance' ) ) ) );
			}
		}

		// Required whether or not it was posted: an Add without one, or an Edit that cleared it.
		if ( '' === $row['name'] ) {
			return $refused;
		}

		return array(
			'ok'      => true,
			'row'     => $row,
			'changed' => $changed,
		);
	}

	/**
	 * One posted value by its field's rule; see `clean_fields()`.
	 *
	 * @param string $field  A key of `EDITABLE`.
	 * @param mixed  $raw    What was posted.
	 * @param mixed  $stored What the record holds.
	 * @return bool|int|string|string[]|null The value to keep, the number of extra events for
	 *                                        `event_allowance`, the flag for `test`, or null when
	 *                                        the rule refuses it.
	 */
	private static function clean_field( $field, $raw, $stored ) {
		if ( in_array( $field, self::LISTS, true ) ) {
			return is_array( $raw ) ? self::clean_list( $field, $raw, (array) $stored ) : null;
		}

		if ( ! is_scalar( $raw ) ) {
			return null;
		}

		$raw = trim( (string) $raw );

		// The test sponsor's flag (1.123.18), posted as 0 or 1: its box posts a hidden 0 before it.
		if ( 'test' === $field ) {
			return in_array( $raw, array( '0', '1' ), true ) ? '1' === $raw : null;
		}

		// A number, which the record keeps with a day: whether it is the box as drawn is
		// clean_fields()'s to ask, since that turns on the day the save leaves.
		if ( 'event_allowance' === $field ) {
			if ( '' === $raw ) {
				return 0;
			}

			return ctype_digit( $raw ) && (int) $raw <= self::MAX_EXTRA_EVENTS ? (int) $raw : null;
		}

		// Untouched: see clean_fields(). A website is read as typed and trimmed, and its box draws each
		// line break as a space (`WPCPM_Typed_Text::one_line()`), so one the row holds with a space at
		// either end or a line break inside, posted back as its box drew it, is untouched too, and
		// keeps its stored bytes rather than being completed or cleaned anew. The contact address's box
		// shows the stored address with each entity read (`render_fields()`), so one that reads the
		// same as the stored address once each entity is read (`WPCPM_Typed_Text::same_text()`) is
		// untouched as well, and keeps a stored `&amp;` as it is.
		if ( (string) $stored === $raw || ( 'website' === $field && WPCPM_Typed_Text::one_line( trim( (string) $stored ) ) === $raw ) || ( 'contact_email' === $field && WPCPM_Typed_Text::same_text( $raw, trim( (string) $stored ) ) ) ) {
			return (string) $stored;
		}

		if ( isset( self::TYPED[ $field ] ) ) {
			return self::clean_typed( $field, $raw, (string) $stored );
		}

		switch ( $field ) {
			case 'website':
			case 'contact_email':
			case 'product_type':
				$clean = WPCPM_Sponsor_Profile::clean( $field, $raw );

				return $clean['ok'] ? (string) $clean['value'] : null;
			case 'status':
				return in_array( $raw, WPCPM_Sponsors_Index::STATUSES, true ) ? $raw : null;
			case 'option':
				return '' === $raw || in_array( $raw, WPCPM_Sponsor_Application::OPTION_CHOICES, true ) ? $raw : null;
			case 'manager':
				return '' === $raw || in_array( $raw, self::manager_ids(), true ) ? $raw : null;
			case 'tier':
				return '' === $raw || WPCPM_Sponsor_Tiers::is_tier( $raw ) ? $raw : null;
			case 'tier_until':
				return '' === $raw || WPCPM_Sponsor_Tiers::is_date( $raw ) ? $raw : null;
		}

		return null;
	}

	/**
	 * One of the typed texts of `TYPED`, by its own rule: the name, the contact person and the
	 * funded line one line each, the notes with their line breaks.
	 *
	 * Read the same as the stored text (`reads_as_stored()`), it is the stored text, and is kept as it
	 * is whatever its length, so a row that holds a text longer than its limit from an earlier store
	 * does not stop a save of the other fields. A changed text is counted as the manager typed it
	 * (`typed_length()`): each entity the cleaner wrote for a "<" and the quote marks and ampersands
	 * after it, and each line break, is the one character its box counted. One over the limit is
	 * refused rather than cut, since a name or a note cut short says something else.
	 *
	 * @param string $field  A key of `TYPED`.
	 * @param string $raw    What was posted, trimmed.
	 * @param string $stored What the record holds.
	 * @return string|null The value to keep, or null when it is over its limit.
	 */
	private static function clean_typed( $field, $raw, $stored ) {
		$value = 'notes' === $field ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );

		if ( self::reads_as_stored( $field, $value, $stored ) ) {
			return $stored;
		}

		return WPCPM_Typed_Text::typed_length( $value ) <= self::TYPED[ $field ][0] ? $value : null;
	}

	/**
	 * Whether a text posted for one of the typed texts of `TYPED` reads as the text the record holds:
	 * the test of a box posted back as it was drawn, asked of what was typed (`drawn_typed()`) and of
	 * what the cleaner made of it (`clean_typed()`).
	 *
	 * Its box draws the text the manager typed (`render_fields()`), so posted back unedited it can
	 * come back in other bytes than the stored ones: a ">" the box held as `&gt;` after a "<" the
	 * cleaner would read as a tag comes back written `&gt;` where the row holds ">", and the notes'
	 * every line break comes back as CR LF. A text area drops a line break its text begins with, and
	 * the cleaner trims what is posted, so a stored text that ends or starts in a space or a line
	 * break, as an earlier store could keep one, comes back without it. So both are read trimmed, and
	 * compared once each entity is read (`WPCPM_Typed_Text::same_text()`, `same_lines()` for the
	 * notes). A one-line box is drawn with each line break inside its stored text as a space
	 * (`WPCPM_Typed_Text::one_line()`, a CR LF one break), which a text input would otherwise drop
	 * and so join the words: a name stored with "Caf%C3%A9" and "Co" on two lines, as a row brought
	 * over from the program records can hold it, would post back as "Caf%C3%A9Co", asked about losses
	 * as though the manager had typed it. So a post-back that reads as the stored text so folded is
	 * the stored text too.
	 *
	 * @param string $field  A key of `TYPED`.
	 * @param string $text   What was posted, as typed or as cleaned.
	 * @param string $stored What the record holds.
	 * @return bool
	 */
	private static function reads_as_stored( $field, $text, $stored ) {
		$text   = trim( (string) $text );
		$stored = trim( (string) $stored );

		if ( 'notes' === $field ) {
			return WPCPM_Typed_Text::same_lines( $text, $stored );
		}

		return WPCPM_Typed_Text::same_text( $text, $stored ) || WPCPM_Typed_Text::same_text( $text, WPCPM_Typed_Text::one_line( $stored ) );
	}

	/**
	 * The status a save that `clean_fields()` refused leaves with: the one that names a typed text
	 * over its limit, or the one that lists the rules for every other refusal.
	 *
	 * @param array $clean What `clean_fields()` answered.
	 * @return string A key of `messages()`.
	 */
	private static function refusal( array $clean ) {
		$long = isset( $clean['long'] ) ? (string) $clean['long'] : '';

		return isset( self::TYPED[ $long ] ) ? self::TYPED[ $long ][1] : 'sponsor-rejected';
	}

	/**
	 * The extra events a record grants the term that ends on its own Term ends day, while that term
	 * still runs: the number the Tier box is drawn holding, and what a save compares the posted number
	 * with. The allowance's count while its day is that day and has not passed, and 0 otherwise, a
	 * number granted for another term included.
	 *
	 * The same day the store counts the number for (`WPCPM_Sponsor_Event::extra_events()`), read
	 * here from the row alone, so the Edit form draws and saves on a checkout without the event store.
	 * Once that day has passed, no start can use the number, since a start has to be still to come:
	 * the box then holds none and says what the ended term was granted (`render_extra_row()`), so a
	 * renewal of a term that has ended takes the number typed with it for the renewed term in one
	 * save, rather than keeping it, drawn as the box's, with the term that ended.
	 *
	 * @param array $row A sponsor's row, read for `tier_until` and `event_allowance`.
	 * @return int
	 */
	private static function granted( array $row ) {
		$until     = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';
		$allowance = isset( $row['event_allowance'] ) && is_array( $row['event_allowance'] ) ? $row['event_allowance'] : array();

		return '' !== $until && isset( $allowance['until'], $allowance['extra'] ) && (string) $allowance['until'] === $until && strcmp( $until, WPCPM_Sponsor_Tiers::today() ) >= 0 ? (int) $allowance['extra'] : 0;
	}

	/**
	 * The allowance a save leaves on the row, from the number the Tier box posted.
	 *
	 * The number the box was drawn with (`granted()`) is the box as drawn, and the row keeps its
	 * allowance as it is: a renewal that leaves the box alone leaves the number with the term it was
	 * granted for, which the store then counts for that term alone. With no term after the save the
	 * row keeps its allowance too, since a sponsor with no term waits only for its last event to take
	 * place and a number has no term to be kept for. Any other number is the term's the save leaves:
	 * kept with its last day, or nothing for 0.
	 *
	 * @param int    $extra   The number posted, from 0 to `MAX_EXTRA_EVENTS`.
	 * @param array  $current The record before the save.
	 * @param string $until   The last day of the term the save leaves, `Y-m-d`, or '' for none.
	 * @return array `until` and `extra`, or `array()`.
	 */
	private static function allowance_after( $extra, array $current, $until ) {
		if ( '' === $until || self::granted( $current ) === $extra ) {
			return $current['event_allowance'];
		}

		return $extra > 0 ? array(
			'until' => $until,
			'extra' => $extra,
		) : array();
	}

	/**
	 * A ticked list: the values the form offers and those already on the record, which the form
	 * draws too and which pass as themselves (see `clean_fields()`), in that order.
	 *
	 * @param string   $field  `support` or `mentors`.
	 * @param array    $raw    The ticked values.
	 * @param string[] $stored What the record holds.
	 * @return string[]
	 */
	private static function clean_list( $field, array $raw, array $stored ) {
		$stored  = array_values( array_map( 'strval', $stored ) );
		$offered = 'support' === $field ? WPCPM_Sponsor_Interests::CHOICES : array_map( 'strval', array_keys( WPCPM_Mentors_Sync::sponsorship() ) );
		$value   = array_values( array_unique( array_intersect( array_merge( $offered, $stored ), array_map( 'strval', $raw ) ) ) );

		// The same values in another order are no change.
		$sorted_value  = $value;
		$sorted_stored = $stored;
		sort( $sorted_value );
		sort( $sorted_stored );

		return $sorted_value === $sorted_stored ? $stored : $value;
	}

	/**
	 * What the form posted, for `clean_fields()`: each field it carries, and none it does not.
	 *
	 * A box left clear posts nothing, so each list on the forms carries one hidden empty value as
	 * well: a list that arrives is a list the form drew, and one that does not arrive is left as it
	 * is (the Edit form draws no mentors while the mentors index has none to offer).
	 *
	 * @param string[] $fields The fields to read, keys of `EDITABLE`.
	 * @return array Field => the posted string, or the ticked values of a list.
	 */
	private static function posted_fields( array $fields ) {
		$posted = array();

		foreach ( $fields as $field ) {
			$name = 'wpcpm_' . $field;

			if ( in_array( $field, self::LISTS, true ) ) {
				$list = WPCPM_Request::posted_list( $name, self::LIST_VALUE );

				if ( in_array( '', $list, true ) ) {
					$posted[ $field ] = array_values( array_diff( $list, array( '' ) ) );
				}

				continue;
			}

			// A note keeps its line breaks, and the website is read as typed, as the profile card reads
			// its own: the one-line cleaner removes every `%XX`, which would rewrite an address in
			// another alphabet or a query with a space in it, and `clean_field()` runs the website
			// through `WPCPM_Field_Value::clean_url()`, whose `esc_url_raw()` keeps them. Every other
			// field is one line.
			if ( 'notes' === $field ) {
				$value = WPCPM_Request::posted_lines( $name, null );
			} elseif ( 'website' === $field ) {
				$value = WPCPM_Request::posted_verbatim( $name, null );
			} else {
				$value = WPCPM_Request::posted_text( $name, null );
			}

			if ( null !== $value ) {
				$posted[ $field ] = $value;
			}
		}

		return $posted;
	}

	/**
	 * A sponsor's Edit form, or the Add form, as `WPCPM_Typed_Text::keep()` names it: the form, and
	 * the sponsor an Edit form edits, so a refused save's typing comes back in the form it was typed
	 * into and in no other.
	 *
	 * @param string $key Sponsor key, or '' for the Add form.
	 * @return string
	 */
	private static function typed_form( $key ) {
		return '' === (string) $key ? 'sponsor-add' : 'sponsor-edit:' . $key;
	}

	/**
	 * What the form posted, before the cleaner has read it: each field it carries as typed
	 * (`WPCPM_Request::posted_raw()`), unslashed and neither cleaned nor trimmed, and each list it
	 * drew as `posted_fields()` reads it, the ticked values.
	 *
	 * @param string[] $fields The fields to read, keys of `EDITABLE`.
	 * @return array Field => what was typed, or the ticked values of a list.
	 */
	private static function typed_post( array $fields ) {
		$typed = array_intersect_key( self::posted_fields( $fields ), array_flip( self::LISTS ) );

		foreach ( array_diff( $fields, self::LISTS ) as $field ) {
			$value = WPCPM_Request::posted_raw( 'wpcpm_' . $field, null );

			if ( null !== $value ) {
				$typed[ $field ] = $value;
			}
		}

		return $typed;
	}

	/**
	 * The typed texts of `TYPED` posted back as they were drawn: each that reads as the text the
	 * record holds (`reads_as_stored()`), asked of what was typed.
	 *
	 * @param array $typed   What was typed, as `typed_post()` read it.
	 * @param array $current The record as the store holds it.
	 * @return string[] Keys of `TYPED`, in its order.
	 */
	private static function drawn_typed( array $typed, array $current ) {
		$drawn = array();

		foreach ( array_keys( self::TYPED ) as $field ) {
			$stored = isset( $current[ $field ] ) && is_scalar( $current[ $field ] ) ? (string) $current[ $field ] : '';

			if ( isset( $typed[ $field ] ) && is_string( $typed[ $field ] ) && self::reads_as_stored( $field, $typed[ $field ], $stored ) ) {
				$drawn[] = $field;
			}
		}

		return $drawn;
	}

	/**
	 * The first of the typed texts of `TYPED`, in the forms' order, that the cleaner would take words
	 * from (`WPCPM_Typed_Text::cleaner_loses()`): "Kids <12 free, adults >18 pay" would be stored as
	 * "Kids 18 pay". A box posted back as it was drawn is not asked.
	 *
	 * @param array    $typed What was typed, as `typed_post()` read it.
	 * @param string[] $drawn The boxes posted back as drawn (`drawn_typed()`).
	 * @return string A key of `TYPED`, or '' when none would lose a word.
	 */
	private static function lost_box( array $typed, array $drawn ) {
		foreach ( array_keys( self::TYPED ) as $field ) {
			if ( isset( $typed[ $field ] ) && ! in_array( $field, $drawn, true ) && WPCPM_Typed_Text::cleaner_loses( $typed[ $field ], 'notes' === $field ? 'lines' : 'line' ) ) {
				return $field;
			}
		}

		return '';
	}

	/**
	 * Keep what a refused Edit or Add form held for the one time it is drawn again (`render_edit()`,
	 * `render_add()`): every field as it was posted, so the manager makes the change the refusal names
	 * and loses none of the others.
	 *
	 * A typed text of `TYPED` is kept as typed, up to the room its box was drawn with, and never less
	 * than the text it was drawn with (`WPCPM_Typed_Text::kept_room()`); the website and the contact
	 * address whole, as their boxes take any length. A choice is kept when its field's rule takes it
	 * (`clean_field()`): a value the form offers, or the one the record holds, which is what a select,
	 * the Term ends day and the extra events' number can post. A list is kept as the ticked values the
	 * form draws (`clean_list()`). A choice a hand-made post sent is not kept, so the form is never
	 * drawn offering it.
	 *
	 * @param string $form  The form, as `typed_form()` names it.
	 * @param array  $typed What was typed, as `typed_post()` read it.
	 * @param array  $row   The record the form was drawn from, or the new record's defaults.
	 */
	private static function keep_typing( $form, array $typed, array $row ) {
		$row  = array_merge( WPCPM_Sponsors_Index::empty_row(), $row );
		$kept = array();
		$room = array();

		foreach ( $typed as $field => $value ) {
			if ( is_array( $value ) ) {
				$kept[ $field ] = self::clean_list( $field, $value, (array) $row[ $field ] );

				continue;
			}

			if ( isset( self::TYPED[ $field ] ) ) {
				$room[ $field ] = WPCPM_Typed_Text::kept_room( (string) $row[ $field ], self::TYPED[ $field ][0] );
			} elseif ( in_array( $field, array( 'website', 'contact_email' ), true ) || null !== self::clean_field( $field, $value, $row[ $field ] ) ) {
				$room[ $field ] = mb_strlen( (string) $value, 'UTF-8' );
			} else {
				continue;
			}

			$kept[ $field ] = $value;
		}

		WPCPM_Typed_Text::keep( $form, $kept, $room );
	}

	/**
	 * The program managers a sponsor can be assigned to, by user ID as the row keeps it.
	 *
	 * @return string[]
	 */
	private static function manager_ids() {
		return array_map(
			static function ( $user ) {
				return (string) $user->ID;
			},
			WPCPM_Institutions::managers()
		);
	}

	/**
	 * The fields a manager set on a new sponsor: those of `EDITABLE` that hold a value.
	 *
	 * @param array $row The new row.
	 * @return string[]
	 */
	private static function filled( array $row ) {
		return array_values(
			array_filter(
				self::EDITABLE,
				static function ( $field ) use ( $row ) {
					return array() !== $row[ $field ] && '' !== $row[ $field ] && false !== $row[ $field ];
				}
			)
		);
	}

	/**
	 * Detach every account that acts for a sponsor, as the setting asks when it says revoke, and
	 * clear the account flag once nobody is left, as the members handler does for the last account
	 * it removes.
	 *
	 * @param string $key   Sponsor key.
	 * @param int    $actor The manager who changed the status.
	 * @return int How many accounts were detached.
	 */
	private static function revoke_accounts( $key, $actor ) {
		$detached = 0;

		foreach ( WPCPM_Sponsor_Members::members_of( $key ) as $member ) {
			if ( true === WPCPM_Sponsor_Members::detach( $member->ID, WPCPM_Sponsor_Members::REASON_REVOKED, $actor ) ) {
				++$detached;
			}
		}

		// Asked again rather than assumed: an account a detach refused still acts for the sponsor,
		// and the flag has to say so.
		if ( empty( WPCPM_Sponsor_Members::members_of( $key ) ) ) {
			self::mark_dashboard_account( $key, false );
		}

		return $detached;
	}

	/**
	 * A sponsor's Edit form: the Sponsors tab with `?edit=<key>`, which draws that form in place of the
	 * tab's cards (`render_tab_sponsors()`).
	 *
	 * The one builder of every address that opens the form: Edit on each row of the Sponsors card, the
	 * way back after Add and every save, Not a renewal's landing (`WPCPM_Sponsor_Report_Screen`), the
	 * daily run's mail about a term that ends (`WPCPM_Sponsor_Tiers_Run`) and the Administrator
	 * Dashboard's Terms ending rows (`WPCPM_Administrators_Cards`). Public and static for those three,
	 * which have no module to ask, so the address is written out, as `accounts_view_url()` writes its
	 * own: the module's own address is an instance method (`admin_url()`), and the screen's page slug
	 * is fixed. The key is encoded as a query value; whether it names a sponsor is the form's to ask
	 * (`render_edit()`).
	 *
	 * @param string $key Sponsor key.
	 * @return string
	 */
	public static function edit_url( $key ) {
		return admin_url( 'admin.php?page=wpcpm-sponsors&tab=sponsors&' . self::ARG_EDIT . '=' . rawurlencode( (string) $key ) );
	}

	/**
	 * Back to a sponsor's Edit form on the Sponsors tab (`edit_url()`) with the outcome flashed, rather
	 * than to the list: a manager who has just saved a sponsor is still working on it.
	 *
	 * @param string $status An outcome `messages()` knows.
	 * @param string $key    Sponsor key.
	 */
	private function redirect_to_edit( $status, $key ) {
		WPCPM_Flash::set( $this->flash_key(), $status );
		wp_safe_redirect( self::edit_url( $key ) );
		exit;
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
				'message'  => __( 'The first offer was created in draft; the sponsor completes it and switches it on from the Sponsor Dashboard.', 'wpcredits-program-manager' ),
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

		// void_claim() answers true, false or a WP_Error (the pool's lock can be another
		// request's for a moment), which is not the same "nothing to void" as a person
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
			printf( '<button type="submit" class="button">%s</button>', esc_html( sprintf( __( 'Create the first offer for %s', 'wpcredits-program-manager' ), '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record ) ) );
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
			echo '<td>' . esc_html( $offer['title'] ) . ( $offer['primary'] ? ' <span class="description">' . esc_html__( '(primary)', 'wpcredits-program-manager' ) . '</span>' : '' ) . '</td>';
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
					printf( '<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-confirm="%3$s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr__( 'Voiding', 'wpcredits-program-manager' ), esc_attr__( 'Void this claim? The person may then claim again.', 'wpcredits-program-manager' ) );
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
	 * The site's writer of the row's `dashboard_account` flag for provisioning and member
	 * changes (spec section 12): the provision handler sets it true; the members handler sets it
	 * true when it attaches an account and false when a sponsor's last account goes; and a status
	 * change that detaches the accounts (`revoke_accounts()`) sets it false. The approval sets the
	 * flag true on insert, a save of the Edit form sets it true in its own patch for a sponsor an
	 * account acts for whose record says it has none (`handle_edit()`), and the migration copies
	 * whatever the old index held; none of those three goes through this method, nor do the two that
	 * give a new row its first false: a sponsor added on the Sponsors tab (`handle_add()`) and the
	 * index's `empty_row()`, which a row that does not give the flag takes it from.
	 *
	 * @param string $record Sponsor key.
	 * @param bool   $flag   Whether it has an account.
	 * @return bool True when the row was patched; false for a malformed or unknown key, which
	 *              touches nothing.
	 */
	public static function mark_dashboard_account( $record, $flag ) {
		$record = trim( (string) $record );

		// Refused here, before the store is asked at all: patch() would answer false for a
		// malformed key too (post_id_of() refuses it), but a string that is not shaped like a key
		// should not cost a read to find that out.
		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return false;
		}

		return WPCPM_Sponsors_Index::patch( $record, array( 'dashboard_account' => (bool) $flag ) );
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
	 * Read with `WPCPM_Request::text()` and never a `key()`: `sanitize_key()` lowercases, and a
	 * sponsor's key is case-sensitive. Whether it names a sponsor the index holds is the view's to ask
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
	 * @param string $record The sponsor's key, with its case.
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
	 * The tabs as the bar prints them: TABS, each label translated, but for Handbook listing while
	 * the tiers switch is off.
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
	 * The last tab, Handbook listing, holds the listing for the handbook, which names the tiers, so it
	 * is in the bar only while the tiers switch is on, by the test the box itself is drawn under
	 * (`listing_shown()`). While the switch is off it is left out here, and its address opens the
	 * first tab, as the address of a tab the screen does not have does (`render_admin_page()`).
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
			'reports'          => __( 'Reports', 'wpcredits-program-manager' ),
			'agreements'       => __( 'Agreements', 'wpcredits-program-manager' ),
			'listing'          => __( 'Handbook listing', 'wpcredits-program-manager' ),
		);

		$tabs = self::TABS;

		if ( ! self::listing_shown() ) {
			unset( $tabs['listing'] );
		}

		return array_merge( $tabs, array_intersect_key( $translated, $tabs ) );
	}

	/**
	 * Render the Sponsors screen: the outcome of the last press, the tab bar, then the tab shown.
	 *
	 * Eight tabs by job, in TABS: Waiting for review holds the sponsor applications, the sponsor posts,
	 * the sponsors' events while they are in use and the signed agreements waiting, read here and
	 * decided on the Administrator Dashboard, or one application opened from them in their place;
	 * Sponsors the migration box while the move onto the site is incomplete, then every sponsor, or
	 * one sponsor's Edit form in their place; Accounts the accounts locked for the day, the
	 * invitations and the sponsor accounts list, or one sponsor's accounts in their place; Offers and
	 * codes every offer with its codes and claims; Interests what sponsors said they would like to
	 * support; Reports every Approved sponsor's annual reports, or one report's editor in their place;
	 * Agreements every Approved sponsor's agreement; and Handbook listing, while the tiers switch is
	 * on, the listing for the handbook.
	 *
	 * While the switch is off the bar leaves Handbook listing out (`tab_labels()`), and its address
	 * opens the first tab, the queue, as the address of a tab the screen does not have does. The
	 * shared screen's reading of the tab (`tab()`) knows every tab of TABS, so the frame asks the bar:
	 * for a tab the bar does not hold it draws the first tab, and the bar marks that one. The rule is
	 * the frame's, not a `tab()` of this class's own, so the reading stays the one every audience's
	 * screen shares; the other readers of it here, the load hook and `view_record()`, ask only whether
	 * the tab is Accounts.
	 *
	 * The outcome notice is the whole screen's: one map of every outcome a press here can flash,
	 * printed above the bar on whichever tab is shown. A press comes back to the tab it was made on,
	 * so its sentence prints there; the agreement forms come back by their referer, which is the tab
	 * they were pressed on. One whose way back names no tab lands on the screen's own address, which
	 * is the queue: a decision on an opened application. Each tab reads only what it draws.
	 *
	 * Unlike the screens whose audience is synced, the frame prints no warning while the site has no
	 * connection to another service (`render_not_connected()`): the sponsors are the site's own
	 * records, added and edited here, so nothing on this screen waits for one.
	 */
	public function render_admin_page() {
		$tabs = self::tab_labels();
		$tab  = self::tab();

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = (string) array_key_first( $tabs );
		}

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		$this->render_status_notice( $this->outcome_messages( $tab ) );

		WPCPM_Screen_Tabs::render( $this->page_slug(), $tabs, $tab );

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
			case 'reports':
				$this->render_tab_reports();
				break;
			case 'agreements':
				$this->render_tab_agreements();
				break;
			case 'listing':
				$this->render_tab_listing();
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
	 * when none of them was ready, on one sponsor when it has an account already. None of the sync's
	 * outcomes is in it, since no sync runs here (`sync_messages()`). `error` is worded by the tab it
	 * comes back to: a row's invitation that could not be sent comes back to the Accounts tab, which
	 * words it for the invitation; no tab prints an error below the notice, so on every other tab the
	 * words send the reader to the screen again (`failed_message()`).
	 *
	 * @param string $tab The tab shown.
	 * @return array<string, array> Status => notice type and sentence.
	 */
	private function outcome_messages( $tab ) {
		$messages = self::messages();

		$messages['error'] = self::failed_message();

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
	 * The Waiting for review tab: the kinds of item a manager decides, each in a card of its own in
	 * the Administrator Dashboard's order, or one application opened from them in their place.
	 *
	 * The tab reads and the Administrator Dashboard decides, a decision's one home: the sponsor
	 * applications, the sponsor posts, the sponsors' events while they are in use and the signed
	 * agreements waiting are listed here, and each links to its place there. While the dashboard's
	 * page is missing there is nowhere to link, so the sentence its class keeps for that prints once,
	 * above the cards, and no card or row links.
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
		$this->render_queue_offers();
		$this->render_queue_events();
		$this->render_queue_agreements();
	}

	/**
	 * The Sponsors tab: the migration box while the move onto the site is incomplete
	 * (`render_migration()`), then the Sponsors card (`render_index()`): what the tiers as set would
	 * put to dormant after the grace date, the table of every sponsor with its tier and, in each row's
	 * last cell, Edit and Manage accounts, then the form that adds a sponsor, which names this tab.
	 *
	 * When the address names a sponsor's key, that sponsor's Edit form is drawn in their place, the
	 * tab's view of one sponsor, and the accounts are not read for it (`render_edit()`); a key the
	 * store does not hold draws the cards under a notice that says so.
	 *
	 * Nothing here reads or draws a sync: the sponsors are the site's own records, listed, added and
	 * edited on this tab, and a sponsor's account is created on the Accounts tab.
	 */
	private function render_tab_sponsors() {
		$rows = WPCPM_Sponsors_Index::rows();

		if ( $this->render_edit( $rows ) ) {
			return;
		}

		$this->render_migration();
		$this->render_index( $rows, self::accounts_by_sponsor() );
	}

	/**
	 * The Accounts tab: the sponsor accounts locked out of changes for the rest of the day, the
	 * invitations card, then the sponsor accounts list, whose No account view lists the Approved
	 * sponsors with no account yet and creates the missing ones; or, when the address names a sponsor,
	 * that sponsor's accounts in their place (`render_sponsor_accounts()`).
	 *
	 * The screen's own, in place of the shared screen's: the notices of the presses made on the tab
	 * are this screen's frame's, printed above the bar on every tab (`render_admin_page()`), so the
	 * tab does not print them again; and it prints no warning about a connection, since the accounts
	 * it lists are made on the site, by a press here or an approved application, and none arrives by
	 * a sync. The locked accounts are the list's own reading of them, which its Status cells read
	 * too, so one draw of the tab asks the ceiling once (`WPCPM_Sponsors_Table::locked_accounts()`)
	 * and the notice goes with the list, not above one sponsor's accounts; the list is the one the
	 * screen's load hook built, or one built and read now (`table()`).
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
	 * never printed: the view says so, and that a sponsor is added on the Sponsors tab, linked. A press
	 * can still land on such a view, from a form that posted a record the index does not hold; its
	 * outcome is printed above the bar like any other.
	 *
	 * The accounts' addresses are printed here, as the Sponsor Dashboard's People card prints them: a
	 * manager attaches and removes accounts by address. The list prints none. A sponsor that is not
	 * Approved keeps the accounts it has until they are removed, so they are listed with Remove; the
	 * form that attaches one and the switch are drawn for an Approved sponsor alone, as the screen has
	 * always drawn them.
	 *
	 * @param string $record The key the address names, as given.
	 */
	private function render_sponsor_accounts( $record ) {
		echo '<div class="wpcpm-card">';

		// `row()` asks whether the value is a key before it looks, so a value that is none costs
		// no read of the index and is answered as one the index lacks.
		$row = WPCPM_Sponsors_Index::row( $record );

		if ( null === $row ) {
			printf(
				'<p>%1$s %2$s</p>',
				esc_html__( 'That link names no sponsor on this site.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: %s: the name of the Sponsors tab, as a link to it. */
					esc_html__( 'A sponsor is added on the %s tab.', 'wpcredits-program-manager' ),
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
					'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-confirm="%3$s">',
					esc_url( admin_url( 'admin-post.php' ) ),
					esc_attr__( 'Removing', 'wpcredits-program-manager' ),
					esc_attr__( 'Remove this account from the sponsor?', 'wpcredits-program-manager' )
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
							_n( 'Posting is on: %s account can write posts in wp-admin; an Administrator publishes them.', 'Posting is on: %s accounts can write posts in wp-admin; an Administrator publishes them.', count( $members ), 'wpcredits-program-manager' ),
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
	 * A sponsor report's editor, a view of the Reports tab drawn in place of its card when
	 * `?wpcpm_sreport=<id>` names a report, as one sponsor's Edit form is on the Sponsors tab: drawn
	 * by the report screen's class, with the way back to the tab
	 * (`WPCPM_Sponsor_Report_Screen::render_editor()`). Reached from Open on the Reports card and on
	 * the Administrator Dashboard's Sponsor reports card, and where every press made in the editor
	 * lands. An ID that names no report is said in a notice, and the card is drawn under it.
	 *
	 * @return bool Whether the editor was drawn, in which case the tab draws nothing else.
	 */
	private function render_report() {
		if ( ! class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			return false;
		}

		$post_id = WPCPM_Request::id( WPCPM_Sponsor_Report_Files::ARG_EDITOR );

		if ( $post_id < 1 ) {
			return false;
		}

		if ( WPCPM_Sponsor_Report_Screen::render_editor( $post_id ) ) {
			return true;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'That report is not on this site anymore. Every Approved sponsor\'s reports are listed on the Reports card below.', 'wpcredits-program-manager' )
		);

		return false;
	}

	/**
	 * The Reports card, the Reports tab's own: each Approved sponsor's annual reports and the form
	 * that drafts one, drawn by the report screen's class. Guarded like the module's other classes,
	 * so the screen draws with or without it.
	 *
	 * @param array $rows The index rows.
	 */
	private function render_reports( array $rows ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			return;
		}

		WPCPM_Sponsor_Report_Screen::render_card( $rows );
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
	 * The way back from a sponsor's Edit form to the Sponsors tab, which lists every sponsor: the
	 * tab's own address, since the list there has no state of its own to return to.
	 */
	private function render_back_to_sponsors() {
		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( $this->tab_url( 'sponsors' ) ),
			esc_html__( 'Back to the sponsors', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The invitations card, on the Accounts tab: every sponsor account never invited.
	 *
	 * Its button posts the tab, so the press comes back to it. Its Stop and Dismiss are the mail
	 * layer's own forms, which name no tab: they come back to the address they were pressed on, this
	 * tab, and Stop's outcome waits on the flash channel this screen reads, which the card names in
	 * its form, so "Sending stopped." prints here. Its button counts accounts, not people, in words
	 * of its own. An account that also mentors and whose invitation an activation held (1.122.33) is
	 * left out of the button, and the card says how many, since only the account's own row sends it.
	 */
	private function render_invitations() {
		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => self::never_invited(),
				'held'    => count( self::held_back() ),
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
	 * The Reports tab: the Reports card, every Approved sponsor's annual reports and the form that
	 * drafts one (`render_reports()`); or, when the address names a report, its editor in the card's
	 * place, the tab's view of one report (`render_report()`), which reads the report and not the
	 * index. An ID that names no report draws the card under a notice that says so.
	 */
	private function render_tab_reports() {
		if ( $this->render_report() ) {
			return;
		}

		$this->render_reports( WPCPM_Sponsors_Index::rows() );
	}

	/**
	 * The Agreements tab: every Approved sponsor's agreement, and where a document waiting to be read
	 * is listed.
	 */
	private function render_tab_agreements() {
		$this->render_agreements( WPCPM_Sponsors_Index::rows() );
	}

	/**
	 * The Handbook listing tab, in the bar while the tiers switch is on: the listing for the handbook
	 * (`render_listing_box()`), its one card, which reads the index. While the switch is off the tab
	 * is not in the bar and its address opens the first tab (`render_admin_page()`), so this is drawn
	 * only while the box is shown, under the same test (`listing_shown()`).
	 */
	private function render_tab_listing() {
		$this->render_listing_box();
	}

	/**
	 * The sponsor posts waiting for review, oldest first, to be read: each is published or returned
	 * on the Administrator Dashboard, and each row ends with the way to its own item there.
	 *
	 * The rows are the dashboard card's own read, `WPCPM_Administrators_Cards::LIMIT` of them, so no
	 * row links to an item that card does not draw: the read applies its limit before it leaves out
	 * a post whose sponsor stamp is no sponsor's key, so the first rows of a longer read could differ.
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
	 * The sponsor offers, and the changes to live ones, waiting for review (1.123.18), oldest first, to
	 * be read: each is approved or returned on the Administrator Dashboard's card of the same title,
	 * and each row ends with the way to its own item there. Read as the posts' card reads its own: the
	 * rows the dashboard card's own read, `WPCPM_Administrators_Cards::LIMIT` of them, and the heading
	 * counted from a second read, one more than `COUNT_MAX`, the list saying so whenever it shows
	 * fewer than wait. A row says in one sentence whether it is a new offer or changes, whose, who
	 * sent it and how long ago.
	 */
	private function render_queue_offers() {
		if ( ! class_exists( 'WPCPM_Sponsor_Offer_Review' ) ) {
			return;
		}

		$waiting = count( (array) WPCPM_Sponsor_Offer_Review::waiting_all( self::COUNT_MAX + 1 ) );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-offers-review">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Sponsor offers to review', 'wpcredits-program-manager' ),
			esc_html( self::queue_count( $waiting ) )
		);
		echo '<p class="description">' . esc_html__( 'Offers that sponsors sent for review, and changes they made to what students see of live ones, oldest first. Each is approved or returned on the Administrator Dashboard.', 'wpcredits-program-manager' ) . '</p>';

		if ( 0 === $waiting ) {
			echo '<p>' . esc_html__( 'Nothing is waiting.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		$rows = (array) WPCPM_Sponsor_Offer_Review::waiting_all( (int) WPCPM_Administrators_Cards::LIMIT );

		self::render_queue_window( count( $rows ), $waiting );

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $row ) {
			$who = '' !== (string) $row['by'] ? (string) $row['by'] : __( 'somebody whose account is gone', 'wpcredits-program-manager' );
			$ago = human_time_diff( (int) $row['at'], time() );

			echo '<li class="wpcpm-queue-item">';
			printf( '<h3 class="wpcpm-queue-title">%s</h3>', esc_html( (string) $row['title'] ) );
			printf(
				'<p>%s</p>',
				esc_html(
					'new' === $row['change']
						/* translators: 1: the sponsor's name, 2: the name of the account that sent it, 3: how long ago, as a human-readable time difference. */
						? sprintf( __( 'A new offer from %1$s, sent by %2$s %3$s ago.', 'wpcredits-program-manager' ), (string) $row['company'], $who, $ago )
						/* translators: 1: the sponsor's name, 2: the name of the account that sent them, 3: how long ago, as a human-readable time difference. */
						: sprintf( __( 'Changes to a live offer of %1$s, sent by %2$s %3$s ago.', 'wpcredits-program-manager' ), (string) $row['company'], $who, $ago )
				)
			);
			WPCPM_Return::render_dashboard_link( 'sponsor-offers-review', 'wpcpm-sponsor-offer-' . (int) $row['id'] );
			echo '</li>';
		}

		echo '</ol>';
		echo '</div>';
	}

	/**
	 * The events sponsors planned that wait for the program's OK, oldest first, to be read: each is
	 * approved or returned on the Administrator Dashboard's Events card, whose title this card takes,
	 * and each row ends with the way to its own item there. Drawn only while the events are in use
	 * (`WPCPM_Sponsor_Event_Queue::shown()`), as that card is, and nothing of them is read otherwise.
	 *
	 * The rows are that card's own read of what waits (`WPCPM_Sponsor_Event_Queue::waiting_rows()`),
	 * in its order, and the list holds every one, as that card does: a sponsor holds one event at a
	 * time, so the list is never longer than the sponsors with an event waiting. The heading counts
	 * them, in the menu bubble's form past `COUNT_MAX`. A row says in one sentence whose event it is,
	 * who planned it and how long ago, and when it takes place in the words the dashboard's item opens
	 * with (`WPCPM_Sponsor_Event_Queue::time_fact()`), so the two read one event's time alike. A row
	 * carries its planner's ID and no name, since the dashboard prints none, so the planners' accounts
	 * are read here, warmed in one query first. The card's id is the one the dashboard's Events card
	 * and the Overview link to.
	 */
	private function render_queue_events() {
		if ( ! class_exists( 'WPCPM_Sponsor_Event_Queue' ) || ! WPCPM_Sponsor_Event_Queue::shown() ) {
			return;
		}

		$rows    = (array) WPCPM_Sponsor_Event_Queue::waiting_rows();
		$waiting = count( $rows );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-events">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Events', 'wpcredits-program-manager' ),
			esc_html( self::queue_count( $waiting ) )
		);
		echo '<p class="description">' . esc_html__( 'Events that sponsors planned and that wait for the program\'s OK, oldest first. An event is approved or returned on the Administrator Dashboard.', 'wpcredits-program-manager' ) . '</p>';

		if ( 0 === $waiting ) {
			echo '<p>' . esc_html__( 'Nothing is waiting.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		// The planners' accounts in one query, each read from it below.
		$planners = array_values( array_unique( array_filter( array_map( 'intval', array_column( $rows, 'planned_author' ) ) ) ) );

		if ( array() !== $planners ) {
			cache_users( $planners );
		}

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $row ) {
			$planner = (int) $row['planned_author'] > 0 ? get_userdata( (int) $row['planned_author'] ) : false;
			$planned = $planner instanceof WP_User ? (string) $planner->display_name : '';

			echo '<li class="wpcpm-queue-item">';
			printf( '<h3 class="wpcpm-queue-title">%s</h3>', esc_html( (string) $row['title'] ) );
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: the sponsor's name, 2: the name of the account that planned the event, 3: how long ago, as a human-readable time difference, 4: when the event takes place, its length and the time zone, as "December 1, 2026 10:00 am to 11:30 am, 90 minutes, in your time zone (Europe / Warsaw)". */
						__( 'From %1$s, by %2$s. Planned %3$s ago, for %4$s.', 'wpcredits-program-manager' ),
						(string) $row['sponsor_name'],
						'' !== $planned ? $planned : __( 'somebody whose account is gone', 'wpcredits-program-manager' ),
						human_time_diff( (int) $row['planned_at'], time() ),
						WPCPM_Sponsor_Event_Queue::time_fact( (array) $row )
					)
				)
			);
			WPCPM_Return::render_dashboard_link( 'sponsor-events', 'wpcpm-sponsor-event-' . (int) $row['id'] );
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
	 * sign. The posts, events and signed agreements cards on the Waiting for review tab print it in
	 * their headings, and the posts' and the agreements' in their window lines; so do the
	 * Administrator Dashboard's tiles and cards for the posts and the agreements, and the Overview's
	 * counts of those two. The applications are counted in full and print their number as it stands,
	 * on the applications card here and on those pages alike.
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
	 * The migration box, where the sync panel stood, and only while the move onto the site is
	 * incomplete (design section 5): how far the move got, the sponsors it could give no program
	 * manager, the sponsors still waiting for the draft offer that keeps it open, and Run again, whose
	 * form names the Sponsors tab, so its press comes back to that tab (`handle_migrate()`): to this
	 * box while the move is still incomplete, and to the Sponsors card alone once the run has
	 * completed it.
	 */
	private function render_migration() {
		$report = WPCPM_Sponsors_Migration::report();

		if ( $report['complete'] ) {
			return;
		}

		echo '<div class="wpcpm-card" id="wpcpm-sponsors-migration">';
		echo '<h2>' . esc_html__( 'Moving sponsors onto the site', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Since 1.111.0 every sponsor is a record of its own on this site. The move runs by itself and tries again at most once a minute until every sponsor has moved; this box goes away when it has.', 'wpcredits-program-manager' ) . '</p>';

		if ( $report['runs'] < 1 ) {
			echo '<p>' . esc_html__( 'The move has not run yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: how many sponsors are posts on the site, 2: how many rows the old index held. */
						_n(
							'%1$s of %2$s row is a post.',
							'%1$s of %2$s rows are posts.',
							$report['rows'],
							'wpcredits-program-manager'
						),
						number_format_i18n( $report['posts'] ),
						number_format_i18n( $report['rows'] )
					)
				)
			);
		}

		if ( ! empty( $report['unmapped'] ) ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: sponsor names, comma-separated. */
						__( 'No Administrator on this site matched the sponsors\' Administrator, so none is assigned: %s. Choose one on each sponsor\'s Edit form.', 'wpcredits-program-manager' ),
						implode( ', ', $report['unmapped'] )
					)
				)
			);
		}

		// Named because they are why a move with every row a post is still open: each is owed its
		// first offer, in draft, from the text the old index held, and that text has no other copy.
		if ( ! empty( $report['unseeded'] ) ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: sponsor names, comma-separated. */
						__( 'Still waiting for their first offer, in draft, which keeps the move open until each has one: %s.', 'wpcredits-program-manager' ),
						implode( ', ', $report['unseeded'] )
					)
				)
			);
		}

		printf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Running', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_MIGRATE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_MIGRATE ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'sponsors' ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Run again', 'wpcredits-program-manager' ) );
		echo '</form></div>';
	}

	/**
	 * A sponsor's Edit form, a view of the Sponsors tab drawn in place of its cards when `?edit=<key>`
	 * names a sponsor the store holds, as the opened application is on the queue tab and one sponsor's
	 * accounts on the Accounts tab: the sponsor's name, the way back to the tab, what a manager owns of
	 * the record, then its key and listing handle, which nobody edits. Reached from Edit on each row of
	 * the Sponsors card, and where Add and every save of the form land (`redirect_to_edit()`).
	 *
	 * The form names the Sponsors tab, so the one press that cannot come back to the form, a save for
	 * a key the store no longer holds, comes back to the tab (`handle_edit()`), and its Cancel is the
	 * tab too. A key the store does not hold, well formed or not, is never printed: a notice says the
	 * link names no sponsor on this site, and the tab's cards are drawn under it.
	 *
	 * @param array $rows The index rows.
	 * @return bool Whether the form was drawn, in which case the tab draws nothing else.
	 */
	private function render_edit( array $rows ) {
		$key = WPCPM_Request::text( self::ARG_EDIT );

		if ( '' === $key ) {
			return false;
		}

		if ( ! isset( $rows[ $key ] ) ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s %2$s</p></div>',
				esc_html__( 'That link names no sponsor on this site.', 'wpcredits-program-manager' ),
				esc_html__( 'Every sponsor on this site is listed on the Sponsors card below.', 'wpcredits-program-manager' )
			);

			return false;
		}

		$row  = $rows[ $key ];
		$name = '' === trim( (string) $row['name'] ) ? $key : trim( (string) $row['name'] );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-edit">';
		printf(
			'<h2>%s</h2>',
			esc_html(
				sprintf(
					/* translators: %s: sponsor name. */
					__( 'Edit %s', 'wpcredits-program-manager' ),
					$name
				)
			)
		);
		$this->render_back_to_sponsors();
		printf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_EDIT . '_' . $key );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_EDIT ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $key ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'sponsors' ) );
		// A refused save: every field is drawn as it was posted (`keep_typing()`).
		self::render_fields( 'edit', $row, WPCPM_Typed_Text::kept( self::typed_form( $key ) ) );
		printf(
			'<p class="submit"><button type="submit" class="button button-primary">%1$s</button> <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Save', 'wpcredits-program-manager' ),
			esc_url( $this->tab_url( 'sponsors' ) ),
			esc_html__( 'Cancel', 'wpcredits-program-manager' )
		);
		echo '</form>';

		// Not a renewal's own form, while the row owes a report: the Tier box's button inside the form
		// above posts it through its form attribute, since one form cannot sit inside another.
		if ( class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			WPCPM_Sponsor_Report_Screen::render_not_renewal_form( $key, $row );
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the sponsor's key, 2: its listing handle. */
					__( 'Key: %1$s; listing handle: %2$s', 'wpcredits-program-manager' ),
					$key,
					(string) $row['slug']
				)
			)
		);
		echo '</div>';

		return true;
	}

	/**
	 * Add a sponsor, under the index table: the fields of the Edit form but the mentors, whose
	 * picker is the Edit form's once the sponsor exists.
	 *
	 * The form names the Sponsors tab, so a press the handler refuses comes back to it
	 * (`redirect_back()`); a sponsor added goes on to its Edit form, which the same tab draws in
	 * place of its cards (`handle_add()`).
	 */
	private function render_add() {
		echo '<h3 id="wpcpm-sponsor-add">' . esc_html__( 'Add a sponsor', 'wpcredits-program-manager' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'For a company that never applied on the site. Nothing else happens when it is added: once its status is Approved, its account is made with Create account on the Accounts tab\'s No account view.', 'wpcredits-program-manager' ) . '</p>';
		printf(
			'<form method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Adding', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_ADD );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_ADD ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( 'sponsors' ) );
		// A refused Add: every field is drawn as it was posted (`keep_typing()`).
		self::render_fields( 'add', array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'status' => self::STATUS_ADDED ) ), WPCPM_Typed_Text::kept( self::typed_form( '' ) ) );
		printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p>', esc_html__( 'Add the sponsor', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * The Add and Edit forms' fields, one wp-admin form table in the order of `EDITABLE`, each
	 * holding what the record holds, a value no choice offers included, so a save nobody touched
	 * sends back what is there (see `clean_fields()`).
	 *
	 * The typed texts of `TYPED` are drawn back as the manager typed them, by the rules every box of
	 * typed text uses (`WPCPM_Typed_Text`): a text input through `esc_attr( attr_text() )` and the
	 * notes through `esc_textarea( typed_text() )`, each with the `maxlength` `drawn_limit()` gives it.
	 * Drawn as stored, a box would show the cleaner's entities as typed characters, and one that holds
	 * "We <3 our team > all" would post a "tag" the cleaner drops, words and all. The website is drawn
	 * through `esc_attr( kept_attr() )`, so a stored entity posts back as stored. The contact address
	 * is drawn as stored, through `esc_attr()` alone, so its address box shows it with each entity
	 * read and the browser takes it as an address, and posted back so it is untouched
	 * (`clean_field()`). A line break inside a stored one-line text or website is drawn as a space
	 * (`WPCPM_Typed_Text::one_line()`), which the box would otherwise drop. The profile card on the
	 * Sponsor Dashboard draws and reads its Website, Contact person and Contact email by the same
	 * rules (`WPCPM_Sponsor_Profile`), so a box left as either screen drew it changes nothing.
	 *
	 * After a refused save, the Add or Edit form is drawn as it was posted (`keep_typing()`): every
	 * box with what was typed, exactly, a text input through `esc_attr( kept_attr() )` and the notes
	 * through `esc_textarea()`, in the room it was drawn with; every select, the Term ends day and the
	 * extra events with what was chosen; and every list with the boxes that were ticked. Which rows are
	 * drawn is the record's to say, as it was for the form that was posted.
	 *
	 * @param string $form `add` or `edit`: each form's field IDs carry its name, so the two never
	 *                     share one, and only the Edit form draws the Tier box and the mentors.
	 * @param array  $row  The record, or the new record's defaults.
	 * @param array  $kept What a refused save of the form kept, field => what was typed or the ticked
	 *                     values of a list, or an empty array.
	 */
	private static function render_fields( $form, array $row, array $kept = array() ) {
		$id      = 'wpcpm-' . $form . '-';
		$allowed = array( 'span' => array( 'class' => array() ) );
		$typed   = static function ( $field ) use ( $kept ) {
			return isset( $kept[ $field ] ) && is_string( $kept[ $field ] ) ? $kept[ $field ] : null;
		};
		$ticked  = static function ( $field, array $stored ) use ( $kept ) {
			return isset( $kept[ $field ] ) && is_array( $kept[ $field ] ) ? $kept[ $field ] : $stored;
		};
		// A text input's value, ready for `esc_attr()`: what was typed, or what the record holds. A
		// typed text of `TYPED` is read back to what the manager typed. The website has each "&"
		// written `&amp;` (`kept_attr()`), since `esc_attr()` keeps an entity it finds and the browser
		// would post back the character in place of the stored entity. A line break inside a stored
		// name, contact person, funded line or website is drawn as a space
		// (`WPCPM_Typed_Text::one_line()`), which the box would otherwise drop. The contact address is
		// drawn as stored: its box is an address box, whose browser refuses the whole form when the
		// value is no address, and an entity shown as its characters, `&amp;` say, is none; so the box
		// shows the address with each entity read, and a post-back that reads as the stored address is
		// the box untouched (`clean_field()`). An address's box drops white space itself. The Term ends
		// day is drawn as stored too: a date box takes a date and nothing else.
		$value = static function ( $field ) use ( $row, $typed ) {
			if ( null !== $typed( $field ) ) {
				return WPCPM_Typed_Text::kept_attr( $typed( $field ) );
			}

			$stored = (string) $row[ $field ];

			if ( isset( self::TYPED[ $field ] ) ) {
				return WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::attr_text( $stored ) );
			}

			return 'website' === $field ? WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::kept_attr( $stored ) ) : $stored;
		};

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row"><label for="%1$sname">%2$s %3$s</label></th><td><input type="text" class="regular-text" id="%1$sname" name="wpcpm_name" value="%4$s" maxlength="%5$d" required /></td></tr>',
			esc_attr( $id ),
			esc_html__( 'Name', 'wpcredits-program-manager' ),
			wp_kses( '<span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>', $allowed ),
			esc_attr( $value( 'name' ) ),
			(int) WPCPM_Typed_Text::drawn_limit( (string) $row['name'], self::MAX_LINE )
		);

		// `inputmode="url"` on a text field, as the application form draws its own: a url field
		// refuses a scheme-less address, and example.com is how most people write one.
		printf(
			'<tr><th scope="row"><label for="%1$swebsite">%2$s</label></th><td><input type="text" inputmode="url" class="regular-text" id="%1$swebsite" name="wpcpm_website" value="%3$s" /></td></tr>',
			esc_attr( $id ),
			esc_html__( 'Website', 'wpcredits-program-manager' ),
			esc_attr( $value( 'website' ) )
		);

		self::select_row( $id . 'status', 'status', __( 'Status', 'wpcredits-program-manager' ), array_combine( WPCPM_Sponsors_Index::STATUSES, WPCPM_Sponsors_Index::STATUSES ), $row['status'], $typed( 'status' ) );

		// The test sponsor's flag (1.123.18), on both forms: a hidden 0 before the box, as each list
		// posts its hidden empty value, so a form that drew it always says what it holds.
		$test = null !== $typed( 'test' ) ? '1' === $typed( 'test' ) : ! empty( $row['test'] );

		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="wpcpm_test" value="0" /><label for="%2$stest"><input type="checkbox" id="%2$stest" name="wpcpm_test" value="1"%3$s /> %4$s</label><p class="description">%5$s</p></td></tr>',
			esc_html__( 'Test sponsor', 'wpcredits-program-manager' ),
			esc_attr( $id ),
			$test ? ' checked' : '',
			esc_html__( 'Test sponsor: not listed publicly', 'wpcredits-program-manager' ),
			esc_html__( 'A test sponsor is left out of the Partners page, the logo strip, the handbook listing and the public feed, and its offers, posts and events are shown to no student or mentor. Its own accounts still see its Sponsor Dashboard.', 'wpcredits-program-manager' )
		);

		printf(
			'<tr><th scope="row"><label for="%1$scontact-person">%2$s</label></th><td><input type="text" class="regular-text" id="%1$scontact-person" name="wpcpm_contact_person" value="%3$s" maxlength="%4$d" /></td></tr>',
			esc_attr( $id ),
			esc_html__( 'Contact person', 'wpcredits-program-manager' ),
			esc_attr( $value( 'contact_person' ) ),
			(int) WPCPM_Typed_Text::drawn_limit( (string) $row['contact_person'], self::MAX_LINE )
		);
		printf(
			'<tr><th scope="row"><label for="%1$scontact-email">%2$s</label></th><td><input type="email" class="regular-text" id="%1$scontact-email" name="wpcpm_contact_email" value="%3$s" /><p class="description">%4$s</p></td></tr>',
			esc_attr( $id ),
			esc_html__( 'Contact email', 'wpcredits-program-manager' ),
			esc_attr( $value( 'contact_email' ) ),
			esc_html__( 'Create account makes the account at this address and sends its invitation here.', 'wpcredits-program-manager' )
		);

		$none = array( '' => __( 'Not chosen', 'wpcredits-program-manager' ) );

		self::select_row( $id . 'option', 'option', __( 'Sponsorship option', 'wpcredits-program-manager' ), $none + array_combine( WPCPM_Sponsor_Application::OPTION_CHOICES, WPCPM_Sponsor_Application::OPTION_CHOICES ), $row['option'], $typed( 'option' ) );
		self::checkboxes_row( 'support', __( 'Would like to support', 'wpcredits-program-manager' ), array_combine( WPCPM_Sponsor_Interests::CHOICES, WPCPM_Sponsor_Interests::CHOICES ), $ticked( 'support', (array) $row['support'] ) );

		if ( 'edit' === $form ) {
			self::render_interests_row( $row );
		}
		self::select_row( $id . 'product-type', 'product_type', __( 'Type of product', 'wpcredits-program-manager' ), $none + array_combine( WPCPM_Sponsor_Profile::CHOICES['Type of product'], WPCPM_Sponsor_Profile::CHOICES['Type of product'] ), $row['product_type'], $typed( 'product_type' ) );

		$managers = array( '' => __( 'Nobody yet', 'wpcredits-program-manager' ) );

		foreach ( WPCPM_Institutions::managers() as $user ) {
			$managers[ (string) $user->ID ] = $user->display_name;
		}

		// A manager assigned before they lost the program capability stays assigned, and says so,
		// until somebody chooses another: in the words the index and the terms card name them in.
		$current = (string) $row['manager'];

		if ( '' !== $current && ! array_key_exists( $current, $managers ) ) {
			$managers[ $current ] = WPCPM_Sponsors_Index::manager_label( $row );
		}

		self::select_row( $id . 'manager', 'manager', __( 'Assigned Administrator', 'wpcredits-program-manager' ), $managers, $current, $typed( 'manager' ) );

		if ( 'edit' === $form ) {
			// The Tier box: Edit's alone (design section 4), drawn after the program manager and
			// before the sponsored mentors.
			self::select_row( $id . 'tier', 'tier', __( 'Tier', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::labels(), '' === $row['tier'] ? WPCPM_Sponsor_Tiers::MENTOR : $row['tier'], $typed( 'tier' ) );

			$tier_notes = array( __( 'The sponsor sees its tier and what the tier includes only while Sponsor tiers is switched on in Settings.', 'wpcredits-program-manager' ) );

			// The answer in the words the application's question offered it: "Not sure yet" is one
			// of its answers and no tier, so the ladder's label() would read it as the floor, and a
			// value the question never offered is no request at all. Read without a guard, as the
			// Sponsorship option row above reads the choices the form's support question offered
			// until 1.123.12.
			$offered   = WPCPM_Sponsor_Application::tier_choices();
			$requested = isset( $offered[ $row['tier_requested'] ] ) ? $offered[ $row['tier_requested'] ] : '';

			if ( '' !== $requested ) {
				array_unshift(
					$tier_notes,
					sprintf(
						/* translators: %s: the tier requested on the sponsor's application, or its "Not sure yet". */
						__( 'Requested on the application: %s.', 'wpcredits-program-manager' ),
						$requested
					)
				);
			}

			printf(
				'<tr><th scope="row">%1$s</th><td><p class="description">%2$s</p></td></tr>',
				'',
				esc_html( implode( ' ', $tier_notes ) )
			);

			printf(
				'<tr><th scope="row"><label for="%1$stier-until">%2$s</label></th><td><input type="date" id="%1$stier-until" name="wpcpm_tier_until" value="%3$s" /><p class="description">%4$s</p></td></tr>',
				esc_attr( $id ),
				esc_html__( 'Term ends', 'wpcredits-program-manager' ),
				esc_attr( $value( 'tier_until' ) ),
				esc_html__( 'The last day of the term. Empty means no term. A Friend has no term, so the day is cleared for one. From the day after the term ends the sponsor reads as a Friend, and the daily run applies that from the day after the grace date in Settings.', 'wpcredits-program-manager' )
			);

			self::render_owed_row( $row );

			// For a tier that includes the co-hosted event, the one the number adds to, read on the
			// tier the select holds, so a Champion whose term has ended can be renewed and granted
			// more in one save.
			if ( in_array( WPCPM_Sponsor_Tiers::EVENT_CO_HOSTED, WPCPM_Sponsor_Tiers::events( (string) $row['tier'] ), true ) ) {
				self::render_extra_row( $id, $row, $typed( 'event_allowance' ) );
			}

			printf(
				'<tr><th scope="row"><label for="%1$sfunded-line">%2$s</label></th><td><input type="text" class="regular-text" id="%1$sfunded-line" name="wpcpm_funded_line" value="%3$s" maxlength="%4$d" /><p class="description">%5$s</p></td></tr>',
				esc_attr( $id ),
				esc_html__( 'What the sponsorship funds', 'wpcredits-program-manager' ),
				esc_attr( $value( 'funded_line' ) ),
				(int) WPCPM_Typed_Text::drawn_limit( (string) $row['funded_line'], self::MAX_LINE ),
				esc_html__( 'Shown from Backer up on the sponsor\'s dashboard and in its report, never on the Partners page: one line, for example the mentor stipends of the Developer Track.', 'wpcredits-program-manager' )
			);

			$linked  = array_map( 'strval', (array) $row['mentors'] );
			$mentors = self::mentor_choices( $linked );

			if ( empty( $mentors ) ) {
				printf(
					'<tr><th scope="row">%1$s</th><td><p class="description">%2$s</p></td></tr>',
					esc_html__( 'Sponsored mentors', 'wpcredits-program-manager' ),
					esc_html__( 'No mentor can be chosen until the mentors index holds one. It is filled on the Mentors screen.', 'wpcredits-program-manager' )
				);
			} else {
				self::checkboxes_row( 'mentors', __( 'Sponsored mentors', 'wpcredits-program-manager' ), $mentors, $ticked( 'mentors', $linked ) );
			}

			if ( WPCPM_Sponsor_Tiers::PARTNER === WPCPM_Sponsor_Tiers::effective( $row ) ) {
				self::render_tracks_row( (string) $row['record_id'] );
			}
		}

		printf(
			'<tr><th scope="row"><label for="%1$snotes">%2$s</label></th><td><textarea id="%1$snotes" name="wpcpm_notes" rows="4" class="large-text" maxlength="%3$d">%4$s</textarea><p class="description">%5$s</p></td></tr>',
			esc_attr( $id ),
			esc_html__( 'Notes', 'wpcredits-program-manager' ),
			(int) WPCPM_Typed_Text::drawn_limit( (string) $row['notes'], self::MAX_NOTES ),
			// The parser drops one line feed right after the opening tag, so a kept typing, which can
			// begin with a line break, is given one of its own first.
			null !== $typed( 'notes' ) ? "\n" . esc_textarea( $typed( 'notes' ) ) : esc_textarea( WPCPM_Typed_Text::typed_text( (string) $row['notes'] ) ),
			esc_html__( 'Private: shown on this screen only.', 'wpcredits-program-manager' )
		);

		echo '</tbody></table>';
	}

	/**
	 * The Tier box's read-only Report due row, under Term ends, while a save that moved the day left
	 * the term it moved off owed its report (`report_owed`), with Not a renewal for a day that was
	 * corrected rather than renewed.
	 *
	 * The button belongs to a form of its own, drawn after the Edit form by the report screen's
	 * class, and posts it through its `form` attribute: the row sits inside the Edit form, and one
	 * form cannot sit inside another. That form carries the question the press asks. Nothing is
	 * drawn while nothing is owed.
	 *
	 * @param array $row The sponsor's row.
	 */
	private static function render_owed_row( array $row ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Report_Screen' ) ) {
			return;
		}

		$due = WPCPM_Sponsor_Report_Screen::owed_sentence( $row );

		if ( '' === $due ) {
			return;
		}

		printf(
			'<tr><th scope="row">%1$s</th><td><p>%2$s</p><p><button type="submit" form="%3$s" class="button">%4$s</button></p><p class="description">%5$s</p></td></tr>',
			esc_html__( 'Report due', 'wpcredits-program-manager' ),
			esc_html( $due ),
			esc_attr( WPCPM_Sponsor_Report_Screen::NOT_RENEWAL_FORM ),
			esc_html__( 'Not a renewal', 'wpcredits-program-manager' ),
			esc_html__( 'The term\'s last day was moved before its report existed, so that term keeps its report: the daily run drafts it the day after the term ends. When the day was moved to correct it rather than to renew, Not a renewal says no report is owed.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The Tier box's Extra events this term: how many co-hosted events the term that ends on the Term
	 * ends day holds beyond the one the tier includes, drawn holding what the row grants that term
	 * while it runs (`granted()`), so a save that leaves it alone sends that back.
	 *
	 * A number the row keeps for another day, a renewal having moved the day since, is no longer this
	 * term's, and one kept for the Term ends day once that day has passed can no longer be used: the
	 * box holds none for either, and a sentence under the box says what the other term was granted,
	 * as the term that ended once its last day has passed, until the box is changed. After a refused
	 * save, the box holds the number that was posted (`keep_typing()`).
	 *
	 * @param string      $id   The form's ID prefix.
	 * @param array       $row  The sponsor's row.
	 * @param string|null $kept The number a refused save of the form posted, as posted, or null.
	 */
	private static function render_extra_row( $id, array $row, $kept = null ) {
		$granted   = self::granted( $row );
		$allowance = is_array( $row['event_allowance'] ) ? $row['event_allowance'] : array();
		$other     = '';

		if ( 0 === $granted && isset( $allowance['until'], $allowance['extra'] ) && WPCPM_Sponsor_Tiers::is_date( (string) $allowance['until'] ) ) {
			$count = (int) $allowance['extra'];
			$day   = WPCPM_Sponsor_Tiers::format_day( (string) $allowance['until'] );
			$other = strcmp( (string) $allowance['until'], WPCPM_Sponsor_Tiers::today() ) < 0
				/* translators: 1: how many more co-hosted events were granted, 2: the last day of the term they were granted for. */
				? sprintf( _n( '%1$s more event was granted for the term that ended on %2$s.', '%1$s more events were granted for the term that ended on %2$s.', $count, 'wpcredits-program-manager' ), number_format_i18n( $count ), $day )
				/* translators: 1: how many more co-hosted events were granted, 2: the last day of the term they were granted for. */
				: sprintf( _n( '%1$s more event was granted for the term to %2$s.', '%1$s more events were granted for the term to %2$s.', $count, 'wpcredits-program-manager' ), number_format_i18n( $count ), $day );
		}

		printf(
			'<tr><th scope="row"><label for="%1$sevent-allowance">%2$s</label></th><td><input type="number" id="%1$sevent-allowance" name="wpcpm_event_allowance" value="%3$s" min="0" max="%4$d" step="1" class="small-text" />',
			esc_attr( $id ),
			esc_html__( 'Extra events this term', 'wpcredits-program-manager' ),
			esc_attr( null !== $kept ? WPCPM_Typed_Text::kept_attr( $kept ) : (string) (int) $granted ),
			(int) self::MAX_EXTRA_EVENTS
		);

		if ( '' !== $other ) {
			printf( '<p>%s</p>', esc_html( $other ) );
		}

		printf(
			'<p class="description">%s</p></td></tr>',
			esc_html__( 'Co-hosted events this sponsor may hold in the term that ends on the Term ends day, beyond the one its tier includes. A renewal that moves that day and leaves this box as it is starts the new term with none. Without a term nothing is saved here: a sponsor with no term waits only for its last event to take place.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The Tier box's read-only Tracks row, for an effective Partner alone: which live tracks name
	 * it, each linked to that track's own page on the Track Builder, where a program manager
	 * names or drops a partner. Never editable here, so the two screens cannot disagree about who
	 * is named (the sponsor tiers design, section 4).
	 *
	 * @param string $record The sponsor's key.
	 */
	private static function render_tracks_row( $record ) {
		$links = array();

		if ( class_exists( 'WPCPM_Tracks' ) ) {
			foreach ( WPCPM_Tracks::partner_tracks( $record ) as $track ) {
				// Required unconditionally on the site, as the Track Builder's own screens are;
				// this guard is only for a suite that loads this one file.
				$links[] = class_exists( 'WPCPM_Track_Builder' )
					? sprintf( '<a href="%1$s">%2$s</a>', esc_url( WPCPM_Track_Builder::track_url( (int) $track['post'] ) ), esc_html( $track['label'] ) )
					: esc_html( $track['label'] );
			}
		}

		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s<p class="description">%3$s</p></td></tr>',
			esc_html__( 'Tracks', 'wpcredits-program-manager' ),
			empty( $links )
				? esc_html__( 'None yet. A track names its partners on the Track Builder; they show here once the track is published.', 'wpcredits-program-manager' )
				: wp_kses( implode( ', ', $links ), array( 'a' => array( 'href' => array() ) ) ),
			esc_html__( 'Set on each track\'s page on the Track Builder; a partner whose term ends stays named there and stops showing on the site.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * One select of the Add and Edit forms. A value the record holds that no option offers is
	 * drawn as an option of its own, selected, so a save nobody touched sends it back. After a
	 * refused save, the option that was chosen is the one selected, among the same options.
	 *
	 * @param string      $id      The select's ID.
	 * @param string      $field   The field, a key of `EDITABLE`.
	 * @param string      $label   The label.
	 * @param array       $options Value => label, in order.
	 * @param string      $current What the record holds.
	 * @param string|null $chosen  What a refused save of the form chose, or null.
	 */
	private static function select_row( $id, $field, $label, array $options, $current, $chosen = null ) {
		$current = (string) $current;

		if ( ! array_key_exists( $current, $options ) ) {
			$options = array( $current => '' === $current ? __( 'Not set yet', 'wpcredits-program-manager' ) : $current ) + $options;
		}

		$current = null === $chosen ? $current : (string) $chosen;

		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><select id="%1$s" name="wpcpm_%3$s">',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $field )
		);

		foreach ( $options as $value => $text ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $value ),
				(string) $value === $current ? ' selected' : '',
				esc_html( $text )
			);
		}

		echo '</select></td></tr>';
	}

	/**
	 * One list of boxes of the Add and Edit forms, after the hidden empty value that says the list
	 * was drawn (see `posted_fields()`).
	 *
	 * @param string   $field   `support` or `mentors`.
	 * @param string   $label   The row's label.
	 * @param array    $choices Value => label, in order.
	 * @param string[] $checked The values ticked.
	 */
	private static function checkboxes_row( $field, $label, array $choices, array $checked ) {
		printf( '<tr><th scope="row">%1$s</th><td><fieldset><legend class="screen-reader-text">%1$s</legend>', esc_html( $label ) );
		printf( '<input type="hidden" name="wpcpm_%s[]" value="" />', esc_attr( $field ) );

		foreach ( $choices as $value => $text ) {
			printf(
				'<label><input type="checkbox" name="wpcpm_%1$s[]" value="%2$s"%3$s /> %4$s</label><br />',
				esc_attr( $field ),
				esc_attr( (string) $value ),
				in_array( (string) $value, $checked, true ) ? ' checked' : '',
				esc_html( $text )
			);
		}

		echo '</fieldset></td></tr>';
	}

	/**
	 * The Edit form's read-only row of what the company told the program (1.123.22): the history its
	 * record keeps in `interests`, one dated line per message, as `WPCPM_Sponsor_Interests::file()`
	 * appended it, newest first, each line escaped. Until 1.123.22 the Sponsor Dashboard's Offer more
	 * card listed it, to the company and to an Administrator through the switcher; with that card gone
	 * this is where it is read, beside the ticks of Would like to support, which that card wrote. The
	 * lines come from the Offer more card while the dashboard had one and from Talk to your program
	 * contact. Nothing is drawn for a record that holds none, and nothing here is posted: the history
	 * is the company's words, never edited on this form.
	 *
	 * @param array $row The record.
	 */
	private static function render_interests_row( array $row ) {
		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', isset( $row['interests'] ) ? (string) $row['interests'] : '' ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$lines[] = trim( $line );
			}
		}

		if ( array() === $lines ) {
			return;
		}

		printf( '<tr><th scope="row">%s</th><td><ul>', esc_html__( 'Interests', 'wpcredits-program-manager' ) );

		foreach ( array_reverse( $lines ) as $line ) {
			printf( '<li>%s</li>', esc_html( $line ) );
		}

		printf(
			'</ul><p class="description">%s</p></td></tr>',
			esc_html__( 'What the company told the program from its Sponsor Dashboard, newest first: on the Offer more card while the dashboard had one, and through Talk to your program contact. Read only: it is kept on the company\'s record as it was sent.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The mentors the picker offers: the mentors index, the Active mentors first and then by name,
	 * each "Name (status)"; after them any mentor the record links whom the index does not hold,
	 * by record ID, which a save keeps while it stays ticked (see `clean_fields()`).
	 *
	 * @param string[] $linked The mentor record IDs the sponsor's record links.
	 * @return array<string, string> Record ID => label.
	 */
	private static function mentor_choices( array $linked ) {
		$index = array();

		foreach ( WPCPM_Mentors_Sync::sponsorship() as $record => $facts ) {
			$name   = isset( $facts['name'] ) ? trim( (string) $facts['name'] ) : '';
			$status = isset( $facts['status'] ) ? trim( (string) $facts['status'] ) : '';

			$index[] = array(
				'record' => (string) $record,
				'name'   => '' === $name ? (string) $record : $name,
				'status' => $status,
			);
		}

		// Active first, the word WPCPM_Sponsor_Mentors::looking() reads, then by name.
		usort(
			$index,
			static function ( $a, $b ) {
				$active = (int) ( 'Active' !== $a['status'] ) - (int) ( 'Active' !== $b['status'] );

				return 0 !== $active ? $active : strcasecmp( $a['name'], $b['name'] );
			}
		);

		$choices = array();

		foreach ( $index as $mentor ) {
			$choices[ $mentor['record'] ] = sprintf(
				/* translators: 1: a mentor's name, 2: the mentor's status in the program. */
				__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
				$mentor['name'],
				'' === $mentor['status'] ? __( 'no status', 'wpcredits-program-manager' ) : $mentor['status']
			);
		}

		foreach ( $linked as $record ) {
			if ( ! isset( $choices[ $record ] ) ) {
				$choices[ $record ] = sprintf(
					/* translators: %s: a mentor's record ID. */
					__( '%s (not in the mentors index)', 'wpcredits-program-manager' ),
					$record
				);
			}
		}

		return $choices;
	}

	/**
	 * Every live account, grouped by the sponsor it acts for, one query for the whole render.
	 *
	 * `render_index()` and `render_members()` used to call `WPCPM_Sponsor_Members::members_of()`
	 * once per sponsor row, each its own `get_users()` query; grouping `live_accounts()`'s one
	 * query by `sponsor_of()` here does the same job in one pass over one list.
	 *
	 * @return array<string, WP_User[]> Sponsor key to its accounts.
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
	 * Every sponsor, with its status, manager, accounts and logo, and in the last column Edit on every
	 * row, which opens the sponsor's Edit form on this tab, and its Manage accounts, which opens its
	 * accounts on the Accounts tab, where they are attached and removed and its posting is switched;
	 * then the form that adds one. A screen reader hears the column's heading, Edit and Manage
	 * accounts, and the sponsor's name after each link's words. A sponsor's account is created on the
	 * Accounts tab's No account view.
	 *
	 * @param array $rows     The index rows.
	 * @param array $accounts From `accounts_by_sponsor()`.
	 */
	private function render_index( array $rows, array $accounts ) {
		echo '<div class="wpcpm-card">';
		printf( '<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>', esc_html__( 'Sponsors', 'wpcredits-program-manager' ), esc_html( number_format_i18n( count( $rows ) ) ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No sponsors yet. Add one below or approve an application.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			self::render_grace_preview();
			$this->render_table( $rows, $accounts );
		}

		$this->render_add();

		echo '</div>';
	}

	/**
	 * Whether the listing for the handbook is shown: while Sponsor tiers is on in Settings, the launch
	 * switch every sponsor-facing surface checks (`WPCPM_Sponsor_Tiers::live()`), and while the
	 * listing's class is there to draw it.
	 *
	 * The one test the Handbook listing tab is in the bar under (`tab_labels()`) and the box is drawn
	 * under (`render_listing_box()`), so neither is shown without the other. class_exists() is a
	 * defensive guard, not a load order fix - the listing class always loads before this one - so a
	 * site missing the file for some other reason loses the tab and the box rather than the whole
	 * screen.
	 *
	 * @return bool
	 */
	private static function listing_shown() {
		return class_exists( 'WPCPM_Sponsors_Listing' ) && WPCPM_Sponsor_Tiers::live();
	}

	/**
	 * The listing for the handbook: the same list the Partners page draws, in plain markup with
	 * absolute addresses, for a manager to paste into the handbook or hand to the WordPress
	 * Foundation. The Handbook listing tab's one card (`render_tab_listing()`), the sponsors index
	 * made public.
	 *
	 * Gated on the launch switch, like every other public and sponsor-facing surface this
	 * release adds: the HTML this box shows names the tiers, so it stays off the page, manager
	 * or not, until the program announces them. The test is the tab's own (`listing_shown()`), so
	 * the box is drawn wherever the tab is and nowhere while the switch is off.
	 */
	private function render_listing_box() {
		if ( ! self::listing_shown() ) {
			return;
		}

		echo '<div class="wpcpm-card" id="wpcpm-sponsors-listing-box">';
		echo '<h2>' . esc_html__( 'Listing for the handbook', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html__( 'The partners as the Partners page lists them, in alphabetical order, as plain HTML with the logos\' public addresses: paste it into the handbook or the WordPress Foundation\'s page.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( WPCPM_Sponsors_Index::approved() ) ) {
			echo '<p>' . esc_html__( 'No Approved sponsor yet.', 'wpcredits-program-manager' ) . '</p>';
		}

		printf( '<textarea id="wpcpm-sponsors-listing-html" class="large-text code" rows="12" readonly="readonly">%s</textarea>', esc_textarea( WPCPM_Sponsors_Listing::plain() ) );
		printf(
			'<p><button type="button" class="button" data-wpcpm-copy="#wpcpm-sponsors-listing-html" data-wpcpm-copied="%1$s" data-wpcpm-select-hint="%2$s">%3$s</button> <span class="wpcpm-copy__status" role="status" aria-live="polite"></span></p>',
			esc_attr__( 'Copied.', 'wpcredits-program-manager' ),
			esc_attr__( 'Selected. Copy it with your keyboard.', 'wpcredits-program-manager' ),
			esc_html__( 'Copy', 'wpcredits-program-manager' )
		);
		echo '</div>';
	}

	/**
	 * The grace preview above the index table: the published posts the daily run would put to
	 * dormant on the day after the grace date, under the tiers and terms as they are set now, as
	 * `WPCPM_Sponsor_Tiers_Run::preview()` plans it without applying anything. Drawn on or before
	 * the grace date whether Sponsor tiers is on or off in Settings, because managers set the tiers
	 * before the launch and plan from this; once the date has passed, the run itself is what
	 * happens and the paragraph is not drawn.
	 */
	private static function render_grace_preview() {
		if ( ! WPCPM_Sponsor_Tiers::in_grace() ) {
			return;
		}

		$preview = WPCPM_Sponsor_Tiers_Run::preview();
		$day     = WPCPM_Sponsor_Tiers::format_day( $preview['day'] );

		if ( empty( $preview['sponsors'] ) ) {
			/* translators: %s: the day after the grace date. */
			$text = sprintf( __( 'Under the tiers as set, nothing goes dormant on %s.', 'wpcredits-program-manager' ), $day );
		} else {
			$names = array();

			foreach ( $preview['sponsors'] as $sponsor ) {
				$names[] = sprintf(
					/* translators: 1: company name, 2: how many of its published posts go dormant, such as "1 post". */
					__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
					$sponsor['name'],
					/* translators: %d: how many of the sponsor's published posts go dormant. */
					sprintf( _n( '%d post', '%d posts', $sponsor['posts'], 'wpcredits-program-manager' ), $sponsor['posts'] )
				);
			}

			$text = sprintf(
				/* translators: 1: the day after the grace date, 2: how many published posts go dormant, a phrase like 2 published posts, 3: each sponsor with how many of its posts go dormant. */
				__( 'Under the tiers as set, on %1$s, the daily run puts %2$s to dormant: %3$s.', 'wpcredits-program-manager' ),
				$day,
				/* translators: %d: how many published posts go dormant, across the sponsors. */
				sprintf( _n( '%d published post', '%d published posts', $preview['posts'], 'wpcredits-program-manager' ), $preview['posts'] ),
				implode( '; ', $names )
			);
		}

		if ( ! $preview['live'] ) {
			$text .= ' ' . __( 'Sponsor tiers is off in Settings: nothing goes dormant until it is on.', 'wpcredits-program-manager' );
		}

		echo '<p class="description">' . esc_html( $text ) . '</p>';
	}

	/**
	 * The index table itself.
	 *
	 * @param array $rows     The index rows.
	 * @param array $accounts From `accounts_by_sponsor()`.
	 */
	private function render_table( array $rows, array $accounts ) {
		echo '<table class="wpcpm-table widefat striped"><thead><tr>';
		foreach ( array( __( 'Sponsor', 'wpcredits-program-manager' ), __( 'Status', 'wpcredits-program-manager' ), __( 'Tier', 'wpcredits-program-manager' ), __( 'Product', 'wpcredits-program-manager' ), __( 'Program contact', 'wpcredits-program-manager' ), __( 'Accounts', 'wpcredits-program-manager' ), __( 'Logo', 'wpcredits-program-manager' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		// The column of links is named for a screen reader and drawn without a visible heading.
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Edit and Manage accounts', 'wpcredits-program-manager' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $record => $row ) {
			$members = isset( $accounts[ $record ] ) ? $accounts[ $record ] : array();
			$logo    = WPCPM_Sponsors_Index::logo_record( $record );
			$name    = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
			$link    = self::dashboard_link( $record );

			echo '<tr>';
			echo '<td>' . ( '' !== $link ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html( $name ) ) : esc_html( $name ) );

			// A test sponsor says so on its row (1.123.18): it is listed nowhere public.
			if ( ! empty( $row['test'] ) ) {
				echo ' <span class="description">' . esc_html__( '(test sponsor, not listed publicly)', 'wpcredits-program-manager' ) . '</span>';
			}

			echo '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( self::tier_cell( $row ) ) . '</td>';
			echo '<td>' . esc_html( $row['product_type'] ) . '</td>';
			echo '<td>' . esc_html( WPCPM_Sponsors_Index::manager_label( $row ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( count( $members ) ) ) . '</td>';
			// The row's own `logo` key left it in 1.111.0: the logo option is the only record of
			// it now, so the column reads that alone rather than a fact the row no longer carries.
			echo '<td>' . esc_html( $logo['colour'] > 0 ? __( 'On the site', 'wpcredits-program-manager' ) : __( 'None', 'wpcredits-program-manager' ) ) . '</td>';
			echo '<td>';
			printf(
				'<a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a> ',
				esc_url( self::edit_url( (string) $record ) ),
				esc_html__( 'Edit', 'wpcredits-program-manager' ),
				esc_html( $name )
			);
			printf(
				'<a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url( self::accounts_view_url( $record ) ),
				esc_html__( 'Manage accounts', 'wpcredits-program-manager' ),
				/* translators: %s: the sponsor's name, read out after "Manage accounts" so each row's link says whose accounts it opens. */
				esc_html( sprintf( __( 'of %s', 'wpcredits-program-manager' ), $name ) )
			);
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The index's Tier column: the effective tier alone while there is no term to name, the tier
	 * with the last day of its term while that term is open, and a lapsed term read back as a
	 * Friend, with the tier it used to be and the day it ended (the daily run's mail, the
	 * Sponsorship card and the Administrator Dashboard print the same day through
	 * `WPCPM_Sponsor_Tiers::format_day()`).
	 *
	 * @param array $row A row.
	 * @return string
	 */
	private static function tier_cell( array $row ) {
		$tier  = isset( $row['tier'] ) ? (string) $row['tier'] : '';
		$until = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';

		if ( ! WPCPM_Sponsor_Tiers::is_date( $until ) ) {
			return WPCPM_Sponsor_Tiers::label( WPCPM_Sponsor_Tiers::effective( $row ) );
		}

		$day = WPCPM_Sponsor_Tiers::format_day( $until );

		if ( WPCPM_Sponsor_Tiers::effective( $row ) === $tier ) {
			return sprintf(
				/* translators: 1: a sponsor tier's name, 2: the last day of its term. */
				__( '%1$s until %2$s', 'wpcredits-program-manager' ),
				WPCPM_Sponsor_Tiers::label( $tier ),
				$day
			);
		}

		return sprintf(
			/* translators: 1: the tier a sponsor's term used to be, 2: the day the term ended. */
			__( 'Friend (%1$s ended %2$s)', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Tiers::label( $tier ),
			$day
		);
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
		echo '<p class="description">' . esc_html__( 'What sponsors said they would like to support, from the Sponsor Dashboard. Each was mailed to the assigned Administrator when it was sent.', 'wpcredits-program-manager' ) . '</p>';

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
		printf( '<li>%s</li>', esc_html__( 'It commits the program to nothing an Administrator has not agreed to.', 'wpcredits-program-manager' ) );
		echo '</ul>';

		$flags = array_values( array_filter( array_map( 'strval', (array) $facts['flags'] ) ) );

		printf(
			'<p class="wpcpm-review__flags">%s</p>',
			esc_html(
				$flags
					? sprintf(
						/* translators: %s: what the courtesy scan noticed, in words, each with the PDF's own name for it in brackets. */
						__( 'The scan noticed these in the file: %s.', 'wpcredits-program-manager' ),
						WPCPM_Pdf_Check::in_words( $flags )
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
	 * @param string $record Sponsor key.
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
					/* translators: %s: what this site holds. */
					__( 'This site: %s.', 'wpcredits-program-manager' ),
					self::agreement_word( $state, (string) $summary['accepted_at'] )
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
				__( 'Why it is out of force, in your own words. This is emailed to everybody at the company exactly as you write it. Nothing about their dashboard changes.', 'wpcredits-program-manager' ),
				'',
				// A refused press comes back with the note as it was typed.
				WPCPM_Sponsor_Agreement::kept_revoke_note( (int) $summary['agreement_id'] )
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
	 * @param string      $action  The `admin_post_` action.
	 * @param string      $nonce   The nonce action, keyed to the post or the record.
	 * @param string      $css     The form's classes.
	 * @param string      $busy    What the pressed control says while the request is in flight.
	 * @param array       $hidden  Hidden field name to value.
	 * @param string      $label   The button.
	 * @param string      $confirm A confirm sentence, or '' for none.
	 * @param string      $note    A note box's label, or '' for no note box.
	 * @param string      $drive   A sponsor key when this form asks for a Drive link, else ''.
	 * @param string|null $kept    What a refused press of this form typed into its note box, or
	 *                             null.
	 */
	private function render_agreement_form( $action, $nonce, $css, $busy, array $hidden, $label, $confirm, $note, $drive = '', $kept = null ) {
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
				'<p class="wpcpm-review__note"><label for="wpcpm-note-%1$s">%2$s %6$s</label> <textarea id="wpcpm-note-%1$s" name="%3$s" rows="4" minlength="%4$d" maxlength="%5$d" required>%7$s</textarea></p>',
				esc_attr( sanitize_html_class( $nonce ) ),
				esc_html( $note ),
				esc_attr( WPCPM_Sponsor_Agreement::FIELD_NOTE ),
				(int) WPCPM_Sponsor_Agreement::MIN_NOTE,
				(int) WPCPM_Sponsor_Agreement::MAX_NOTE,
				wp_kses( $required, array( 'span' => array( 'class' => array() ) ) ),
				// The parser drops one line feed right after the opening tag, so a kept note that
				// begins with a line break keeps it.
				is_string( $kept ) ? "\n" . esc_textarea( $kept ) : ''
			);
		}

		if ( '' !== $confirm ) {
			printf(
				'<button type="submit" class="button button-primary" data-wpcpm-confirm="%1$s">%2$s</button>',
				esc_attr( $confirm ),
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
	 * @param string $record Sponsor key.
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
