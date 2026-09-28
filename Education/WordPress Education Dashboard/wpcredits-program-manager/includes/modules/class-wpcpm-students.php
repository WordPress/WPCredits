<?php
/**
 * Students module.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module 1 - Students.
 *
 * Provisions Student accounts from Airtable and gives each student a Student-level
 * page with their own program details and the mentor assigned to them.
 */
class WPCPM_Students extends WPCPM_Sync_Module {

	const ACTION_SYNC   = 'wpcpm_students_sync';
	const ACTION_CANCEL = 'wpcpm_students_cancel';
	const ACTION_INVITE = 'wpcpm_students_invite';

	/** Admin-post action for inviting everybody who has never been invited. */
	const ACTION_BULK = 'wpcpm_students_bulk_invite';
	const ACTION_TICK   = 'wpcpm_students_tick';

	/** The screen's tab the accounts are on, which every link and form of the list returns to. */
	const TAB_ACCOUNTS = 'accounts';

	/** The screen's tab the Airtable sync is on, which the sync's forms return to. */
	const TAB_SYNC = 'sync';

	/**
	 * The screen's tabs, slug => label, in the bar's order: the accounts, the tab the screen opens
	 * on, then the sync. English, as a constant has to hold them; `tab_labels()` translates them.
	 */
	const TABS = array(
		self::TAB_ACCOUNTS => 'Accounts',
		self::TAB_SYNC     => 'Sync',
	);

	/**
	 * The user option the rows-per-page choice is kept in: the Student accounts table's own name, here
	 * too, because the save is hooked at boot, long before that class is loaded.
	 */
	const PER_PAGE_OPTION = 'wpcpm_students_per_page';

	/** The flash channel that carries a press on the ticked accounts' count and kind, beside the outcome. */
	const FLASH_DETAIL = 'students_admin_detail';

	/**
	 * The Student accounts table, built on the screen's load hook.
	 *
	 * @var WPCPM_Students_Table|null
	 */
	private $table;

	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id() {
		return 'students';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Students', 'wpcredits-program-manager' );
	}

	/**
	 * Managed role.
	 *
	 * @return string
	 */
	public function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Student accounts are created from the Airtable Students Reports table. Every student gets a Student-level page with their program details and the mentor assigned to them.', 'wpcredits-program-manager' );
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
		WPCPM_Students_Sync::register_cron();
		WPCPM_Students_Dashboard::init();
		WPCPM_Student_Report_Form::init();
		WPCPM_Student_Feedback::init();

