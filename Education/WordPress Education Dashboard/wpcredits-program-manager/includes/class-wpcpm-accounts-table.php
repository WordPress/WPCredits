<?php
/**
 * The accounts list an audience's screen draws.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The plugin loads this file when a list is first needed (`wpcpm_load_accounts_tables()`: on a
// screen's load hook, as its rows-per-page choice is saved, for a list drawn without that hook, and
// where the invitations card and its button read the table's role and stamp), not as it loads. Core
// loads its list table for wp-admin requests (a screen, admin-ajax, admin-post) after plugins have
// loaded, so it is here already then; whatever loads this file anywhere else may find it missing,
// and the base loads core's class itself.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * One audience's accounts as a WordPress list table.
 *
 * What every audience's account list shares, drawn the way wp-admin draws its own lists: a
 * checkbox per row for the bulk actions, the views by invitation state (All, Invited, Never
 * invited), the search, the sortable columns, the pagination and its rows-per-page screen option,
 * and a row's invitation as a row action. An audience gives it four things: its name, its role, its
 * columns and its query.
 *
 * **Invited means the invitation stamp is there.** An invitation stamps the account's user meta
 * with the time it went out, under a key that follows the role: `WPCPM_Mail::drain_queue()` writes
 * it when the queue sends, and the Students and Mentors syncs' `send_invite()` when one invitation
 * goes. `WPCPM_Mail::never_invited()` reads the stamp's absence as "never invited", and the
 * invitations card counts the people it returns, so the views read presence and absence too: the
 * Never invited view counts by the card's reading, and a stamp of 0 is still a stamp.
 *
 * The table is one form, so a row's invitation cannot be a form of its own: it is a nonce link to
 * the audience's invite handler, drawn with the row's other actions under its primary cell as core
 * draws them (`handle_row_actions()`). The bulk actions carry the ticked accounts under the table's
 * own nonce. The columns a person hides under Screen Options stay hidden; the primary one is never
 * offered to hide (`columns_offered()`).
 *
 * **Built on an admin request only**, on the audience screen's `load-<hook>` or later, never on the
 * front end: core's constructor binds the table to the current screen through `convert_to_screen()`,
 * which lives in wp-admin, and the screen exists from the page's load hook on.
 */
abstract class WPCPM_Accounts_Table extends WP_List_Table {

	/** Rows a page, until somebody chooses another number under Screen Options. */
	const PER_PAGE_DEFAULT = 20;

	/** The most rows a page core saves for its own lists, and so for these. */
	const PER_PAGE_MAX = 999;

	/** The tab the table is drawn on, which every link it makes returns to. */
	const TAB = 'accounts';

	/** The field a form or a link carries its tab in, the name the Settings screen's tabs post too. */
	const TAB_FIELD = 'wpcpm_tab';

	/** The query argument naming the view. */
	const VIEW_ARG = 'wpcpm_view';

	/** The name each row's checkbox posts its account's ID under, as `users[]`. */
	const USERS_FIELD = 'users';

	/** What a list nobody sorted is sorted by. */
	const DEFAULT_ORDERBY = 'display_name';

	/**
	 * The audience: its module's ID, as in `wpcpm-<audience>`, the screen's address.
	 *
	 * @return string
	 */
	abstract protected static function audience();

	/**
	 * The role the audience's accounts hold.
	 *
	 * Public, so the audience's module reads the role its invitations check from the table, rather
	 * than holding a copy of its own.
	 *
	 * @return string
	 */
	abstract public static function role();

	/**
	 * The audience's columns, in order, as column => label. The checkbox comes before them.
	 *
	 * The first is the primary column, the one WordPress keeps on a narrow screen. The base draws a
	 * row's actions under it through `handle_row_actions()`, so an audience adds its own actions by
	 * extending `row_actions_for()` and never prints `row_actions()` in a cell itself, which would
	 * draw a second "Show more details" toggle. Screen Options never offers it to hide, whatever its
	 * key (`columns_offered()`). The labels are escaped here, before core prints them.
	 *
	 * @return array<string, string>
	 */
	abstract protected function columns();

