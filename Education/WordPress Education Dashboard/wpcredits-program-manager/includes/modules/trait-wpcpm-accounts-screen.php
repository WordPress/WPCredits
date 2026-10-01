<?php
/**
 * The screen an audience's accounts are managed on.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What every audience's accounts screen does the same way, whichever audience it lists: the list's
 * load hook, its form and the bulk invitation pressed in it, or a bulk action the audience's table
 * carries out itself, the rows-per-page save, one row's invitation, the way back to the list as it
 * stood, the count a press on the ticked accounts carries beside its outcome, the tab bar and the
 * tab the screen opens on, and the Accounts tab with its list.
 *
 * **One copy, so the screens cannot drift apart.** The Students and Mentors modules held these
 * methods twice, the same but for the audience's name. The capability and the nonce checked before
 * anything else of a press is read (`handle_list_form()`), and the count written with every outcome
 * and read once (`leave()`, `notice_sentence()`), are behaviors a later fix would otherwise have to
 * make in each copy, and could miss in one.
 *
 * A trait rather than a class between the sync module and the audiences, so a module takes it
 * whatever it extends. It reads what every module has from the module base (`WPCPM_Module`): the
 * capability-then-nonce check (`verify()`), a tab's address (`tab_url()`), the notices
 * (`render_notice_from()`), the flash channel (`flash_key()`) and the field a form names its tab in
 * (`TAB_FIELD`); and the sync (`sync_class()`), which the sync module the Students and Mentors
 * modules extend asks of them (`WPCPM_Sync_Module`), and a module without a sync provides itself.
 * From the module it reads:
 *
 * - through `static::`, the constants a trait cannot hold on PHP 7.4: `TABS`, `TAB_ACCOUNTS`,
 *   `TAB_SYNC`, `PER_PAGE_OPTION`, `FLASH_DETAIL` and `ACTION_INVITE`;
 * - the audience's accounts table (`table_class()`), where its list stands (`list_state()`), the
 *   words the screen prints for the audience (`screen_words()`) and the audience's own page on the
 *   site (`dashboard_url()`);
 * - its Sync tab (`render_tab_sync()`) and its invitations card (`render_invitations()`), which
 *   differ from audience to audience.
 *
 * Only the three methods that draw the tabs read what a screen with a Sync tab and invitations has
 * (`TAB_SYNC`, `render_tab_sync()`, `render_invitations()`): a module that replaces those three,
 * `tab_labels()`, `render_admin_page()` and `render_tab_accounts()`, as the Administrators module
 * does for a screen with neither, declares none of them. A class's own method replaces the trait's,
 * and bin/check-references.php, which checks the trait's references in each class that uses it,
 * leaves out the ones in a method the class replaces, unless the class keeps the trait's method
 * under another name with `as`, as the Institutions module keeps `load_screen()`: the class still
 * runs that body, under the other name, so its references are still the class's to answer for.
 */
trait WPCPM_Accounts_Screen {

	/**
	 * The audience's accounts table, built on the screen's load hook, or for a list drawn without it.
	 *
	 * @var WPCPM_Accounts_Table|null
	 */
	private $table;

	/**
	 * The audience's accounts table, as a class name: the list the screen draws, whose role and
	 * invitation stamp the invitations read, and whose rule the rows-per-page save keeps.
	 *
	 * Static, because the rows-per-page save reads it in a filter WordPress calls on the module's
	 * class rather than on a module (`save_per_page()`).
	 *
	 * @return string A class extending WPCPM_Accounts_Table.
	 */
	abstract protected static function table_class();