		add_action( 'admin_post_' . self::ACTION_SYNC, array( $this, 'handle_sync' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_' . self::ACTION_INVITE, array( $this, 'handle_invite' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( $this, 'handle_bulk_invite' ) );
		add_action( 'wp_ajax_' . self::ACTION_TICK, array( $this, 'handle_tick' ) );

		// After the menu is built, at 10: the screen's hook is named after the menu, which does not
		// exist before then.
		add_action( 'admin_menu', array( $this, 'hook_screen' ), 11 );

		// Here rather than on the screen's load hook: WordPress saves a screen option before it builds
		// the menu, so a filter added any later misses the request that saves.
		add_filter( 'set_screen_option_' . self::PER_PAGE_OPTION, array( __CLASS__, 'save_per_page' ), 10, 3 );
	}

	/**
	 * Activation: schedule the sync and create the page.
	 */
	public function activate() {
		WPCPM_Students_Sync::schedule();
		WPCPM_Students_Dashboard::ensure_page();
	}

	/**
	 * Deactivation: stop scheduled work.
	 */
	public function deactivate() {
		WPCPM_Students_Sync::unschedule();
	}

	/**
	 * Uninstall: drop options and user meta. Accounts are left alone.
	 */
	public function uninstall() {
		delete_option( WPCPM_Students_Sync::OPT_STATE );
		delete_option( WPCPM_Students_Sync::OPT_REPORT );
		delete_option( WPCPM_Students_Sync::OPT_LAST );
		delete_option( WPCPM_Students_Sync::OPT_ERROR );
		delete_option( WPCPM_Students_Sync::OPT_LOCK );
		delete_option( WPCPM_Students_Dashboard::OPT_PAGE );
		delete_option( WPCPM_Students_Dashboard::OPT_TITLE_FIXED );

		WPCPM_WPorg_Profile::flush_cache();

		foreach ( array(
			WPCPM_Students_Sync::META_RECORD_ID,
			WPCPM_Students_Sync::META_ACTIVE,
			WPCPM_Students_Sync::META_PROGRAM,
			WPCPM_Students_Sync::META_MENTOR,
			WPCPM_Students_Sync::META_UPDATED,
			WPCPM_Student_Feedback::META_RECORD,
			WPCPM_Student_Feedback::META_RECORD_PLACEMENT,
			'wpcpm_student_invited',
			// A manager's rows-per-page choice for the Student accounts, which core keeps as user meta.
			self::PER_PAGE_OPTION,
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
		return 'WPCPM_Students_Sync';
	}

	/**
	 * The flash channel the Students screen reads its outcomes from.
	 *
	 * @return string
	 */
	protected function flash_key() {
		return 'students_admin';
	}

	/**
	 * Email one student their login invitation.
	 *
	 * From a row of the list, whose invitation is a link carrying the account as `user`, where the list
	 * stands and the handler's nonce, or from a form that posts the account as `user_id`, as each row
	 * did before the list became one form. Sent at once, as it always was for one person, then back to
	 * the list as the link says it stood, on the Accounts tab, the way a press on the ticked accounts
	 * comes back (`list_url()`); a posted form carries no list, and comes back to the tab itself.
	 */
	public function handle_invite() {
		$this->verify( self::ACTION_INVITE );

		$user_id = WPCPM_Request::posted_id( 'user_id' );

		if ( ! $user_id ) {
			$user_id = WPCPM_Request::id( 'user' );
		}

		$result = WPCPM_Students_Sync::send_invite( $user_id );

		$this->leave( $this->list_url(), WPCPM_Mail::invite_outcome( $result ) );
	}

	/**
	 * Queue an invitation for everybody who has never had one, at the institution the list was
	 * narrowed to if it was, then back to the list at that institution on the Accounts tab, as every
	 * other press on the tab comes back to the list as it stood.
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

		// From the posted field, not the URL: `admin-post.php` never sees the list's query string,
		// so reading the filter from there would send to every student while the screen that
		// offered the button was showing one institution.
		$institution = WPCPM_Request::posted_text( 'wpcpm_institution' );
		$pending     = WPCPM_Mail::only_institution( self::never_invited(), $institution );
		$back        = '' === $institution ? $this->accounts_url() : add_query_arg( 'wpcpm_institution', rawurlencode( $institution ), $this->accounts_url() );

		if ( empty( $pending ) ) {
			$this->leave( $back, 'invites-none' );
		}

		WPCPM_Mail::queue_invites( $pending );

		$this->leave( $back, 'invites-queued' );
	}

	/**
	 * Hook the screen's own load hook, now that the menu exists to name it.
	 *
	 * Named as core names it, from the menu's registered name and the page's slug. Core registers
	 * the menu under its translated title, so a fixed name would miss the screen on a site in
	 * another language.
	 */
	public function hook_screen() {
		add_action( 'load-' . get_plugin_page_hookname( $this->page_slug(), WPCPM_Admin::MENU_SLUG ), array( $this, 'load_screen' ) );
	}

	/**
	 * Before the Students screen draws its Accounts tab: the list's classes, its rows-per-page option,
	 * the table, a press in the list's form, and the page of rows the list shows.
	 *
	 * Core's own lists work this way. The table is built here, before the page's header, because
	 * core lists a table's columns under Screen Options from the filter its constructor adds, and the
	 * rows are read here, where core can still redirect a page past the last one. A bulk action is
	 * handled here because the list is a GET form to this screen: its bulk select is named `action`
	 * by core, which would route a post to admin-post.php to a handler of the action's own name.
	 *
	 * Nothing here runs for the Sync tab, which has no list: it reads no account, and Screen Options
	 * offers it no rows-per-page choice or columns for a list it does not draw. The list's form names
	 * the Accounts tab and its links are built on the Accounts tab's address, so a press in the list
	 * is always a request for that tab.
	 */
	public function load_screen() {
		if ( self::TAB_ACCOUNTS !== self::tab() ) {
			return;
		}

		wpcpm_load_accounts_tables();

		WPCPM_Students_Table::add_per_page_option();

		// The headings core prints for screen readers above the views, the pagination and the list:
		// set here or not printed at all, as core's own list screens set theirs. Escaped here, because
		// core prints them as they are given.
		$screen = get_current_screen();

		if ( $screen ) {
			$screen->set_screen_reader_content(
				array(
					'heading_views'      => esc_html__( 'Filter student accounts list', 'wpcredits-program-manager' ),
					'heading_pagination' => esc_html__( 'Student accounts list navigation', 'wpcredits-program-manager' ),
					'heading_list'       => esc_html__( 'Student accounts list', 'wpcredits-program-manager' ),
				)
			);
		}

		$table = new WPCPM_Students_Table();

		$this->handle_list_form( $table );

		$table->prepare_items();

		$this->table = $table;
	}

	/**
	 * What the list's form sent, if it was sent: a bulk action, or a search, a filter or a page.
	 *
	 * Send invite and Resend invite go through the capability and the list's nonce before anything
	 * else of the request is read. Anything else sent from the form comes back to the same list
	 * without the form's own fields, as core's own lists do, so the nonce never stays in the address
	 * or in the page and sort links built from it.
	 *
	 * @param WPCPM_Students_Table $table The table the form belongs to.
	 */
	private function handle_list_form( WPCPM_Students_Table $table ) {
		$action = $table->current_action();

		if ( 'invite' === $action || 'reinvite' === $action ) {
			$this->verify( WPCPM_Students_Table::bulk_nonce_action() );
			$this->invite_selected( WPCPM_Request::ids( WPCPM_Students_Table::USERS_FIELD ), 'reinvite' === $action );
		}

		if ( '' !== WPCPM_Request::text( '_wp_http_referer' ) ) {
			wp_safe_redirect( $this->list_url() );
			exit;
		}
	}

	/**
	 * The rows-per-page choice as core may store it, by the Student accounts table's rule.
	 *
	 * The table is loaded first: core saves a screen option before the screen's load hook runs.
	 *
	 * @param mixed  $keep   What core would store without this filter: false, which is nothing.
	 * @param string $option The option's name.
	 * @param mixed  $value  The number as submitted.
	 * @return int|false
	 */
	public static function save_per_page( $keep, $option, $value ) {
		wpcpm_load_accounts_tables();

		return WPCPM_Students_Table::per_page_to_save( $keep, $option, $value );
	}

	/**
	 * Queue invitations for the ticked accounts, then back to the list, saying how many.
	 *
	 * Send invite queues the ticked accounts never invited through `WPCPM_Mail::queue_invites()`, as
	 * the invitations card's button does, which records the run the card reports on. Resend invite
	 * queues each ticked account invited before through `WPCPM_Mail::queue_invite()`, because
	 * `queue_invites()` drops anybody already invited, by design: the same queue and drain, and no
	 * run for the card to count. The drain passes over anybody sent an invitation in the last fifteen
	 * minutes, the guard against canceling the link in it (`WPCPM_Mail::may_invite()`), so Resend
	 * invite leaves them out and counts them, and the notice says so rather than calling them
	 * queued. Every ID is checked against the Student accounts table's role first, and invited means
	 * the table's stamp is there, the reading its views and row actions use.
	 *
	 * Private: reached only from the list's form on the screen's load hook (`handle_list_form()`),
	 * which checks the capability and the list's nonce before it reads the ticked IDs.
	 *
	 * @param int[] $ids    The ticked accounts.
	 * @param bool  $resend Resend invite, rather than Send invite.
	 */
	private function invite_selected( array $ids, $resend ) {
		$detail  = array( 'resend' => (bool) $resend );
		$role    = WPCPM_Students_Table::role();
		$stamp   = WPCPM_Students_Table::invite_meta();
		$invited = array();
		$never   = array();

		if ( empty( $ids ) ) {
			$this->leave( $this->list_url(), 'invites-none', $detail + array( 'why' => 'none-selected' ) );
		}

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					static function ( $id ) {
						return $id > 0;
					}
				)
			)
		);

		// Every ticked account and its meta in two queries, not two queries an account: a page can
		// hold 999, and the page's own read that primes them comes after this.
		cache_users( $ids );

		foreach ( $ids as $id ) {
			$user = get_user_by( 'id', $id );

			if ( ! $user instanceof WP_User || ! in_array( $role, (array) $user->roles, true ) ) {
				continue;
			}

			if ( metadata_exists( 'user', $user->ID, $stamp ) ) {
				$invited[] = (int) $user->ID;
			} else {
				$never[] = (int) $user->ID;
			}
		}

		if ( $detail['resend'] ) {
			$waiting = WPCPM_Mail::queue();
			$recent  = 0;

			foreach ( $invited as $id ) {
				// Queued for somebody sent one inside the gap, an invitation could be passed over by the
				// drain, which says nothing when it does: left out here and counted instead.
				if ( is_wp_error( WPCPM_Mail::may_invite( $id ) ) ) {
					++$recent;
					continue;
				}

				WPCPM_Mail::queue_invite( $id );
			}

			$queued = count( array_diff( WPCPM_Mail::queue(), $waiting ) );

			if ( empty( $invited ) ) {
				$why = 'never-invited';
			} elseif ( count( $invited ) === $recent ) {
				$why = 'too-soon';
			} else {
				$why = 'queued-already';
			}

			if ( $recent > 0 && 'too-soon' !== $why ) {
				$detail['recent'] = $recent;
			}
		} else {
			$queued = empty( $never ) ? 0 : WPCPM_Mail::queue_invites( $never );
			// Accounts never invited can be waiting already: the card's run sends ten a batch, and the
			// Never invited view lists each until its invitation goes and stamps it.
			$why = ( ! empty( $never ) && ! array_diff( $never, WPCPM_Mail::queue() ) ) ? 'queued-already' : 'invited-already';
		}

		if ( 0 === $queued ) {
			$this->leave( $this->list_url(), 'invites-none', $detail + array( 'why' => $why ) );
		}

		$this->leave( $this->list_url(), 'invites-queued', $detail + array( 'queued' => $queued ) );
	}

