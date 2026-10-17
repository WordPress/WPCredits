<?php
/**
 * Mentors module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 2 - Mentors.
 *
 * Provisions Mentor accounts from Airtable, gives each mentor a private page
 * listing their assigned students, and reports on the last sync.
 *
 * Its screen is the accounts screen every audience's module shares (`WPCPM_Accounts_Screen`), in
 * four tabs: Waiting for review (the applications to mentor, 1.122.31), Accounts, Engagement (how
 * each mentor uses the site, 1.122.26) and Sync. What is the mentors' own is here: the words, the
 * table, the invitations card, the Engagement tab with its table, the Sync tab with its warning while
 * names are still unread, and the screen's choice of the tab it opens on.
 */
class WPCPM_Mentors extends WPCPM_Sync_Module {

	// The Engagement tab builds its own table on the screen's load hook (`load_screen()` below); the
	// shared one is kept under another name for the Accounts tab, as the Institutions module keeps it.
	use WPCPM_Accounts_Screen {
		load_screen as private load_accounts_list;
	}

	const ACTION_SYNC   = 'wpcpm_mentors_sync';
	const ACTION_CANCEL = 'wpcpm_mentors_cancel';
	const ACTION_INVITE = 'wpcpm_mentors_invite';

	/** Admin-post action for inviting everybody who has never been invited. */
	const ACTION_BULK = 'wpcpm_mentors_bulk_invite';
	const ACTION_TICK   = 'wpcpm_mentors_tick';

	/** The screen's tab the accounts are on, which every link and form of the list returns to. */
	const TAB_ACCOUNTS = 'accounts';

	/** The screen's tab the Airtable sync is on, which the sync's forms return to. */
	const TAB_SYNC = 'sync';

	/** The screen's tab that shows how each mentor uses the site (1.122.26). */
	const TAB_ENGAGEMENT = 'engagement';

	/** The screen's tab the applications to mentor are decided on (1.122.31), the slug the other two queues use. */
	const TAB_QUEUE = 'queue';

	/**
	 * The screen's tabs, slug => label, in the bar's order: the applications waiting for review, the
	 * accounts, how each mentor uses the site, then the sync. English, as a constant has to hold them;
	 * `tab_labels()` translates them. The tab the screen opens on is `tab()`'s to choose.
	 */
	const TABS = array(
		self::TAB_QUEUE      => 'Waiting for review',
		self::TAB_ACCOUNTS   => 'Accounts',
		self::TAB_ENGAGEMENT => 'Engagement',
		self::TAB_SYNC       => 'Sync',
	);

	/**
	 * The user option the rows-per-page choice is kept in: the Mentor accounts table's own name, here
	 * too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_mentors_per_page';

	/** The user option the Engagement tab's rows-per-page choice is kept in: its own, never the Accounts list's. */
	const ENGAGEMENT_PER_PAGE_OPTION = 'wpcpm_mentor_engagement_per_page';

	/**
	 * The Engagement tab's table, built on the screen's load hook.
	 *
	 * @var WPCPM_Mentor_Engagement_Table|null
	 */
	private $engagement = null;