	/**
	 * The words the screen prints for its audience, where the parts every accounts screen shares
	 * print them:
	 *
	 * - `heading_views`, `heading_pagination` and `heading_list`: the headings core prints for screen
	 *   readers above the list's views, its pagination and the list itself (`load_screen()`);
	 * - `not_connected`: the warning while Airtable is not connected (`render_not_connected()`);
	 * - `list_heading`, the list card's heading before its count; `page_label`, before the address of
	 *   the audience's own page, and `page_missing`, the warning while that page is missing;
	 *   `search`, the search box's label and button; and `list_note`, the paragraph under the list
	 *   (`render_accounts_list()`).
	 *
	 * Translated where the module writes each out, since the translation tools collect a string only
	 * where it is written as one, or, for a sentence another screen prints too, where its owner
	 * writes it (`WPCPM_Administrators_Dashboard::page_missing()` for the Administrators screen's
	 * `page_missing`), and escaped here, where each is printed.
	 *
	 * @return array<string, string>
	 */
	abstract protected function screen_words();

	/**
	 * The address of the audience's own page on the site, which the list card names: '' while the
	 * page is missing, which the card says instead.
	 *
	 * @return string
	 */
	abstract protected function dashboard_url();

	/**
	 * Where the list stands, as the request says, each value encoded and the empty ones left out:
	 * what a press in the list comes back to (`list_url()`), and what each row's invitation link
	 * carries. The module's own, because each audience narrows its list by its own filters; public,
	 * because the audience's table reads it too.
	 *
	 * @return array<string, string>
	 */
	abstract public static function list_state();