	/**
	 * Back to a place on this screen with the outcome flashed, beside it on a channel of its own
	 * what a press on the ticked accounts adds, which `notice_sentence()` reads.
	 *
	 * The channel is written with every outcome, emptied when there is nothing to add, so a count
	 * never waits to be printed under a later press's notice.
	 *
	 * @param string $url    This screen's address to return to, which WPCPM_Return may swap for the dashboard.
	 * @param string $status An outcome key the screen's message map knows.
	 * @param array  $detail What the press adds: `resend`, `queued` or `why`, and `recent`, how many a
	 *                       Resend invite left out for the fifteen minutes.
	 */
	private function leave( $url, $status, array $detail = array() ) {
		WPCPM_Flash::set( $this->flash_key(), $status );
		WPCPM_Flash::set( self::FLASH_DETAIL, empty( $detail ) ? '' : array_merge( array( 'status' => $status ), $detail ) );

		wp_safe_redirect( WPCPM_Return::url( $url ) );
		exit;
	}

	/**
	 * The screen's Accounts tab.
	 *
	 * @return string
	 */
	private function accounts_url() {
		return $this->tab_url( self::TAB_ACCOUNTS );
	}

	/**
	 * Where the list stands, as the request says: its view, search, institution, sort and page, each
	 * encoded, the empty ones left out.
	 *
	 * What a press in the list comes back to (`list_url()`), and what each row's invitation link
	 * carries, so the invitation comes back to the same place. Rebuilt from what the list reads rather
	 * than copied from the address, so the form's own fields (its nonce, the ticked accounts, the
	 * action chosen) are never carried. Each value is encoded, because `add_query_arg()` sets a value
	 * as it is given.
	 *
	 * @return array<string, string>
	 */
	public static function list_state() {
		return array_map(
			static function ( $value ) {
				return rawurlencode( (string) $value );
			},
			array_filter(
				array(
					'wpcpm_view'        => WPCPM_Request::key( 'wpcpm_view' ),
					's'                 => WPCPM_Request::text( 's' ),
					'wpcpm_institution' => self::institution_filter(),
					'orderby'           => WPCPM_Request::key( 'orderby' ),
					'order'             => WPCPM_Request::key( 'order' ),
					'paged'             => WPCPM_Request::id( 'paged' ),
				)
			)
		);
	}