	/**
	 * The accounts a set of `WP_User_Query` arguments finds, and how many there are in all.
	 *
	 * The table asks for two things. A page of rows: the role, the view's meta query, the search,
	 * the order and the page. A count: the role and one view's meta query, with `fields` of `ID`
	 * and one row, of which only the total is read. An audience narrowing its list (a filter of its
	 * own) narrows both, and appends to the meta query rather than replacing it, because the view is
	 * in it.
	 *
	 * The order is the audience's orderby with `order`, and for the name, the one sort a list starts
	 * in, `WP_User_Query`'s array form with the ID after the name, so two accounts of one name keep
	 * one order from page to page. An audience that sorts a column itself reads what the list is
	 * sorted by from the array's first key.
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	abstract protected function query( array $args );

	/**
	 * Make the table, its plural always the audience.
	 *
	 * Core names the bulk form's nonce `bulk-<plural>`, and a handler checks it by
	 * `bulk_nonce_action()`, so a plural passed in is not taken.
	 *
	 * @param array|string $args WP_List_Table's arguments.
	 */
	public function __construct( $args = array() ) {
		$args           = wp_parse_args( $args );
		$args['plural'] = static::audience();

		parent::__construct( $args );

		// Core's constructor hands the screen every column, for Screen Options to offer to hide; the
		// table answers in its place with every column but the primary one (`columns_offered()`).
		if ( isset( $this->screen->id ) ) {
			$filter = 'manage_' . $this->screen->id . '_columns';

			remove_filter( $filter, array( $this, 'get_columns' ), 0 );
			add_filter( $filter, array( $this, 'columns_offered' ), 0 );
		}
	}

	/**
	 * The columns Screen Options offers to hide: every column but the primary one.
	 *
	 * Core's Screen Options offers a box for each column the screen's `manage_{screen id}_columns`
	 * filter answers, but for a short list of names of its own (`name`, `title`, `username` and a
	 * few more), and core's constructor answers that filter with `get_columns()`. The primary column
	 * holds the row's actions and the toggle a narrow screen opens the other cells with, so it is
	 * never hidden (`prepare_items()`), and a box offered for it would come back unticked on every
	 * load while the column stayed. The table answers the filter with this instead, whatever the
	 * primary column is keyed. Public, as a filter's callback has to be.
	 *
	 * @return array<string, string>
	 */
	public function columns_offered() {
		return array_diff_key( $this->get_columns(), array( $this->primary_column() => true ) );
	}

	/**
	 * The rows-per-page option on the screen, under the audience's name. Called from the audience
	 * screen's load hook, where the table's class is loaded and the screen exists.
	 *
	 * No label: core's own, "Number of items per page:", is what its lists say.
	 */
	public static function add_per_page_option() {
		add_screen_option(
			'per_page',
			array(
				'default' => self::PER_PAGE_DEFAULT,
				'option'  => static::per_page_option(),
			)
		);
	}

	/**
	 * The value core stores for a rows-per-page choice: a whole number from 1 to 999, or false for none.
	 *
	 * The range core allows its own lists. False tells core to store nothing, so a mistyped number
	 * leaves the last good choice in place.
	 *
	 * **How an audience wires the save.** Core stores an option it does not know only when a filter
	 * named `set_screen_option_<option>` returns the value, and it applies that filter in
	 * `set_screen_options()`, before the menu, the screen's load hook or any table exists. So the
	 * audience's module adds the filter in its `boot()`, and the filter's callback loads the tables
	 * with `wpcpm_load_accounts_tables()` and returns this method's value, as
	 * `WPCPM_Students::save_per_page()` does.
	 *
	 * @param mixed  $keep   What core would store without this filter: false, which is nothing.
	 * @param string $option The option's name.
	 * @param mixed  $value  The number as submitted.
	 * @return int|false
	 */
	public static function per_page_to_save( $keep, $option, $value ) {
		$rows = (int) $value;

		return ( $rows >= 1 && $rows <= self::PER_PAGE_MAX ) ? $rows : false;
	}