	/**
	 * The screen's two hooks, which the module adds as it boots.
	 */
	private function boot_screen() {
		// After the menu is built, at 10: the screen's hook is named after the menu, which does not
		// exist before then.
		add_action( 'admin_menu', array( $this, 'hook_screen' ), 11 );

		// Here rather than on the screen's load hook: WordPress saves a screen option before it builds
		// the menu, so a filter added any later misses the request that saves.
		add_filter( 'set_screen_option_' . static::PER_PAGE_OPTION, array( __CLASS__, 'save_per_page' ), 10, 3 );
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
	 * Before the screen draws its Accounts tab: the list's classes, its rows-per-page option, the
	 * table, a press in the list's form, and the page of rows the list shows.
	 *
	 * Core's own lists work this way. The table is built here, before the page's header, because
	 * core lists a table's columns under Screen Options from the filter its constructor adds, and the
	 * rows are read here, where core can still redirect a page past the last one. A bulk action is
	 * handled here because the list is a GET form to this screen: its bulk select is named `action`
	 * by core, which would route a post to admin-post.php to a handler of the action's own name.
	 *
	 * Nothing here runs for the screen's other tabs, which have no list: they read no account for
	 * one, and Screen Options offers them no rows-per-page choice or columns for a list they do not
	 * draw. The list's form names the Accounts tab and its links are built on the Accounts tab's
	 * address, so a press in the list is always a request for that tab.
	 */
	public function load_screen() {
		if ( static::TAB_ACCOUNTS !== self::tab() ) {
			return;
		}

		wpcpm_load_accounts_tables();

		$table_class = static::table_class();
		$table_class::add_per_page_option();

		// The headings core prints for screen readers above the views, the pagination and the list:
		// set here or not printed at all, as core's own list screens set theirs. Escaped here, because
		// core prints them as they are given.
		$screen = get_current_screen();

		if ( $screen ) {
			$words = $this->screen_words();

			$screen->set_screen_reader_content(
				array(
					'heading_views'      => esc_html( $words['heading_views'] ),
					'heading_pagination' => esc_html( $words['heading_pagination'] ),
					'heading_list'       => esc_html( $words['heading_list'] ),
				)
			);
		}

		$table = new $table_class();

		$this->handle_list_form( $table );

		$table->prepare_items();

		$this->table = $table;
	}

	/**
	 * What the list's form sent, if it was sent: a bulk action, or a search, a view, a sort, a page
	 * or a filter of the audience's own.
	 *
	 * Send invite and Resend invite go through the capability and the list's nonce before anything
	 * else of the request is read. So does any other bulk action the audience's table carries out
	 * itself, such as Create account on a view of the records that have no account: the table says
	 * whether the action is its own by the action's name alone (`WPCPM_Accounts_Table::owns_action()`),
	 * and carries it out only once both are checked (`handle_action()`). What came of it is flashed
	 * and the press comes back to the list (`leave()`), whose tab prints the outcome from its message
	 * map, which has to hold it: in the table's own words for it where it has any
	 * (`action_sentence()`), and otherwise in the map's, never in the invitations'
	 * (`notice_sentence()`). The action is the table's to name and to carry out, so this holds no
	 * branch for any one audience's, and an action no table owns does nothing. Anything else sent from
	 * the form comes back to the same list without the form's own fields, as core's own lists do, so
	 * the nonce never stays in the address or in the page and sort links built from it.
	 *
	 * @param WPCPM_Accounts_Table $table The table the form belongs to.
	 */
	private function handle_list_form( WPCPM_Accounts_Table $table ) {
		$action = $table->current_action();

		if ( 'invite' === $action || 'reinvite' === $action ) {
			$this->verify( $table::bulk_nonce_action() );
			$this->invite_selected( WPCPM_Request::ids( $table::USERS_FIELD ), 'reinvite' === $action );
		} elseif ( is_string( $action ) && '' !== $action && $table->owns_action( $action ) ) {
			$this->verify( $table::bulk_nonce_action() );

			list( $status, $detail ) = $table->handle_action( $action );

			$this->leave( $this->list_url(), $status, $detail );
		}

		if ( '' !== WPCPM_Request::text( '_wp_http_referer' ) ) {
			wp_safe_redirect( $this->list_url() );
			exit;
		}
	}

	/**
	 * The rows-per-page choice as core may store it, by the audience's accounts table's rule.
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

		$table_class = static::table_class();

		return $table_class::per_page_to_save( $keep, $option, $value );
	}

	/**
	 * Email one person on the list their login invitation.
	 *
	 * From a row of the list, whose invitation is a link carrying the account as `user`, where the list
	 * stands and the handler's nonce, or from a form that posts the account as `user_id`, as each row
	 * did before the list became one form. Sent at once by the module's own sync (`sync_class()`), as
	 * it always was for one person, then back to the list as the link says it stood, on the Accounts
	 * tab, the way a press on the ticked accounts comes back (`list_url()`); a posted form carries no
	 * list, and comes back to the tab itself.
	 */
	public function handle_invite() {
		$this->verify( static::ACTION_INVITE );

		$user_id = WPCPM_Request::posted_id( 'user_id' );

		if ( ! $user_id ) {
			$user_id = WPCPM_Request::id( 'user' );
		}

		$sync   = $this->sync_class();
		$result = $sync::send_invite( $user_id );

		$this->leave( $this->list_url(), WPCPM_Mail::invite_outcome( $result ) );
	}

	/**
	 * Queue invitations for the ticked accounts, then back to the list, saying how many.
	 *
	 * The arithmetic is the accounts base's (`WPCPM_Accounts_Table::queue_ticked()`), called on the
	 * audience's own table (`table_class()`) for its role and stamps: which ticked accounts are
	 * queued, by which of the mail layer's two ways in, and why none was. The words for what came of
	 * it are the base's too, which the screen prints (`notice_sentence()`); the way back to the list
	 * is the screen's.
	 *
	 * Private: reached only from the list's form on the screen's load hook (`handle_list_form()`),
	 * which checks the capability and the list's nonce before it reads the ticked IDs.
	 *
	 * @param int[] $ids    The ticked accounts.
	 * @param bool  $resend Resend invite, rather than Send invite.
	 */
	private function invite_selected( array $ids, $resend ) {
		$table_class = static::table_class();

		list( $status, $detail ) = $table_class::queue_ticked( $ids, $resend );

		$this->leave( $this->list_url(), $status, $detail );
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
	 * @param array  $detail What the press adds: for the invitations `resend`, `queued` or `why`, and
	 *                       `recent`, how many a Resend invite left out for the fifteen minutes; for a
	 *                       bulk action the table carries out itself, what its `handle_action()`
	 *                       returned beside the outcome.
	 */
	private function leave( $url, $status, array $detail = array() ) {
		WPCPM_Flash::set( $this->flash_key(), $status );
		WPCPM_Flash::set( static::FLASH_DETAIL, empty( $detail ) ? '' : array_merge( array( 'status' => $status ), $detail ) );

		wp_safe_redirect( WPCPM_Return::url( $url ) );
		exit;
	}

	/**
	 * Every account of the audience never sent an invitation of any kind, by its accounts table's own
	 * role and stamp, which says whether the plugin invites the role at all, the reading its Never
	 * invited view counts by.
	 *
	 * The table's classes are loaded first: the invitations card's button posts to admin-post.php,
	 * where nothing else loads them.
	 *
	 * @return int[]
	 */
	private static function never_invited() {
		wpcpm_load_accounts_tables();

		$table_class = static::table_class();

		return WPCPM_Mail::never_invited( $table_class::role(), $table_class::invite_meta() );
	}

	/**
	 * The screen's Accounts tab.
	 *
	 * @return string
	 */
	private function accounts_url() {
		return $this->tab_url( static::TAB_ACCOUNTS );
	}

	/**
	 * The Accounts tab as the list was showing it (`list_state()`).
	 *
	 * @return string
	 */
	private function list_url() {
		return add_query_arg( static::list_state(), $this->accounts_url() );
	}

	/**
	 * The audience's accounts table the load hook built, or one built and read now for a list drawn
	 * without it.
	 *
	 * @return WPCPM_Accounts_Table
	 */
	private function table() {
		if ( null === $this->table ) {
			wpcpm_load_accounts_tables();

			$table_class = static::table_class();
			$this->table = new $table_class();
			$this->table->prepare_items();
		}

		return $this->table;
	}

	/**
	 * The outcomes the Accounts tab prints, the presses made on it: a row's invitation's, worded once
	 * for every audience's screen (`WPCPM_Mail::invite_notices()`), with the `error` its failed send
	 * leaves, and the invitations'. The sync's own print on the Sync tab.
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
	 * The sentence for a press on the ticked accounts: how many invitations were queued or why none
	 * was, or what a bulk action the audience's table carries out itself did.
	 *
	 * The detail is one-shot, so it is read here, on the one status being printed, and used only when
	 * it was carried for that very status: the invitations card's button leaves the same two
	 * statuses with no count, and keeps the map's sentences.
	 *
	 * The invitations' two outcomes are worded by the accounts base, one wording for every audience's
	 * list (`WPCPM_Accounts_Table::selected_sentence()`). Any other outcome carrying a detail is a
	 * table's own action's (`handle_action()`), worded by the audience's table
	 * (`action_sentence()`), or by the map when the table has no words for it, since the
	 * invitations' words would tell of invitations that action never sent. The tables are loaded
	 * first: a tab drawn without the screen's load hook has loaded none (`table()`).
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from the map.
	 * @return string
	 */
	protected function notice_sentence( $status, $sentence ) {
		$detail = WPCPM_Flash::take( static::FLASH_DETAIL );

		if ( ! is_array( $detail ) || ! isset( $detail['status'] ) || (string) $status !== (string) $detail['status'] ) {
			return (string) $sentence;
		}

		wpcpm_load_accounts_tables();

		if ( in_array( (string) $status, array( 'invites-queued', 'invites-none' ), true ) ) {
			return WPCPM_Accounts_Table::selected_sentence( $detail );
		}

		$table_class = static::table_class();
		$worded      = (string) $table_class::action_sentence( (string) $status, $detail );

		return '' === $worded ? (string) $sentence : $worded;
	}

	/**
	 * The tab the screen shows: the one its address names, or the screen's first tab when it names
	 * none or one the screen does not have.
	 *
	 * The first tab is the default, the rule every audience's screen follows, so a screen's default
	 * is the order of its TABS. The menu opens a screen at its own address, which names no tab: the
	 * Students, Mentors and Administrators screens open on Accounts, their first tab, and the sort
	 * and page links core builds from that address, which name no tab either, open the list too. A
	 * screen whose first tab is another, such as a queue, opens on that one and reaches its list at an
	 * address naming the Accounts tab, which the list's form, its views and the links core builds from
	 * that address all name in turn.
	 *
	 * @return string A key of TABS.
	 */
	public static function tab() {
		$tab = WPCPM_Request::key( 'tab' );

		return isset( static::TABS[ $tab ] ) ? $tab : (string) array_key_first( static::TABS );
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
			static::TAB_ACCOUNTS => __( 'Accounts', 'wpcredits-program-manager' ),
			static::TAB_SYNC     => __( 'Sync', 'wpcredits-program-manager' ),
		);

		return array_merge( static::TABS, array_intersect_key( $translated, static::TABS ) );
	}

	/**
	 * The hidden field naming the tab a form is on, so its press comes back to it (`redirect_back()`).
	 *
	 * @param string $tab A key of TABS.
	 */
	private function tab_field( $tab ) {
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( static::TAB_FIELD ), esc_attr( $tab ) );
	}