	/**
	 * The Accounts tab as the list was showing it (`list_state()`).
	 *
	 * @return string
	 */
	private function list_url() {
		return add_query_arg( self::list_state(), $this->accounts_url() );
	}

	/**
	 * The Student accounts table the load hook built, or one built and read now for a list drawn
	 * without it.
	 *
	 * @return WPCPM_Students_Table
	 */
	private function table() {
		if ( null === $this->table ) {
			wpcpm_load_accounts_tables();

			$this->table = new WPCPM_Students_Table();
			$this->table->prepare_items();
		}

		return $this->table;
	}

	/**
	 * The outcomes the Accounts tab prints, the presses made on it: a row's invitation's, worded once
	 * with the Mentors screen, with the `error` its failed send leaves, and the invitations'. The
	 * sync's own print on the Sync tab.
	 *
	 * The `error` is worded for this tab: the shared sentence sends the reader to the last sync's
	 * error below it, which the Sync tab prints and this one does not.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice type and sentence.
	 */
	private function accounts_messages() {
		return array_merge(
			array( 'error' => array( 'error', __( 'The invitation could not be sent.', 'wpcredits-program-manager' ) ) ),
			WPCPM_Mail::invite_notices(),
			array(
				'invites-queued'  => array( 'success', __( 'Invitations queued. They go out in the background - the progress is shown below.', 'wpcredits-program-manager' ) ),
				'invites-none'    => array( 'info', __( 'Nobody was waiting for an invitation.', 'wpcredits-program-manager' ) ),
				'invites-stopped' => array( 'info', __( 'Sending stopped. Invitations already sent cannot be recalled.', 'wpcredits-program-manager' ) ),
			)
		);
	}