	/** The flash channel that carries a press on the ticked accounts' count and kind, beside the outcome. */
	const FLASH_DETAIL = 'mentors_admin_detail';

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'mentors';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Mentors', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_MENTOR;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Mentor accounts are created from the Airtable Mentors table, using each mentor\'s WordPress.org username. Every mentor gets a Mentor-level page listing the students assigned to them.', 'wpcredits-program-manager' );
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		WPCPM_Mentors_Sync::register_cron();
		WPCPM_Mentors_Dashboard::init();
		WPCPM_Mentor_Notes::init();
		WPCPM_Mentor_Availability::init();
		WPCPM_Mentor_Calls::init();
		WPCPM_Call_Calendar::init();
		WPCPM_Group_Sessions::init();
		// The applications to mentor (1.122.31): the post type they are kept in, the public form, the
		// decisions taken on the Waiting for review tab, and the public feed of their counts.
		WPCPM_Mentor_Application::init();
		WPCPM_Mentor_Application_Form::init();
		WPCPM_Mentor_Application_Queue::init();
		WPCPM_Mentor_Application_Feed::init();

		// The hourly call reminders, put back on the clock whenever they are missing, as the
		// sync above does for its own job: the activation hook is the only other caller and
		// it never fires on this site's deploy path.
		add_action( 'init', array( 'WPCPM_Mentor_Calls', 'schedule' ), 20 );

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( $this, 'handle_bulk_invite' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// The screen's own load hook, once the menu exists, and its rows-per-page save.
		$this->boot_screen();

		// The Engagement tab's rows-per-page save, hooked here for the reason the Accounts list's is:
		// WordPress saves a screen option before it builds the menu.
		add_filter( 'set_screen_option_' . self::ENGAGEMENT_PER_PAGE_OPTION, array( __CLASS__, 'save_engagement_per_page' ), 10, 3 );
	}

	/**
	 * Activation: schedule the recurring sync and create the mentor page.
	 */
	public function activate() {
		WPCPM_Mentors_Sync::schedule();
		WPCPM_Mentor_Calls::schedule();
		WPCPM_Mentors_Dashboard::ensure_page();
		// The application form's page (1.122.31), and deliberately not gated: see its `ensure_page()`.
		WPCPM_Mentor_Application_Form::ensure_page();
	}

	/**
	 * Deactivation: stop all scheduled work.
	 */
	public function deactivate() {
		WPCPM_Mentors_Sync::unschedule();
		WPCPM_Mentor_Calls::unschedule();
		WPCPM_Mentor_Application::unschedule();
	}

	/**
	 * Uninstall: drop the module's own options, user meta and notes.
	 *
	 * Accounts are left alone - they are people, not plugin state.
	 */
	public function uninstall() {
		WPCPM_Mentor_Notes::delete_all();
		WPCPM_Mentor_Calls::delete_all();
		// The applications to mentor (1.122.31): every row, the deletion log, what deleted rows counted
		// for the feed and the daily run, and the form's page ID. The page itself stays, as every page
		// the plugin creates does: a page is site content.
		WPCPM_Mentor_Application::delete_all();
		delete_option( WPCPM_Mentor_Application_Form::OPT_PAGE );
		delete_transient( WPCPM_Mentor_Application_Feed::TRANSIENT );
		// Prefix-named, so it cannot be swept by listing keys: booking locks left by
		// requests that died holding one, and the per-student resolved-mentor cache.
		WPCPM_Mentor_Calls::flush_cache();
		WPCPM_WPorg_Profile::flush_cache();

		delete_option( WPCPM_Mentors_Sync::OPT_STATE );
		delete_option( WPCPM_Mentors_Sync::OPT_REPORT );
		delete_option( WPCPM_Mentors_Sync::OPT_LAST );
		delete_option( WPCPM_Mentors_Sync::OPT_ERROR );
		delete_option( WPCPM_Mentors_Sync::OPT_LOCK );
		delete_option( WPCPM_Mentors_Sync::OPT_FIELDS );
		delete_option( WPCPM_Mentors_Sync::OPT_LOOKUPS );
		delete_option( WPCPM_Mentors_Sync::OPT_SPONSORSHIP );
		delete_option( WPCPM_Mentors_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Mentors_Dashboard::OPT_TITLE_FIXED );

		foreach ( array(
			WPCPM_Mentors_Sync::META_RECORD_ID,
			WPCPM_Mentors_Sync::META_PROFILE,
			WPCPM_Mentors_Sync::META_ACTIVE,
			WPCPM_Mentors_Sync::META_MENTEES,
			WPCPM_Mentors_Sync::META_COUNT,
			WPCPM_Mentors_Sync::META_PAST_COUNT,
			WPCPM_Mentors_Sync::META_UPDATED,
			WPCPM_Mentor_Availability::META,
			// Set on students as well as mentors, but it exists only because the call
			// calendar needs a per-person clock, so it goes with the calendar.
			WPCPM_Mentor_Availability::META_TIMEZONE,
			'wpcpm_mentor_invited',
			// A manager's rows-per-page choice for the Mentor accounts and for the Engagement tab,
			// which core keeps as user meta.
			self::PER_PAGE_OPTION,
			self::ENGAGEMENT_PER_PAGE_OPTION,
		) as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * The sync this module owns.
	 *
	 * @return string
	 */
	protected function sync_class() {
		return 'WPCPM_Mentors_Sync';
	}

	/**
	 * The flash channel the Mentors screen reads its outcomes from.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return 'mentors_admin';
	}

	/**
	 * The Mentor accounts table: the list the screen draws, whose role and stamp the invitations
	 * read, and whose rule the rows-per-page save keeps.
	 *
	 * @return string
	 */
	protected static function table_class() {
		return 'WPCPM_Mentors_Table';
	}

	/**
	 * The words the screen prints for mentors, in the places every accounts screen prints its
	 * audience's: the keys `WPCPM_Accounts_Screen::screen_words()` lists.
	 *
	 * @return array<string, string>
	 */
	protected function screen_words() {
		return array(
			'heading_views'      => __( 'Filter mentor accounts list', 'wpcredits-program-manager' ),
			'heading_pagination' => __( 'Mentor accounts list navigation', 'wpcredits-program-manager' ),
			'heading_list'       => __( 'Mentor accounts list', 'wpcredits-program-manager' ),
			'not_connected'      => __( 'Airtable is not connected yet, so no mentors can be synced.', 'wpcredits-program-manager' ),
			'list_heading'       => __( 'Mentor accounts', 'wpcredits-program-manager' ),
			'page_label'         => __( 'Mentor Report Card:', 'wpcredits-program-manager' ),
			'page_missing'       => __( 'The mentor page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ),
			'search'             => __( 'Search mentors', 'wpcredits-program-manager' ),
			'list_note'          => __( 'Accounts are created with a random password and no email. "Send invite" emails that mentor a password-reset link so they can set their own.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The Mentor Report Card's address, which the list card names, or '' while the page is missing.
	 *
	 * @return string
	 */
	protected function dashboard_url() {
		return WPCPM_Mentors_Dashboard::page_url();
	}

	/**
	 * Before the screen draws: the Engagement tab's rows-per-page option and its table, or the
	 * Accounts tab's list as every accounts screen loads it.
	 */
	public function load_screen() {
		if ( self::TAB_ENGAGEMENT !== self::tab() ) {
			$this->load_accounts_list();

			return;
		}

		// The list table prints core's nonce and the address the form came from inside the search form,
		// so a search would leave both in the address and in every sort link built from it. Back to
		// the same list without them, as the Accounts tab does, before any of the list is read.
		if ( null !== WPCPM_Request::text( '_wpnonce', null ) || null !== WPCPM_Request::text( '_wp_http_referer', null ) ) {
			wp_safe_redirect( $this->engagement_url() );
			exit;
		}

		// The table first: its file is loaded the first time it is built, and the option names a
		// constant of its class.
		$this->engagement_table();

		// The headings core prints for screen readers above the views, the pagination and the list:
		// set here or not printed at all, as the Accounts tab sets its own. Escaped here, because core
		// prints them as they are given.
		$screen = get_current_screen();

		if ( $screen ) {
			$screen->set_screen_reader_content(
				array(
					'heading_views'      => esc_html__( 'Filter mentor engagement list', 'wpcredits-program-manager' ),
					'heading_pagination' => esc_html__( 'Mentor engagement list navigation', 'wpcredits-program-manager' ),
					'heading_list'       => esc_html__( 'Mentor engagement list', 'wpcredits-program-manager' ),
				)
			);
		}

		add_screen_option(
			'per_page',
			array(
				'default' => WPCPM_Mentor_Engagement_Table::PER_PAGE_DEFAULT,
				'option'  => self::ENGAGEMENT_PER_PAGE_OPTION,
			)
		);
	}

	/**
	 * The value WordPress stores for the Engagement tab's rows-per-page choice: 1 to 999, or false
	 * for none, the range `WPCPM_Accounts_Table::per_page_to_save()` allows.
	 *
	 * @param mixed  $keep   What core would store: false.
	 * @param string $option The option's name.
	 * @param mixed  $value  The number as submitted.
	 * @return int|false
	 */
	public static function save_engagement_per_page( $keep, $option, $value ) {
		$rows = (int) $value;

		return ( $rows >= 1 && $rows <= 999 ) ? $rows : false;
	}

	/**
	 * The tab the screen shows: the one its address names, or, from the menu and for a tab the screen
	 * does not have, Waiting for review while applications wait there and Accounts while none do
	 * (design of 16 October 2026, section 3, decision 3: the queue first when it holds rows). An
	 * address that carries a sort, a page or a search is the Accounts list's own and opens Accounts
	 * whatever waits: core builds the list's sort and page links from the address the screen was
	 * opened at, which from the menu names no tab.
	 *
	 * @return string A key of TABS.
	 */
	public static function tab() {
		$tab = WPCPM_Request::key( 'tab' );

		if ( isset( self::TABS[ $tab ] ) ) {
			return $tab;
		}

		// A search for "0" is a search, so the text is compared with the empty string and never tested
		// for being empty.
		if ( '' !== WPCPM_Request::key( 'orderby' ) || WPCPM_Request::id( 'paged' ) > 0 || '' !== WPCPM_Request::text( 's' ) ) {
			return self::TAB_ACCOUNTS;
		}

		return WPCPM_Mentor_Application::waiting_count() > 0 ? self::TAB_QUEUE : self::TAB_ACCOUNTS;
	}

	/**
	 * The tabs as the bar prints them: TABS, each label translated.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private static function tab_labels() {
		$translated = array(
			self::TAB_QUEUE      => __( 'Waiting for review', 'wpcredits-program-manager' ),
			self::TAB_ACCOUNTS   => __( 'Accounts', 'wpcredits-program-manager' ),
			self::TAB_ENGAGEMENT => __( 'Engagement', 'wpcredits-program-manager' ),
			self::TAB_SYNC       => __( 'Sync', 'wpcredits-program-manager' ),
		);

		return array_merge( self::TABS, array_intersect_key( $translated, self::TABS ) );
	}

	/**
	 * Render the screen: the tab bar, then the tab shown.
	 */
	public function render_admin_page() {
		$tab = self::tab();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		if ( self::TAB_SYNC === $tab ) {
			$this->render_tab_sync();
		} elseif ( self::TAB_ENGAGEMENT === $tab ) {
			$this->render_tab_engagement();
		} elseif ( self::TAB_QUEUE === $tab ) {
			$this->render_tab_queue();
		} else {
			$this->render_tab_accounts();
		}

		echo '</div>';
	}

	/**
	 * The Waiting for review tab: what the last decision pressed on it did, then the applications to
	 * mentor, read and decided here (`WPCPM_Mentor_Application_Queue`).
	 */
	private function render_tab_queue() {
		// The queue's notices with the sentence a press carried for its outcome, taken here, where the
		// notice prints (1.122.33).
		$this->render_notice_from( WPCPM_Mentor_Application_Queue::notices() );

		WPCPM_Mentor_Application_Queue::render();
	}

	/**
	 * The Engagement tab: the headline figures, then every mentor in the list. Read-only.
	 */
	private function render_tab_engagement() {
		$table = $this->engagement_table();

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Mentor engagement', 'wpcredits-program-manager' ) . '</h2>';
		echo '<ul class="ul-disc">';

		foreach ( WPCPM_Mentor_Engagement::tiles( $table->summary() ) as $tile ) {
			printf(
				'<li><strong>%1$s:</strong> %2$s%3$s</li>',
				esc_html( $tile['name'] ),
				esc_html( number_format_i18n( $tile['n'] ) ),
				'' !== $tile['line'] ? esc_html( ', ' . $tile['line'] ) : ''
			);
		}

		echo '</ul>';
		echo '<p>' . esc_html( WPCPM_Mentor_Engagement::apart( $table->summary() ) ) . '</p>';
		echo '</div>';

		echo '<div class="wpcpm-card" id="' . esc_attr( WPCPM_Mentor_Engagement_Table::ANCHOR ) . '">';
		$table->views();
		printf( '<form method="get" action="#%s">', esc_attr( WPCPM_Mentor_Engagement_Table::ANCHOR ) );
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB_ENGAGEMENT ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( WPCPM_Mentor_Engagement_Table::VIEW_ARG ), esc_attr( $table->current_view() ) );
		$table->search_box( esc_html__( 'Search mentors', 'wpcredits-program-manager' ), 'wpcpm-mentor-engagement' );
		$table->display();
		echo '</form>';

		$since = WPCPM_Sign_Ins::since();

		printf(
			'<p class="description">%s</p>',
			esc_html(
				$since > 0
					/* translators: %s: a date. */
					? sprintf( __( 'Sign-ins before %s are approximate: they were read from the site\'s sessions, Two Factor and the activity log on the day of the release.', 'wpcredits-program-manager' ), wp_date( (string) get_option( 'date_format' ), $since ) )
					: __( 'Sign-ins are recorded from this release on; earlier ones are read once, the first time an Administrator opens wp-admin after the update.', 'wpcredits-program-manager' )
			)
		);
		echo '</div>';
	}

	/**
	 * The Engagement tab as the list was showing it: its view, search, sort and page.
	 *
	 * @return string
	 */
	private function engagement_url() {
		require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement-table.php';

		$paged    = WPCPM_Request::id( 'paged' );
		$view_arg = WPCPM_Mentor_Engagement_Table::VIEW_ARG;

		// Whatever is empty is left out; a search for "0" is not empty.
		$state = array_filter(
			array(
				$view_arg => WPCPM_Request::key( $view_arg ),
				's'       => WPCPM_Request::text( 's' ),
				'orderby' => WPCPM_Request::key( 'orderby' ),
				'order'   => WPCPM_Request::key( 'order' ),
				'paged'   => $paged > 0 ? (string) $paged : '',
			),
			static function ( $value ) {
				return '' !== $value;
			}
		);

		return add_query_arg( array_map( 'rawurlencode', $state ), $this->tab_url( self::TAB_ENGAGEMENT ) );
	}

	/**
	 * The Engagement tab's table over one report, built once a request.
	 *
	 * @return WPCPM_Mentor_Engagement_Table
	 */
	private function engagement_table() {
		if ( null === $this->engagement ) {
			require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-engagement-table.php';

			$this->engagement = new WPCPM_Mentor_Engagement_Table( WPCPM_Mentor_Engagement::report() );
			$this->engagement->prepare_items();
		}

		return $this->engagement;
	}

	/**
	 * Queue an invitation for every mentor who has never had one, then back to the Accounts tab, as
	 * every other press on the tab comes back to it.
	 *
	 * Queued rather than sent here: `send_invite()` sends immediately, which is right for one row
	 * and would time out somewhere in the middle of two hundred. The queue is drained by cron a
	 * batch at a time, which is what it was built for.
	 *
	 * The count a press on the ticked accounts carries is that press's alone, and this button leaves
	 * the same outcomes with none: `leave()` empties that channel, so a count never printed is not
	 * shown under these.
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
	 * The Sync tab: the sync's notices, the warning while Airtable is not connected, the warning while
	 * mentors made from applications wait for their Airtable row (1.122.33), the last run's error, the
	 * warning while names are still unread, the Airtable sync card and the last run's report.
	 */
	private function render_tab_sync() {
		$report = get_option( WPCPM_Mentors_Sync::OPT_REPORT );
		$error  = get_option( WPCPM_Mentors_Sync::OPT_ERROR );

		$this->render_notice_from( self::sync_messages() );
		$this->render_not_connected();
		$this->render_unwritten();

		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( $error )
			);
		}

		// Student rows are cached in user meta, so institution and team names only appear once a
		// sync has read the tables they live in. On this tab, since what it asks for is a sync.
		if ( WPCPM_Mentors_Sync::has_unresolved_links() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Institution and team names have not been read yet.', 'wpcredits-program-manager' ),
				esc_html__( 'Airtable sends those two fields as record IDs, which the sync turns into names. Run a sync to fill them in - until then the mentor page leaves them blank rather than showing an ID.', 'wpcredits-program-manager' )
			);
		}

		$this->render_sync_panel( WPCPM_Mentors_Sync::progress(), (int) get_option( WPCPM_Mentors_Sync::OPT_LAST ) );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}
	}

	/**
	 * The mentors made from applications whose Mentors row in Airtable is not written yet (1.122.33):
	 * the sync leaves each such account alone until it is, so the Sync tab says how many, and where
	 * Write the Airtable row again is. Counting may settle a row whose account was linked meanwhile
	 * or is gone, under that row's lock and only for such a row (ruling R1).
	 */
	private function render_unwritten() {
		$count = WPCPM_Mentor_Activation::unwritten_count();

		if ( $count <= 0 ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: how many mentors made from applications have no Airtable row yet. */
					_n( '%s mentor made from an application has no Airtable row yet.', '%s mentors made from applications have no Airtable row yet.', $count, 'wpcredits-program-manager' ),
					number_format_i18n( $count )
				)
			),
			esc_url( WPCPM_Mentor_Application_Queue::view_url( 'active' ) ),
			esc_html__( 'Write the Airtable row again is on each, under Activated on the Waiting for review tab.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The invitations card, on the Accounts tab: every mentor never invited, and how many of them are
	 * left out because an activation held their invitation (1.122.33), which only their own row sends.
	 *
	 * Its button posts the tab, so the press comes back to it. Its Stop and Dismiss are the mail
	 * layer's own forms, which name no tab: they come back to the address they were pressed on, this
	 * tab, and Stop's outcome waits on the flash channel this screen reads, which the card names in
	 * its form, so "Sending stopped." prints here.
	 */
	private function render_invitations() {
		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => self::never_invited(),
				'held'    => count( self::held_back() ),
				'noun'    => __( 'mentors', 'wpcredits-program-manager' ),
				'hidden'  => array( self::TAB_FIELD => self::TAB_ACCOUNTS ),
				'flash'   => $this->flash_key(),
			)
		);
	}

	/**
	 * The mentors never invited whose invitation an activation held (`WPCPM_Mail::held_back()`), by
	 * the Mentors table's role and stamp, as `never_invited()` reads them.
	 *
	 * @return int[]
	 */
	private static function held_back() {
		wpcpm_load_accounts_tables();

		$table_class = static::table_class();

		return WPCPM_Mail::held_back( $table_class::role(), $table_class::invite_meta() );
	}

	/**
	 * The Airtable sync card, on the Sync tab: the live progress and Cancel sync while a run is on,
	 * and otherwise when the last run finished and the button that starts one. Both forms name the
	 * tab, so the press comes back to it.
	 *
	 * @param array $progress Progress data.
	 * @param int   $last     Timestamp of the last completed sync.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		if ( $progress['running'] ) {
			$this->render_progress( $progress );

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION_CANCEL );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CANCEL ) . '" />';
			$this->tab_field( self::TAB_SYNC );
			submit_button( __( 'Cancel sync', 'wpcredits-program-manager' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			if ( $last ) {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: human-readable time difference. */
							__( 'Last completed %s ago.', 'wpcredits-program-manager' ),
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
			$this->tab_field( self::TAB_SYNC );
			submit_button( __( 'Sync mentors now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Format a duration as a clock value.
	 *
	 * `human_time_diff()` rounds to "1 min" and would sit unchanged for a whole
	 * minute, which is the opposite of what a progress readout needs.
	 *
	 * @param int $seconds Elapsed seconds.
	 * @return string
	 */
	public static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( $seconds >= HOUR_IN_SECONDS ) {
			return sprintf( '%d:%02d:%02d', intdiv( $seconds, HOUR_IN_SECONDS ), intdiv( $seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ), $seconds % MINUTE_IN_SECONDS );
		}

		return sprintf( '%d:%02d', intdiv( $seconds, MINUTE_IN_SECONDS ), $seconds % MINUTE_IN_SECONDS );
	}

	/**
	 * The live progress readout.
	 *
	 * Rendered server-side with the current values so it is already populated on
	 * first paint - and so it still reports real progress with JavaScript off,
	 * where cron drives the run and the meta-refresh fallback updates the page.
	 *
	 * @param array $progress Progress payload from WPCPM_Mentors_Sync::progress().
	 */
	private function render_progress( array $progress ) {
		$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;

		printf(
			'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
			esc_attr( self::ACTION_TICK ),
			esc_attr( wp_create_nonce( self::ACTION_TICK ) )
		);

		echo '<p class="wpcpm-progress__head">';
		echo '<span class="spinner is-active" aria-hidden="true"></span> ';
		printf( '<strong data-wpcpm-label>%s</strong>', esc_html( $progress['label'] ) );
		printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( $progress['step_label'] ) );
		echo '</p>';

		printf(
			'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar>
				<div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div>
			</div>',
			(int) $percent,
			esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
		);

		echo '<p class="wpcpm-progress__meta">';
		printf( '<span data-wpcpm-percent>%s</span>', esc_html( sprintf( '%d%%', $percent ) ) );
		echo ' · ';
		printf( '<span data-wpcpm-detail>%s</span>', esc_html( $progress['detail'] ) );
		echo ' · ';
		/* translators: %s: elapsed time as a clock value, e.g. "2:05". */
		$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
		printf(
			'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
			esc_attr( $elapsed_label ),
			esc_html( sprintf( $elapsed_label, self::format_duration( (int) $progress['elapsed'] ) ) )
		);
		echo '</p>';

		// Only shown if the run genuinely stops advancing, so the reassuring
		// message above never has to compete with a silent failure.
		printf(
			'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
			$progress['stalled'] ? '' : ' hidden',
			esc_html__( 'No progress for over two minutes. The run may have been interrupted - cancel it and start again.', 'wpcredits-program-manager' )
		);

		echo '<p class="description">' . esc_html__( 'The sync works in short bursts so it never hits a PHP timeout. Progress above updates every few seconds; you can safely leave this page and come back.', 'wpcredits-program-manager' ) . '</p>';

		echo '<noscript><p class="description">' . esc_html__( 'JavaScript is off, so this page reloads every 15 seconds to show progress. The sync itself continues either way.', 'wpcredits-program-manager' ) . '</p>';
		echo '<meta http-equiv="refresh" content="15" /></noscript>';

		echo '</div>';
	}

	/**
	 * The last run's numbers and warnings.
	 *
	 * @param array $report Stored report.
	 */
	private function render_report( array $report ) {
		$stats = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();

		$labels = array(
			'described'     => __( 'Field descriptions read from Airtable', 'wpcredits-program-manager' ),
			'mentors_seen'  => __( 'Active mentors in Airtable', 'wpcredits-program-manager' ),
			'created'       => __( 'Accounts created', 'wpcredits-program-manager' ),
			'linked'        => __( 'Existing accounts linked', 'wpcredits-program-manager' ),
			'updated'       => __( 'Accounts refreshed', 'wpcredits-program-manager' ),
			'invited'       => __( 'Invitations sent', 'wpcredits-program-manager' ),
			'revoked'       => __( 'Mentor role revoked (no longer active)', 'wpcredits-program-manager' ),
			'students_seen' => __( 'Current student reports read', 'wpcredits-program-manager' ),
			'past_seen'     => __( 'Past student reports read', 'wpcredits-program-manager' ),
			'assigned'      => __( 'Student assignments written', 'wpcredits-program-manager' ),
			'unassigned'    => __( 'Students with no mentor in Airtable', 'wpcredits-program-manager' ),
			'orphaned'      => __( 'Students assigned to a non-active mentor', 'wpcredits-program-manager' ),
			'skipped'       => __( 'Mentors skipped (missing data)', 'wpcredits-program-manager' ),
			'conflicts'     => __( 'Account conflicts', 'wpcredits-program-manager' ),
		);

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Last sync report', 'wpcredits-program-manager' ) . '</h2>';
		echo '<table class="wpcpm-table"><tbody>';

		foreach ( $labels as $key => $label ) {
			if ( ! isset( $stats[ $key ] ) ) {
				continue;
			}

			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
				esc_html( $label ),
				esc_html( number_format_i18n( (int) $stats[ $key ] ) )
			);
		}

		echo '</tbody></table>';

		$notices = isset( $report['notices'] ) ? (array) $report['notices'] : array();

		if ( ! empty( $notices ) ) {
			echo '<h3>' . esc_html__( 'Warnings', 'wpcredits-program-manager' ) . '</h3>';
			echo '<ul class="wpcpm-notices">';
			foreach ( $notices as $notice ) {
				echo '<li>' . esc_html( $notice ) . '</li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}
}