	/**
	 * Render the screen: the tab bar, then the tab shown.
	 *
	 * Accounts holds the invitations and the list, Sync the Airtable sync and what its last run left,
	 * drawn by the module (`render_tab_sync()`). A press comes back to the tab it was made on, and
	 * each tab prints the notices of the presses made on it.
	 */
	public function render_admin_page() {
		$tab = self::tab();

		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html( $this->label() ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html( $this->description() ) . '</p>';

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tab_labels(), $tab );

		if ( static::TAB_SYNC === $tab ) {
			$this->render_tab_sync();
		} else {
			$this->render_tab_accounts();
		}

		echo '</div>';
	}

	/**
	 * The Accounts tab: the notices of the presses made on it, the warning while Airtable is not
	 * connected, the invitations card, which is the module's (`render_invitations()`), and the
	 * audience's accounts list.
	 */
	private function render_tab_accounts() {
		$this->render_notice_from( $this->accounts_messages() );
		$this->render_not_connected();
		$this->render_invitations();
		$this->render_accounts_list();
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

		$words = $this->screen_words();

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( $words['not_connected'] ),
			esc_url( admin_url( 'admin.php?page=wpcpm-settings' ) ),
			esc_html__( 'Open settings', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The audience's accounts, in WordPress's list table: every account with the role, a page at a
	 * time.
	 *
	 * One GET form to this screen, as core's own lists are, so the view, the search, the sort, the
	 * page and any filter of the audience's own are the address, and a bulk action pressed in it is
	 * handled on the load hook before anything here is drawn. The heading counts the whole list, every
	 * page of it: until 1.117.1 the Students screen read the first 500 accounts by display name and
	 * counted those, which hid 13 of its 513 accounts with no trace on 28 Sep 2026, the Mentors screen
	 * asked for the same first 500 until 1.117.2, and the Administrators screen for the first 200
	 * until 1.117.3. The count is the table's (`heading_count()`), every account the list holds: from
	 * WordPress, or for a sort the table orders itself, from the table's own read of every account;
	 * and on a record view, whose rows are records, the accounts the list holds in all, since the
	 * heading names accounts. The note under the list says how accounts are made and invited, so it
	 * is left out on a record view (`is_record_view()`).
	 */
	private function render_accounts_list() {
		$table    = $this->table();
		$words    = $this->screen_words();
		$page_url = $this->dashboard_url();

		echo '<div class="wpcpm-card">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html( $words['list_heading'] ),
			esc_html( number_format_i18n( $table->heading_count() ) )
		);

		if ( $page_url ) {
			printf(
				'<p>%1$s <a href="%2$s">%2$s</a></p>',
				esc_html( $words['page_label'] ),
				esc_url( $page_url )
			);
		} else {
			echo '<p class="wpcpm-warning">' . esc_html( $words['page_missing'] ) . '</p>';
		}

		$table->views();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->page_slug() ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( static::TAB_ACCOUNTS ) );
		$table->view_field();
		// Escaped here: core prints the label as it is given.
		$table->search_box( esc_html( $words['search'] ), $this->page_slug() );
		$table->display();
		echo '</form>';

		if ( ! $table->is_record_view() ) {
			echo '<p class="description">' . esc_html( $words['list_note'] ) . '</p>';
		}

		echo '</div>';
	}
}