	/**
	 * The sentence for a press on the ticked accounts: how many were queued, or why none was.
	 *
	 * The count is one-shot, so it is read here, on the one status being printed, and used only when
	 * it was carried for that very status: the invitations card's button leaves the same two
	 * statuses with no count, and keeps the map's sentences.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from the map.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		$detail = WPCPM_Flash::take( self::FLASH_DETAIL );

		if ( ! is_array( $detail ) || ! isset( $detail['status'] ) || (string) $status !== (string) $detail['status'] ) {
			return (string) $sentence;
		}

		return self::selected_sentence( $detail );
	}

	/**
	 * What a press on the ticked accounts says it did, and how many a Resend invite left out for the
	 * fifteen minutes when it left any out.
	 *
	 * @param array $detail `resend`, `queued` or `why`, and `recent`.
	 * @return string
	 */
	private static function selected_sentence( array $detail ) {
		$sentence = self::queued_sentence( $detail );
		$recent   = isset( $detail['recent'] ) ? (int) $detail['recent'] : 0;

		if ( $recent > 0 ) {
			$sentence .= ' ' . sprintf(
				/* translators: 1: how many selected accounts were left out, 2: a number of minutes. */
				_n( '%1$s selected account was left out: it was sent an invitation less than %2$d minutes ago, and another one now would cancel the link in it.', '%1$s selected accounts were left out: each was sent an invitation less than %2$d minutes ago, and another one now would cancel the link in it.', $recent, 'wpcredits-program-manager' ),
				number_format_i18n( $recent ),
				(int) ( WPCPM_Mail::INVITE_GAP / MINUTE_IN_SECONDS )
			);
		}

		return $sentence;
	}