	/**
	 * The user option the audience's rows-per-page choice is kept in: `wpcpm_<audience>_per_page`.
	 *
	 * @return string
	 */
	public static function per_page_option() {
		return 'wpcpm_' . static::audience() . '_per_page';
	}

	/**
	 * Rows a page for the person looking: their choice, or the default until they make one.
	 *
	 * Core's own reading, `get_items_per_page()`: the user option, anything that is not a positive
	 * number read as no choice, then the option's filter, which core's Screen Options box applies to
	 * the number it shows, so the box and the list agree.
	 *
	 * @return int
	 */
	public function per_page() {
		return $this->get_items_per_page( static::per_page_option(), self::PER_PAGE_DEFAULT );
	}

	/**
	 * The nonce the bulk form carries, which a handler for the bulk actions checks.
	 *
	 * Core prints it above the table as `bulk-<plural>`, and the plural is the audience.
	 *
	 * @return string
	 */
	public static function bulk_nonce_action() {
		return 'bulk-' . static::audience();
	}

	/**
	 * The checkbox, then the audience's columns, their labels escaped: core prints them as given.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array_merge(
			array( 'cb' => '<input type="checkbox" />' ),
			array_map( 'esc_html', $this->columns() )
		);
	}

	/**
	 * A row's checkbox, posting the account's ID under `users[]`, labeled with the account's name.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_cb( $user ) {
		$id = 'wpcpm-account-' . (int) $user->ID;

		return sprintf(
			'<input type="checkbox" name="%1$s[]" id="%2$s" value="%3$d" /><label for="%2$s"><span class="screen-reader-text">%4$s</span></label>',
			esc_attr( self::USERS_FIELD ),
			esc_attr( $id ),
			(int) $user->ID,
			esc_html(
				sprintf(
					/* translators: %s: the account's display name. */
					__( 'Select %s', 'wpcredits-program-manager' ),
					$user->display_name
				)
			)
		);
	}

	/**
	 * The columns the list sorts by, as column => `WP_User_Query` orderby.
	 *
	 * None unless the audience says. The map is also the whole of what a request may sort by,
	 * because the value goes into a query. A request picks the value it matches once both are
	 * through `sanitize_key()`, which lowercases, so a value such as `ID` can be chosen. The name's
	 * ties are settled by the ID (`prepare_items()`); a value two of the audience's accounts can
	 * share needs the audience's query to settle its ties the same way.
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array();
	}

	/**
	 * The sortable columns in WordPress's shape, the one the list starts sorted by marked as such.
	 *
	 * Arrays rather than bare orderby values, because the table sets its column headers itself and
	 * core reads each entry as an array. The fifth entry is the order a list nobody sorted starts
	 * in, which lets core mark that column's header as sorted before anybody chooses one.
	 *
	 * @return array<string, array>
	 */
	protected function get_sortable_columns() {
		$sortable = array();

		foreach ( $this->sortable_map() as $column => $orderby ) {
			$sortable[ $column ] = self::DEFAULT_ORDERBY === $orderby
				? array( $orderby, false, '', '', 'asc' )
				: array( $orderby, false );
		}

		return $sortable;
	}

	/**
	 * The views: All, Invited and Never invited, with how many accounts each holds.
	 *
	 * @return array<string, string> View => link.
	 */
	protected function get_views() {
		return $this->invite_views( $this->invite_counts() );
	}

	/**
	 * The three views' links, each with its count, the one in force marked as WordPress marks it.
	 *
	 * Each is the Accounts tab of the audience's screen, and names its view unless it is All. The
	 * words are escaped and the count's markup is the table's, so a translation cannot add markup.
	 *
	 * @param array{all: int, invited: int, never-invited: int} $counts Accounts in each view.
	 * @return array<string, string> View => link.
	 */
	protected function invite_views( $counts ) {
		$numbers = array(
			'all'           => isset( $counts['all'] ) ? (int) $counts['all'] : 0,
			'invited'       => isset( $counts['invited'] ) ? (int) $counts['invited'] : 0,
			'never-invited' => isset( $counts['never-invited'] ) ? (int) $counts['never-invited'] : 0,
		);

		$labels = array(
			/* translators: %s: how many accounts, in parentheses. */
			'all'           => _n( 'All %s', 'All %s', $numbers['all'], 'wpcredits-program-manager' ),
			/* translators: %s: how many accounts, in parentheses. */
			'invited'       => _n( 'Invited %s', 'Invited %s', $numbers['invited'], 'wpcredits-program-manager' ),
			/* translators: %s: how many accounts, in parentheses. */
			'never-invited' => _n( 'Never invited %s', 'Never invited %s', $numbers['never-invited'], 'wpcredits-program-manager' ),
		);

		$current = $this->current_view();
		$views   = array();

		foreach ( $labels as $view => $label ) {
			$views[ $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $this->page_url( 'all' === $view ? array() : array( self::VIEW_ARG => $view ) ) ),
				$current === $view ? ' class="current" aria-current="page"' : '',
				sprintf( esc_html( $label ), '<span class="count">(' . esc_html( number_format_i18n( $numbers[ $view ] ) ) . ')</span>' )
			);
		}

		return $views;
	}

	/**
	 * How many of the audience's accounts are invited and never invited, and so how many in all.
	 *
	 * Two counts through the audience's own query, so anything the audience narrows its list by
	 * narrows the counts the same way. Not the search: the views count the whole list, as
	 * WordPress's own views do. All is the sum, because a stamp is either there or not.
	 *
	 * @return array{all: int, invited: int, never-invited: int}
	 */
	protected function invite_counts() {
		$invited = $this->count_view( 'invited' );
		$never   = $this->count_view( 'never-invited' );

		return array(
			'all'           => $invited + $never,
			'invited'       => $invited,
			'never-invited' => $never,
		);
	}

	/**
	 * The view in force: `invited`, `never-invited`, or `all` for none or one the table does not have.
	 *
	 * @return string
	 */
	protected function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return in_array( $view, array( 'invited', 'never-invited' ), true ) ? $view : 'all';
	}

	/**
	 * The bulk actions: send an invitation to the ticked accounts, or send another.
	 *
	 * Public rather than core's protected, so code outside the table, a handler checking what was
	 * posted, can read them. Escaped, because core prints the labels as given.
	 *
	 * @return array<string, string> Action => label.
	 */
	public function get_bulk_actions() {
		return array(
			'invite'   => esc_html__( 'Send invite', 'wpcredits-program-manager' ),
			'reinvite' => esc_html__( 'Resend invite', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * A row's invitation: Send invite for an account never invited, Resend invite for one that was.
	 *
	 * A nonce link to the audience's invite handler, with the account as `user`, the tab to come back
	 * to and where the list stands (`list_state()`), under the nonce that handler already checks, its
	 * own action's. The audience adds its own actions, such as "View page", beside it.
	 *
	 * The link is a GET request, so the handler has to read the account from the query string as
	 * `user`, as `WPCPM_Students::handle_invite()` does after the posted `user_id` a form sends
	 * (`WPCPM_Request::posted_id( 'user_id' )`, then `WPCPM_Request::id( 'user' )`): a handler that
	 * reads the posted field alone finds no account behind the link and sends nothing.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string> `invite` or `reinvite` => the link.
	 */
	protected function invite_row_actions( WP_User $user ) {
		$action  = static::invite_action();
		$invited = metadata_exists( 'user', $user->ID, static::invite_meta() );
		$url     = wp_nonce_url(
			add_query_arg(
				// The link's own arguments win over the list's state, which only adds to them.
				array(
					'action'        => $action,
					'user'          => (int) $user->ID,
					self::TAB_FIELD => self::TAB,
				) + $this->list_state(),
				admin_url( 'admin-post.php' )
			),
			$action
		);

		return array(
			( $invited ? 'reinvite' : 'invite' ) => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				$invited ? esc_html__( 'Resend invite', 'wpcredits-program-manager' ) : esc_html__( 'Send invite', 'wpcredits-program-manager' )
			),
		);
	}

	/**
	 * Where the list stands, which a row's invitation carries to the audience's handler so the handler
	 * can come back to it: the view, the search, the sort, the page and whatever else the audience
	 * narrows the list by, as query arguments, each encoded. None unless the audience says, since the
	 * handler, and the address it comes back to, are the audience's.
	 *
	 * @return array<string, string>
	 */
	protected function list_state() {
		return array();
	}

	/**
	 * The row's actions, drawn under its primary cell the way core's own lists draw them.
	 *
	 * Core's `single_row_columns()` adds this after every cell but the checkbox, and core's default
	 * gives the primary cell the "Show more details" toggle. `row_actions()` ends in that toggle
	 * already, so drawing the actions here, in place of the default, leaves one toggle a row.
	 *
	 * @param WP_User $item        The row's account.
	 * @param string  $column_name The cell's column.
	 * @param string  $primary     The primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return $column_name === $primary ? $this->row_actions( $this->row_actions_for( $item ) ) : '';
	}

	/**
	 * A row's actions, as action => link: the invitation, which an audience extends with its own,
	 * such as "View page" or the account's editor.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		return $this->invite_row_actions( $user );
	}

	/**
	 * The user meta an invitation to the audience's accounts is stamped in.
	 *
	 * The key `WPCPM_Mail::drain_queue()` stamps an account holding this role alone under: its three
	 * named roles' own, and for every other role its last branch, the student stamp. The syncs'
	 * `send_invite()` write the same keys for students and mentors, so the key read here is the one
	 * the send wrote. An account holding two roles is stamped under drain_queue()'s first match, and
	 * each audience reads its own key, as its invitations card does.
	 *
	 * Public, so the audience's module reads the stamp its invitations and its invitations card count
	 * by from the table, the reading the views use, rather than holding a copy of its own.
	 *
	 * @return string
	 */
	public static function invite_meta() {
		$stamps = array(
			WPCPM_Roles::ROLE_MENTOR      => 'wpcpm_mentor_invited',
			WPCPM_Roles::ROLE_INSTITUTION => 'wpcpm_inst_invited',
			WPCPM_Roles::ROLE_SPONSOR     => 'wpcpm_sponsor_invited',
		);

		$role = static::role();

		return isset( $stamps[ $role ] ) ? $stamps[ $role ] : 'wpcpm_student_invited';
	}

	/**
	 * The audience's handler for one invitation: `wpcpm_<audience>_invite`, as the Students and
	 * Mentors modules name theirs.
	 *
	 * @return string
	 */
	protected static function invite_action() {
		return 'wpcpm_' . static::audience() . '_invite';
	}

	/**
	 * The search as `WP_User_Query` takes a "contains" search: the term with a wildcard at each end.
	 *
	 * An empty string when nothing was searched for.
	 *
	 * @return string
	 */
	protected function search_clause() {
		$term = WPCPM_Request::text( 's' );

		return '' === $term ? '' : '*' . $term . '*';
	}

	/**
	 * Read one page of the view in force, searched and sorted, and set what the table draws.
	 *
	 * The search covers the name, the username and the email; an audience searching its own columns
	 * too does it in its query.
	 */
	public function prepare_items() {
		$per_page = $this->per_page();
		$orderby  = $this->orderby();
		$order    = $this->order();

		// Names are not unique, and the database may return two accounts of one name in either order,
		// and in another for another page, so one could show twice and the other on no page: the ID
		// after the name settles it. The other sorts are the audience's to settle.
		if ( self::DEFAULT_ORDERBY === $orderby ) {
			$orderby = array(
				self::DEFAULT_ORDERBY => $order,
				'ID'                  => $order,
			);
		}

		$args = array(
			'role'        => static::role(),
			'number'      => $per_page,
			'offset'      => ( $this->get_pagenum() - 1 ) * $per_page,
			'orderby'     => $orderby,
			'order'       => $order,
			'count_total' => true,
			'meta_query'  => $this->view_clause( $this->current_view() ),
		);

		$search = $this->search_clause();

		if ( '' !== $search ) {
			$args['search']         = $search;
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$found = $this->query( $args );

		$this->items = isset( $found['items'] ) ? (array) $found['items'] : array();

		$this->set_pagination_args(
			array(
				'total_items' => isset( $found['total'] ) ? (int) $found['total'] : 0,
				'per_page'    => $per_page,
			)
		);

		// Set here rather than worked out by core from the screen, so the table draws the same with or
		// without one (most suites have none): its own columns, its first as the primary one. The
		// columns a person unticked under Screen Options stay hidden, as on core's own lists, once the
		// table is built on the screen's load hook. The primary column is never hidden, because it
		// holds the row's actions and the toggle a narrow screen opens the other cells with: Screen
		// Options is never offered it (`columns_offered()`), and one hidden all the same, by a choice
		// saved before or a filter on core's hidden columns, is drawn.
		$hidden = isset( $this->screen )
			? array_values( array_diff( get_hidden_columns( $this->screen ), array( $this->primary_column() ) ) )
			: array();

		$this->_column_headers = array( $this->get_columns(), $hidden, $this->get_sortable_columns(), $this->primary_column() );
	}

	/**
	 * The sentence in the empty row. An audience overrides it to say how its accounts arrive.
	 *
	 * Public, as core declares it: a subclass cannot narrow a method core made public.
	 */
	public function no_items() {
		esc_html_e( 'No accounts found.', 'wpcredits-program-manager' );
	}

	/**
	 * The Accounts tab of the audience's screen, with any query arguments added.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	protected function page_url( array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'wpcpm-' . static::audience(),
					'tab'  => self::TAB,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * One view's count, through the audience's query: one ID fetched, the total counted.
	 *
	 * @param string $view `invited` or `never-invited`.
	 * @return int
	 */
	private function count_view( $view ) {
		$found = $this->query(
			array(
				'role'        => static::role(),
				'number'      => 1,
				'fields'      => 'ID',
				'count_total' => true,
				'meta_query'  => $this->view_clause( $view ),
			)
		);

		return isset( $found['total'] ) ? (int) $found['total'] : 0;
	}

	/**
	 * A view's meta query: the stamp there for Invited, not there for Never invited, none for All.
	 *
	 * @param string $view `all`, `invited` or `never-invited`.
	 * @return array
	 */
	private function view_clause( $view ) {
		if ( 'invited' !== $view && 'never-invited' !== $view ) {
			return array();
		}

		return array(
			array(
				'key'     => static::invite_meta(),
				'compare' => 'invited' === $view ? 'EXISTS' : 'NOT EXISTS',
			),
		);
	}

	/**
	 * What the list is sorted by: the audience's orderby that the request names, compared once both
	 * are through `sanitize_key()`, and the name otherwise.
	 *
	 * @return string
	 */
	private function orderby() {
		$asked = WPCPM_Request::key( 'orderby' );

		foreach ( $this->sortable_map() as $orderby ) {
			if ( '' !== $asked && sanitize_key( $orderby ) === $asked ) {
				return $orderby;
			}
		}

		return self::DEFAULT_ORDERBY;
	}

	/**
	 * Z to A when asked for, A to Z otherwise.
	 *
	 * @return string `ASC` or `DESC`.
	 */
	private function order() {
		return 'desc' === WPCPM_Request::key( 'order' ) ? 'DESC' : 'ASC';
	}

	/**
	 * The primary column: the audience's first.
	 *
	 * @return string
	 */
	private function primary_column() {
		$columns = array_keys( $this->columns() );

		return isset( $columns[0] ) ? (string) $columns[0] : '';
	}
}