	/**
	 * How many a press on the ticked accounts queued, or why it queued none.
	 *
	 * @param array $detail `resend`, and `queued` or `why`.
	 * @return string
	 */
	private static function queued_sentence( array $detail ) {
		$queued = isset( $detail['queued'] ) ? (int) $detail['queued'] : 0;

		if ( $queued > 0 ) {
			if ( empty( $detail['resend'] ) ) {
				/* translators: %s: how many invitations were queued. */
				$sentence = _n( '%s invitation queued. It goes out in the background - the progress is shown below.', '%s invitations queued. They go out in the background - the progress is shown below.', $queued, 'wpcredits-program-manager' );
			} else {
				/* translators: %s: how many invitations were queued. */
				$sentence = _n( '%s invitation queued. It goes out with the next batch and replaces the link in any earlier invitation.', '%s invitations queued. They go out with the next batch, and each replaces the link in any earlier invitation.', $queued, 'wpcredits-program-manager' );
			}

			return sprintf( $sentence, number_format_i18n( $queued ) );
		}

		$why = isset( $detail['why'] ) ? (string) $detail['why'] : '';

		if ( 'none-selected' === $why ) {
			return __( 'Nothing to send: no accounts were selected.', 'wpcredits-program-manager' );
		}

		if ( 'never-invited' === $why ) {
			return __( 'Nothing to send: none of the selected accounts has been invited yet. Use Send invite for a first invitation.', 'wpcredits-program-manager' );
		}

		if ( 'queued-already' === $why ) {
			return __( 'Nothing to send: the selected accounts are already waiting in the queue.', 'wpcredits-program-manager' );
		}

		if ( 'too-soon' === $why ) {
			return sprintf(
				/* translators: %d: a number of minutes. */
				__( 'Nothing to send: the selected accounts were each sent an invitation less than %d minutes ago, and another one now would cancel the link in it. Ask them to use their newest email, or try again later.', 'wpcredits-program-manager' ),
				(int) ( WPCPM_Mail::INVITE_GAP / MINUTE_IN_SECONDS )
			);
		}

		return __( 'Nothing to send: none of the selected accounts needs a first invitation.', 'wpcredits-program-manager' );
	}

	/**
	 * The tab the screen shows: the one its address names, or Accounts when it names none or one the
	 * screen does not have.
	 *
	 * Accounts is the default as well as the first tab: the menu opens the screen at its own address,
	 * and the sort and page links core builds from an address carry the tab it names, none from the
	 * menu's, so they open the list too.
	 *
	 * @return string A key of TABS.
	 */
	public static function tab() {
		$tab = WPCPM_Request::key( 'tab' );

		return isset( self::TABS[ $tab ] ) ? $tab : self::TAB_ACCOUNTS;
	}

	/**
	 * The tabs as the bar prints them: TABS, each label translated.
	 *
	 * Each is written out here, because the translation tools collect a string only where it is
	 * written as one; a tab this does not name keeps the English of TABS.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private static function tab_labels() {
		$translated = array(
			self::TAB_ACCOUNTS => __( 'Accounts', 'wpcredits-program-manager' ),
			self::TAB_SYNC     => __( 'Sync', 'wpcredits-program-manager' ),
		);

		return array_merge( self::TABS, array_intersect_key( $translated, self::TABS ) );
	}

	/**
	 * The hidden field naming the tab a form is on, so its press comes back to it (`redirect_back()`).
	 *
	 * @param string $tab A key of TABS.
	 */
	private function tab_field( $tab ) {
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TAB_FIELD ), esc_attr( $tab ) );
	}

	/**
	 * Render the Students screen: the tab bar, then the tab shown.
	 *
	 * Accounts holds the invitations and the list, Sync the Airtable sync and what its last run left.
	 * A press comes back to the tab it was made on, and each tab prints the notices of the presses
	 * made on it.
	 */
	public function render_admin_page() {
		$tab = self::tab();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		if ( self::TAB_SYNC === $tab ) {
			$this->render_tab_sync();
		} else {
			$this->render_tab_accounts();
		}

		echo '</div>';
	}

	/**
	 * The Accounts tab: the notices of the presses made on it, the warning while Airtable is not
	 * connected, the invitations card and the Student accounts list.
	 */
	private function render_tab_accounts() {
		$this->render_notice_from( $this->accounts_messages() );
		$this->render_not_connected();
		$this->render_invitations();
		$this->render_student_list();
	}

	/**
	 * The Sync tab: the sync's notices, the warning while Airtable is not connected, the last run's
	 * error, the Airtable sync card and the last run's report.
	 */
	private function render_tab_sync() {
		$report = get_option( WPCPM_Students_Sync::OPT_REPORT );
		$error  = get_option( WPCPM_Students_Sync::OPT_ERROR );

		$this->render_notice_from( self::sync_messages() );
		$this->render_not_connected();

		if ( $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Last sync error:', 'wpcredits-program-manager' ),
				esc_html( $error )
			);
		}

		$this->render_sync_panel( WPCPM_Students_Sync::progress(), (int) get_option( WPCPM_Students_Sync::OPT_LAST ) );

		if ( is_array( $report ) ) {
			$this->render_report( $report );
		}
	}

	/**
	 * The warning while Airtable is not connected, on both tabs: the Sync tab's button cannot run
	 * without the connection, and the accounts the Accounts tab lists and invites arrive only by the
	 * sync, so the tab the screen opens on says why no more arrive.
	 */
	private function render_not_connected() {
		if ( WPCPM_Settings::is_connected() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Airtable is not connected yet, so no students can be synced.', 'wpcredits-program-manager' ),
			esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
			esc_html__( 'Open settings', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The invitations card, on the Accounts tab: everybody never invited, narrowed to the
	 * institution the list is showing.
	 *
	 * Its button posts the institution and the tab, so the press queues whom the card counted and
	 * comes back to the list at that institution. Its Stop and Dismiss are the mail layer's own
	 * forms, which name no tab: they come back to the address they were pressed on, this tab, and
	 * Stop's outcome waits on the flash channel this screen reads, which the card names in its form.
	 */
	private function render_invitations() {
		// **The card obeys the filter the list is showing.** Offering "invite 241 students" under a
		// list narrowed to one institution is how somebody emails two hundred people by accident.
		$filter  = self::institution_filter();
		$pending = WPCPM_Mail::only_institution( self::never_invited(), $filter );

		WPCPM_Mail::render_invite_card(
			array(
				'action'  => self::ACTION_BULK,
				'pending' => $pending,
				'noun'    => '' === $filter
					? __( 'students', 'wpcredits-program-manager' )
					/* translators: %s: institution name. */
					: sprintf( __( 'students at %s', 'wpcredits-program-manager' ), $filter ),
				'hidden'  => array(
					'wpcpm_institution' => $filter,
					self::TAB_FIELD     => self::TAB_ACCOUNTS,
				),
				'flash'   => $this->flash_key(),
			)
		);
	}

	/**
	 * Every Student account never sent an invitation, by the Student accounts table's own role and
	 * stamp, the reading its Never invited view counts by.
	 *
	 * The table's classes are loaded first: the invitations card's button posts to admin-post.php,
	 * where nothing else loads them.
	 *
	 * @return int[]
	 */
	private static function never_invited() {
		wpcpm_load_accounts_tables();

		return WPCPM_Mail::never_invited( WPCPM_Students_Table::role(), WPCPM_Students_Table::invite_meta() );
	}

	/**
	 * The Airtable sync card, on the Sync tab: the live progress and Cancel sync while a run is on,
	 * and otherwise when the last run finished and the button that starts one. Both forms name the
	 * tab, so the press comes back to it.
	 *
	 * @param array $progress Progress payload.
	 * @param int   $last     Timestamp of the last completed run.
	 */
	private function render_sync_panel( array $progress, $last ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Airtable sync', 'wpcredits-program-manager' ) . '</h2>';

		echo '<p class="description">' . esc_html__( 'Reads each student\'s program details, then their mentor\'s contact details - partly from Airtable, and the rest from the mentor\'s WordPress.org profile. Profile reads are cached for twelve hours.', 'wpcredits-program-manager' ) . '</p>';

		if ( $progress['running'] ) {
			printf(
				'<div class="wpcpm-progress" data-wpcpm-progress data-action="%1$s" data-nonce="%2$s" data-poll="3">',
				esc_attr( self::ACTION_TICK ),
				esc_attr( wp_create_nonce( self::ACTION_TICK ) )
			);

			echo '<p class="wpcpm-progress__head"><span class="spinner is-active" aria-hidden="true"></span> ';
			printf( '<strong data-wpcpm-label>%s</strong>', esc_html( $progress['label'] ) );
			printf( ' <span class="wpcpm-progress__step" data-wpcpm-step>%s</span>', esc_html( $progress['step_label'] ) );
			echo '</p>';

			printf(
				'<div class="wpcpm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s" data-wpcpm-bar><div class="wpcpm-bar__fill" style="width:%1$d%%" data-wpcpm-fill></div></div>',
				(int) $progress['percent'],
				esc_attr__( 'Sync progress', 'wpcredits-program-manager' )
			);

			echo '<p class="wpcpm-progress__meta">';
			printf( '<span data-wpcpm-percent>%d%%</span> · ', (int) $progress['percent'] );
			printf( '<span data-wpcpm-detail>%s</span> · ', esc_html( $progress['detail'] ) );
			/* translators: %s: elapsed time as a clock value. */
			$elapsed_label = __( 'running for %s', 'wpcredits-program-manager' );
			printf(
				'<span data-wpcpm-elapsed data-label="%1$s">%2$s</span>',
				esc_attr( $elapsed_label ),
				esc_html( sprintf( $elapsed_label, WPCPM_Mentors::format_duration( (int) $progress['elapsed'] ) ) )
			);
			echo '</p>';

			printf(
				'<p class="wpcpm-progress__stalled" data-wpcpm-stalled%1$s>%2$s</p>',
				$progress['stalled'] ? '' : ' hidden',
				esc_html__( 'No progress for over two minutes. The run may have been interrupted - cancel it and start again.', 'wpcredits-program-manager' )
			);

			echo '<noscript><meta http-equiv="refresh" content="15" /></noscript>';
			echo '</div>';

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
			submit_button( __( 'Sync students now', 'wpcredits-program-manager' ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * The last run's numbers.
	 *
	 * @param array $report Stored report.
	 */
	private function render_report( array $report ) {
		$stats = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();

		$labels = array(
			'students_seen' => __( 'Student records read', 'wpcredits-program-manager' ),
			'mentors_seen'  => __( 'Mentors read', 'wpcredits-program-manager' ),
			'profiles_read' => __( 'WordPress.org profiles read', 'wpcredits-program-manager' ),
			'created'       => __( 'Accounts created', 'wpcredits-program-manager' ),
			'linked'        => __( 'Existing accounts linked', 'wpcredits-program-manager' ),
			'updated'       => __( 'Accounts refreshed', 'wpcredits-program-manager' ),
			'assigned'      => __( 'Program details written', 'wpcredits-program-manager' ),
			'invited'       => __( 'Invitations sent', 'wpcredits-program-manager' ),
			'revoked'       => __( 'Student role revoked (no longer in the program)', 'wpcredits-program-manager' ),
			'no_mentor'     => __( 'Students with no mentor assigned', 'wpcredits-program-manager' ),
			'skipped'       => __( 'Students skipped (missing data)', 'wpcredits-program-manager' ),
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

	/**
	 * Which institution the screen is narrowed to, if any.
	 *
	 * The name itself rather than an ID: institutions arrive from Airtable as linked records that
	 * the sync has already resolved to names on each student's row, and there is no institution
	 * post or term on this site to hold an ID against.
	 *
	 * @return string Resolved institution name, or an empty string for all of them.
	 */
	public static function institution_filter() {
		return WPCPM_Request::text( 'wpcpm_institution' );
	}

	/**
	 * The Student accounts, in WordPress's list table: every account with the role, a page at a time.
	 *
	 * One GET form to this screen, as core's own lists are, so the view, the search, the institution,
	 * the sort and the page are the address, and a bulk action pressed in it is handled on the load
	 * hook before anything here is drawn. The heading counts the whole list, every page of it: until
	 * 1.117.1 the list read the first 500 accounts by display name and counted those, so the accounts
	 * after the cap were missing from the list and from their institution's count with no trace (13
	 * of 513 on 28 Sep 2026). The count now is WordPress's total, and the institution picker counts
	 * from a read of every account.
	 */
	private function render_student_list() {
		$table    = $this->table();
		$page_url = WPCPM_Students_Dashboard::page_url();

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Student accounts', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( (int) $table->get_pagination_arg( 'total_items' ) ) )
		);

		if ( $page_url ) {
			printf(
				'<p>%1$s <a href="%2$s">%2$s</a></p>',
				esc_html__( 'Student page:', 'wpcredits-program-manager' ),
				esc_url( $page_url )
			);
		} else {
			echo '<p class="wpcpm-warning">' . esc_html__( 'The student page is missing. Re-activate the plugin to recreate it.', 'wpcredits-program-manager' ) . '</p>';
		}

		$table->views();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB_ACCOUNTS ) );
		$table->view_field();
		// Escaped here: core prints the label as it is given.
		$table->search_box( esc_html__( 'Search students', 'wpcredits-program-manager' ), 'wpcpm-students' );
		$table->display();
		echo '</form>';

		echo '<p class="description">' . esc_html__( 'Accounts are created with a random password and no email. Usernames come from the student\'s WordPress.org profile where Airtable has one, and from their email address otherwise.', 'wpcredits-program-manager' ) . '</p>';
		echo '</div>';
	}
}
