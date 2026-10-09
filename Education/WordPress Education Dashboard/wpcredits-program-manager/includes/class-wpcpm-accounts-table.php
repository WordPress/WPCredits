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
// screen's load hook, as its rows-per-page choice is saved, for a list drawn without that hook, where
// the invitations card and its button read the table's role and stamp, and where a screen prints
// what a press on the ticked accounts did in the base's words), not as it loads. Core loads its list
// table for wp-admin requests (a screen, admin-ajax, admin-post) after plugins have loaded, so it is
// here already then; whatever loads this file anywhere else may find it missing, and the base loads
// core's class itself.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * One audience's accounts as a WordPress list table.
 *
 * What every audience's account list shares, drawn the way wp-admin draws its own lists: a
 * checkbox per row for the bulk actions, the views by invitation state (All, Invited, Never
 * invited), the search, the sortable columns, the pagination and its rows-per-page screen option,
 * and a row's invitation as a row action. An audience's list draws all of it unless the audience
 * leaves a part out, as the Administrators list leaves out the checkbox, the bulk actions, the
 * invitation views and a row's invitation, because nothing is pressed on administrators
 * (`WPCPM_Administrators_Table`). An audience gives it four things: its name, its role, its columns
 * and its query. The cells every audience's list draws alike are the base's too, for an audience
 * with those columns: the name, linked to the account's editor, and the username (`column_name()`,
 * `column_login()`), and a row's Edit, which an audience puts among its row's actions
 * (`edit_row_action()`).
 *
 * **Invited means an invitation stamp is there, any of them.** An invitation stamps the account's
 * user meta with the time it went out, under the key of each program role the account holds, by the
 * one rule the queue and each sync's `send_invite()` stamp by (`WPCPM_Mail::stamp_invited()`), and a
 * role the plugin never invites has no key of its own (`invite_meta()`). An account has one
 * password, so one stamped under any of its roles was sent an invitation:
 * `WPCPM_Mail::never_invited()` reads the absence of every stamp as "never invited", and the
 * invitations card counts the people it returns, so the views, a row's invitation and the bulk
 * actions read the presence and absence of the same stamps (`stamps_read()`): the Never invited view
 * counts by the card's reading, and a stamp of 0 is still a stamp.
 *
 * The table is one form, so a row's invitation cannot be a form of its own: it is a nonce link to
 * the audience's invite handler, drawn with the row's other actions under its primary cell as core
 * draws them (`handle_row_actions()`). The bulk actions carry the ticked accounts under the table's
 * own nonce, and what they queue is worked out here for every audience (`queue_ticked()`), and so
 * are the words for what came of it (`selected_sentence()`): the audience's handler checks the
 * capability and the nonce, makes the call and carries what came of it back to its list, where its
 * screen prints it in those words. The columns a person hides under Screen Options stay hidden; the
 * primary one is never offered to hide (`columns_offered()`).
 *
 * **A record view lists what has no account yet.** Two audiences keep records before any account
 * exists for them, an institution's and a sponsor's, and their lists need a view of those records,
 * with a bulk action that creates the missing accounts. A table adds such a view through the record
 * seams: its views and their counts (`record_views()`, `count_records()`), its rows
 * (`record_rows()`), its columns and sorts (`record_columns()`, `record_sortable()`), a row's own
 * actions (`record_row_actions()`) and its bulk actions (`record_bulk_actions()`), which the table
 * carries out itself (`owns_action()`, `handle_action()`). The base draws a record view as it draws
 * the account views: its link after the three invitation views, a row per record, paged here, each
 * record's actions under its primary cell, and each record's checkbox posting its ID under
 * `records[]` (`RECORDS_FIELD`). The invitation views go on counting accounts alone, since a record
 * is none. Every seam answers none here, so a list that keeps no records draws its three invitation
 * views and its accounts alone. A record view shares the screen's one list of hidden columns with
 * the account views: core keeps one list a screen, and its script saves only the columns of the
 * table on the page, so a column hidden under Screen Options on one kind of view forgets one hidden
 * on the other. A column key both kinds have, hidden on one and primary on the other, is drawn all
 * the same, since the primary column is never hidden (`prepare_items()`).
 *
 * **What is said around the list, and which rows may be ticked, are the table's to say too.** A
 * table prints what it has to say above its views (`list_intro()`) and under its table
 * (`list_outro()`), such as what a record view's bulk action sends, which the list's Apply has no
 * dialog to say; and it leaves the checkbox out of a row its bulk action could only refuse
 * (`row_tickable()`). The base says nothing there and lets every row be ticked.
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

	/** The name each record row's checkbox posts its record's ID under, as `records[]`, on a record view. */
	const RECORDS_FIELD = 'records';

	/** What a list nobody sorted is sorted by. */
	const DEFAULT_ORDERBY = 'display_name';

	/**
	 * The id of the list's card, which the list's form is sent to and a link that undoes a filter lands on,
	 * so a reload of the screen opens at the list and not at the top. The admin stylesheet names it too.
	 */
	const LIST_ANCHOR = 'wpcpm-accounts-list';

	/**
	 * How many accounts each invitation view holds, read once a table (`invite_counts()`).
	 *
	 * @var array{all: int, invited: int, never-invited: int}|null
	 */
	private $invite_counts;

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
	 * The audience's columns, in order, as column => label. The base's `get_columns()` puts the
	 * checkbox before them, for the bulk actions, and a list that offers none leaves it out with a
	 * `get_columns()` of its own, as the Administrators list does.
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
	 * sorted by with `sorted_by()`, which reads the array form's first key.
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
	 * audience's module adds the filter as it boots, and the filter's callback loads the tables with
	 * `wpcpm_load_accounts_tables()` and returns this method's value, as the accounts screen every
	 * audience's module shares does (`WPCPM_Accounts_Screen::boot_screen()`, `save_per_page()`).
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
	 * The checkbox, then the view's columns, their labels escaped: core prints them as given. The
	 * view's columns are the audience's (`columns()`), or on a record view the view's own
	 * (`record_columns()`), the checkbox before them too, for the view's bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array_merge(
			array( 'cb' => '<input type="checkbox" />' ),
			array_map( 'esc_html', $this->view_columns() )
		);
	}

	/**
	 * A row's checkbox, labeled with the row's name: an account's ID under `users[]`, labeled with
	 * the account's display name, or on a record view a record's ID under `records[]`, labeled with
	 * the record's name, or with its ID where it has no name. One markup for both, so a change to how
	 * a row is ticked reaches both.
	 *
	 * A row the table says may not be ticked draws none (`row_tickable()`), asked before anything
	 * else. A record row whose ID is missing, empty or not a scalar draws none either: a box with no
	 * ID would post nothing, and every such row would share one HTML id, which its label would tick in
	 * its place.
	 *
	 * @param WP_User|array $item The row: an account, or a record on a record view.
	 * @return string
	 */
	protected function column_cb( $item ) {
		if ( ! $this->row_tickable( $item ) ) {
			return '';
		}

		if ( is_array( $item ) ) {
			$value = ( isset( $item['id'] ) && is_scalar( $item['id'] ) ) ? (string) $item['id'] : '';

			if ( '' === $value ) {
				return '';
			}

			$field = self::RECORDS_FIELD;
			$id    = 'wpcpm-record-' . $value;
			$name  = ( isset( $item['name'] ) && is_scalar( $item['name'] ) && '' !== (string) $item['name'] ) ? (string) $item['name'] : $value;
		} else {
			$field = self::USERS_FIELD;
			$value = (string) (int) $item->ID;
			$id    = 'wpcpm-account-' . $value;
			$name  = $item->display_name;
		}

		return sprintf(
			'<input type="checkbox" name="%1$s[]" id="%2$s" value="%3$s" /><label for="%2$s"><span class="screen-reader-text">%4$s</span></label>',
			esc_attr( $field ),
			esc_attr( $id ),
			esc_attr( $value ),
			esc_html(
				sprintf(
					/* translators: %s: the account's display name, or the name of a record that has no account. */
					__( 'Select %s', 'wpcredits-program-manager' ),
					$name
				)
			)
		);
	}

	/**
	 * Whether a row may be ticked for a bulk action: every row, here.
	 *
	 * A table answers no for a row its bulk action could only refuse, and the row draws no checkbox
	 * (`column_cb()`), as core's own users list leaves the box out of a row the person looking may
	 * not act on, so the header's tick-all never ticks a refusal. Asked for an account row and for a
	 * record row alike. A box left out is no guard: the table's action still checks every row it is
	 * handed, since a row can stop being one it may act on after the page was drawn.
	 *
	 * @param WP_User|array $item The row: an account, or a record on a record view.
	 * @return bool
	 */
	protected function row_tickable( $item ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: every row may be ticked here, whatever the row.
		return true;
	}

	/**
	 * What the table prints above its views: nothing, here.
	 *
	 * Above the views is the first part of the list the screen draws, so what is printed there is
	 * read before the list and its search: a table says there what a view's bulk action does that the
	 * list's Apply has no dialog to say. Printed by the table's `views()`, escaped by the table.
	 */
	protected function list_intro() {}

	/**
	 * What the table prints under its table, after the rows and the pagination: nothing, here.
	 *
	 * A table says there what the list holds that the rows do not, such as where its rows come from.
	 * Printed by the table's `display()`, escaped by the table.
	 */
	protected function list_outro() {}

	/**
	 * The views, as core prints them, after what the table prints above them (`list_intro()`).
	 *
	 * Public, as core declares it.
	 */
	public function views() {
		$this->list_intro();

		parent::views();
	}

	/**
	 * The table, as core prints it, then what the table prints under it (`list_outro()`).
	 *
	 * Public, as core declares it.
	 */
	public function display() {
		parent::display();

		$this->list_outro();
	}

	/**
	 * The account's name, to the account's editor when the person looking may open it, in bold, as
	 * core's own lists set a row's title.
	 *
	 * Drawn for any audience whose columns hold `name`, the primary column of every audience's list
	 * so far. The value only: the row's actions come from `row_actions_for()`, drawn under it
	 * (`handle_row_actions()`). An audience whose name cell says more draws its own. Never reached by
	 * a record row: a record view names no `name` column (`record_columns()`).
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_name( $user ) {
		$edit = (string) get_edit_user_link( $user->ID );
		$name = esc_html( $user->display_name );

		if ( '' === $edit ) {
			return '<strong>' . $name . '</strong>';
		}

		return sprintf( '<strong><a href="%1$s">%2$s</a></strong>', esc_url( $edit ), $name );
	}

	/**
	 * The username, as code: drawn for any audience whose columns hold `login`. Never reached by a
	 * record row: a record view names no `login` column (`record_columns()`).
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_login( $user ) {
		return '<code>' . esc_html( $user->user_login ) . '</code>';
	}

	/**
	 * A cell no `column_<key>()` method draws: on a record row, the record's value for the column, as
	 * text, or '' where it holds none. An account row keeps core's default, which draws nothing: its
	 * cells are the base's and the audience's `column_<key>()` methods.
	 *
	 * @param WP_User|array $item        The row: an account, or a record on a record view.
	 * @param string        $column_name The cell's column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( ! is_array( $item ) ) {
			return (string) parent::column_default( $item, $column_name );
		}

		return ( isset( $item[ $column_name ] ) && is_scalar( $item[ $column_name ] ) ) ? esc_html( (string) $item[ $column_name ] ) : '';
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
	 * The sortable columns in WordPress's shape, the one the list starts sorted by marked as such;
	 * on a record view, the view's own (`record_sortable()`).
	 *
	 * Arrays rather than bare orderby values, because the table sets its column headers itself and
	 * core reads each entry as an array. The fifth entry is the order a list nobody sorted starts
	 * in, which lets core mark that column's header as sorted before anybody chooses one.
	 *
	 * @return array<string, array>
	 */
	protected function get_sortable_columns() {
		if ( '' !== $this->record_view() ) {
			return $this->record_sortable();
		}

		$sortable = array();

		foreach ( $this->sortable_map() as $column => $orderby ) {
			$sortable[ $column ] = self::DEFAULT_ORDERBY === $orderby
				? array( $orderby, false, '', '', 'asc' )
				: array( $orderby, false );
		}

		return $sortable;
	}

	/**
	 * The views: All, Invited and Never invited, with how many accounts each holds, then each record
	 * view the table has (`record_views()`), with how many records it holds (`count_records()`), its
	 * label translated by that count, singular or plural, as the invitation views' are.
	 *
	 * @return array<string, string> View => link.
	 */
	protected function get_views() {
		$views   = $this->invite_views( $this->invite_counts() );
		$current = $this->current_view();

		foreach ( $this->record_views() as $view => $label ) {
			$count = (int) $this->count_records( $view );

			$views[ $view ] = $this->view_link( $view, translate_nooped_plural( $label, $count, 'wpcredits-program-manager' ), $count, $current === $view );
		}

		return $views;
	}

	/**
	 * The three views' links, each with its count, the one in force marked as WordPress marks it
	 * (`view_link()`).
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
			$views[ $view ] = $this->view_link( $view, $label, $numbers[ $view ], $current === $view );
		}

		return $views;
	}

	/**
	 * One view's link, with its count, marked as WordPress marks the view in force.
	 *
	 * The one markup every audience's views are drawn in, the three invitation views
	 * (`invite_views()`), a record view (`get_views()`) and an audience's own, so a change to how a
	 * view is linked, marked or counted reaches every list. The link is the Accounts tab of the
	 * audience's screen, naming its view unless it is All. The words are escaped and the count's
	 * markup is the table's, so a translation cannot add markup.
	 *
	 * @param string $view    The view: `all`, or one the table has.
	 * @param string $label   The view's words, translated, with `%s` where the count goes.
	 * @param int    $count   How many accounts, or on a record view records, the view holds.
	 * @param bool   $current Whether it is the view in force.
	 * @return string
	 */
	protected function view_link( $view, $label, $count, $current ) {
		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $this->page_url( 'all' === $view ? array() : array( self::VIEW_ARG => $view ) ) ),
			$current ? ' class="current" aria-current="page"' : '',
			sprintf( esc_html( $label ), '<span class="count">(' . esc_html( number_format_i18n( (int) $count ) ) . ')</span>' )
		);
	}

	/**
	 * How many of the audience's accounts are invited and never invited, and so how many in all.
	 *
	 * Two counts through the audience's own query, so anything the audience narrows its list by
	 * narrows the counts the same way. Not the search: the views count the whole list, as
	 * WordPress's own views do. All is the sum, because an account holds a stamp or holds none.
	 *
	 * Read once a table and kept: each count is a query, and one draw asks for them more than once,
	 * the views and, on a record view, the heading (`heading_count()`). A table is built for one
	 * request, and a press that changes a stamp leaves for another page before anything is drawn.
	 *
	 * @return array{all: int, invited: int, never-invited: int}
	 */
	protected function invite_counts() {
		if ( null === $this->invite_counts ) {
			$invited = $this->count_view( 'invited' );
			$never   = $this->count_view( 'never-invited' );

			$this->invite_counts = array(
				'all'           => $invited + $never,
				'invited'       => $invited,
				'never-invited' => $never,
			);
		}

		return $this->invite_counts;
	}

	/**
	 * The view in force: `invited`, `never-invited`, a record view the table has (`record_views()`),
	 * or `all` for none or one the table does not have.
	 *
	 * @return string
	 */
	protected function current_view() {
		$view    = WPCPM_Request::key( self::VIEW_ARG );
		$records = $this->record_views();

		return ( in_array( $view, array( 'invited', 'never-invited' ), true ) || isset( $records[ $view ] ) ) ? $view : 'all';
	}

	/**
	 * The hidden field that keeps the view in force when the list's form is sent, so a search, a
	 * filter or a bulk action on the Never invited view comes back to it. Nothing for All.
	 *
	 * The audience's screen prints it in the list's form, beside the screen's page and tab.
	 */
	public function view_field() {
		$view = $this->current_view();

		if ( 'all' === $view ) {
			return;
		}

		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::VIEW_ARG ), esc_attr( $view ) );
	}

	/**
	 * The bulk actions: send an invitation to the ticked accounts, or send another; on a record view,
	 * the view's own (`record_bulk_actions()`), since a record has no account to invite.
	 *
	 * Public rather than core's protected, so code outside the table, a handler checking what was
	 * posted, can read them. Escaped, because core prints the labels as given.
	 *
	 * @return array<string, string> Action => label.
	 */
	public function get_bulk_actions() {
		$view = $this->record_view();

		if ( '' !== $view ) {
			return array_map( 'esc_html', $this->record_bulk_actions( $view ) );
		}

		return array(
			'invite'   => esc_html__( 'Send invite', 'wpcredits-program-manager' ),
			'reinvite' => esc_html__( 'Resend invite', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Whether a bulk action is the table's own to carry out (`handle_action()`): none is, here.
	 *
	 * The accounts screen every audience shares takes Send invite and Resend invite itself and asks
	 * the table about any other action pressed in its list (`WPCPM_Accounts_Screen::handle_list_form()`),
	 * so an audience that offers an action of its own, such as Create account on a record view,
	 * carries it out in its own table and the screen holds no branch for it. Asked before the list's
	 * nonce is checked, so it answers by the action's name alone and reads nothing else of the
	 * request. The base owns none, so an action pressed in a list without one does nothing.
	 *
	 * @param string $action The bulk action pressed.
	 * @return bool
	 */
	public function owns_action( $action ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base owns none, whichever action is named.
		return false;
	}

	/**
	 * Carry out a bulk action the table owns, and say what came of it: the outcome and its detail, in
	 * the shape `queue_ticked()` returns them for the invitations. Nothing here, since the base owns no
	 * action.
	 *
	 * The audience's screen flashes both and comes back to its list, then prints the outcome from the
	 * message map it prints on the tab the press returns to, so the outcome must be a key of that map:
	 * one the map does not hold prints nothing. A detail is worded by the table's own words for the
	 * outcome (`action_sentence()`), or where those are '' by the map's sentence, never by the
	 * invitations' (`WPCPM_Accounts_Screen::notice_sentence()`).
	 *
	 * Called only after the capability and the list's nonce are checked, for an action
	 * `owns_action()` answered yes to (`WPCPM_Accounts_Screen::handle_list_form()`), from whichever
	 * view the list was on: core reads the action a request names whether the view offers it or not.
	 * So it reads only the fields its own action owns, such as a record view's records under
	 * `RECORDS_FIELD` through `WPCPM_Request::list()`, and matches each against what the site holds.
	 *
	 * @param string $action The bulk action pressed.
	 * @return array{0: string, 1: array} The outcome and its detail.
	 */
	public function handle_action( $action ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base carries out none, whichever action is named.
		return array( '', array() );
	}

	/**
	 * The words for what a bulk action the table carries out itself did, from the outcome and the
	 * detail its `handle_action()` returned: '' here, so the sentence the screen's message map holds
	 * for the outcome stands.
	 *
	 * The audience's screen asks it of the audience's table class for the one outcome it is printing,
	 * and only when that outcome carried a detail (`WPCPM_Accounts_Screen::notice_sentence()`). The
	 * invitations' two outcomes are worded by the base for every audience (`selected_sentence()`) and
	 * are never asked of it. Static, as `selected_sentence()` is, so the screen asks it of the class
	 * with no table built.
	 *
	 * @param string $status The outcome `handle_action()` returned, a key of the screen's message map.
	 * @param array  $detail What `handle_action()` returned beside it, the outcome among it as `status`.
	 * @return string The sentence, as text, which the screen escapes; '' for the map's.
	 */
	public static function action_sentence( $status, array $detail ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base has no words, whichever outcome is named.
		return '';
	}

	/**
	 * The views of the records the audience keeps without an account, as view => its label as
	 * `_n_noop()` holds it, the singular and the plural each with one `%s` where the count goes:
	 * plurals through `_n()`, so the label is translated by the view's count once `count_records()`
	 * has answered (`get_views()`), as the invitation views' labels are picked by theirs
	 * (`invite_views()`). Each is drawn after the three invitation views.
	 *
	 * Each view is a key as `sanitize_key()` keeps it, lowercase letters, digits, hyphens and
	 * underscores, since the view in force is read through `WPCPM_Request::key()` (`current_view()`),
	 * and none is `all`, `invited` or `never-invited`. The map is asked for many times as a list is
	 * drawn, by every reading of the view in force, so it stays constant and cheap: the counting is
	 * `count_records()`'s.
	 *
	 * None here, so a list that keeps no records draws the three views alone, and an address naming
	 * a record view is All on it (`current_view()`).
	 *
	 * @return array<string, array> View => its label, as `_n_noop()` returns it.
	 */
	protected function record_views() {
		return array();
	}

	/**
	 * How many records a record view holds, for its link's count: the whole view, not the search, as
	 * WordPress's own views count. 0 here, where there is no record view.
	 *
	 * @param string $view A record view.
	 * @return int
	 */
	protected function count_records( $view ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base holds no record, whichever view is named.
		return 0;
	}

	/**
	 * Every row of a record view, searched and ordered as the list asks, each an array holding at
	 * least the record's `id` and its `name`, and a value for each column no `column_<key>()` method
	 * draws, which is printed as text (`column_default()`). The `id` is a non-empty scalar, unique
	 * within the view, matching the pattern the table's own action reads the ticked records back
	 * with: its checkbox posts it under `records[]` and carries it in its HTML id, and a row without
	 * one draws no checkbox (`column_cb()`). The `name` is the checkbox's label, the `id` standing in
	 * for it where there is none.
	 *
	 * The audience reads the search and the sort itself, since the base's own readers serve the
	 * account views, its search shaped for `WP_User_Query` and its sort bound to `sortable_map()`:
	 * the term as `WPCPM_Request::text( 's' )`, and `orderby` and `order` through
	 * `WPCPM_Request::key()`, matching `orderby` only against the orderbys `record_sortable()`
	 * declares. Every row, not a page: the base counts them for the pagination, takes the page's
	 * slice and drops anything that is not a row (`prepare_items()`). None here.
	 *
	 * @param string $view A record view.
	 * @return array[]
	 */
	protected function record_rows( $view ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base holds no record, whichever view is named.
		return array();
	}

	/**
	 * A record view's columns, in order, as column => label, in place of the audience's on that view
	 * (`columns()`): the base puts the checkbox before them and escapes the labels, and the first is
	 * the view's primary column, as the audience's first is on its views.
	 *
	 * None of them is `name` or `login`: the base draws those two cells from an account
	 * (`column_name()`, `column_login()`), and a record row is none. A column keyed as one of the
	 * audience's is drawn by the same `column_<key>()` method on both kinds of row, so such a method
	 * reads either. None here.
	 *
	 * @return array<string, string>
	 */
	protected function record_columns() {
		return array();
	}

	/**
	 * A record view's sortable columns, in WordPress's shape, as `get_sortable_columns()` answers for
	 * the account views: column => the orderby, then whether a first press on the column sorts Z to
	 * A. The audience orders the rows itself (`record_rows()`), reading `orderby` and `order` from the
	 * request and matching the orderby only against these, and none of it reaches a query. None here.
	 *
	 * @return array<string, array>
	 */
	protected function record_sortable() {
		return array();
	}

	/**
	 * A record row's own actions, as action => link, drawn under the record view's primary cell the
	 * way an account's are drawn under its name (`handle_row_actions()`): such as Create account on
	 * the row of a record ready for one. The links are the table's to escape, as an account's are
	 * (`row_actions_for()`). None here, so a record row draws the toggle alone.
	 *
	 * Read only for a record row, which is no account: an account's actions are `row_actions_for()`'s,
	 * and the invitations among them are an account's alone.
	 *
	 * @param array $row The record row, as `record_rows()` gave it.
	 * @return array<string, string> Action => link.
	 */
	protected function record_row_actions( array $row ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base offers none, whatever the row.
		return array();
	}

	/**
	 * A record view's bulk actions, as action => label, in place of the invitations on that view: each
	 * one the table carries out itself (`owns_action()`, `handle_action()`), such as Create account.
	 * The base escapes the labels, since core prints them as given. A record view with none still
	 * draws a checkbox on each row (`get_columns()`), as the account views do. None here.
	 *
	 * @param string $view A record view.
	 * @return array<string, string> Action => label.
	 */
	protected function record_bulk_actions( $view ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- A seam: the base offers none, whichever view is named.
		return array();
	}

	/**
	 * Queue invitations for the ticked accounts, and say what came of it: the outcome and its
	 * detail, which the audience's module carries back to its list, where `selected_sentence()`
	 * words it.
	 *
	 * Send invite queues the ticked accounts never invited through `WPCPM_Mail::queue_invites()`, as
	 * the invitations card's button does, which records the run the card reports on. Resend invite
	 * queues each ticked account invited before through `WPCPM_Mail::queue_invite()`, because
	 * `queue_invites()` drops anybody already invited, by design: the same queue and drain, and no
	 * run for the card to count. The drain passes over anybody sent an invitation in the last fifteen
	 * minutes, the guard against canceling the link in it (`WPCPM_Mail::may_invite()`), so Resend
	 * invite leaves them out and counts them, and the outcome says so rather than calling them
	 * queued. Every ID is checked against the audience's role first, an account outside it left out
	 * and a repeat counted once, and invited means any of the stamps is there (`holds_stamp()`), the
	 * reading its views and row actions use.
	 *
	 * The outcome is `invites-queued`, with how many were queued as `queued`, or `invites-none`, with
	 * why as `why`: `none-selected` when nothing was ticked, or nothing that is an account's ID,
	 * `never-invited` when a Resend invite found nobody invited before, `too-soon` when everybody it
	 * found invited before was sent an invitation inside the fifteen minutes, `queued-already` when
	 * everybody left to queue is waiting already, and `invited-already` when a Send invite found
	 * nobody left to invite for the first time. Every detail carries `resend`, and a Resend invite
	 * that left anybody out for the fifteen minutes carries how many as `recent`, unless that is why
	 * none was queued.
	 *
	 * Static, called on the audience's own table (`WPCPM_Students_Table::queue_ticked()`,
	 * `WPCPM_Mentors_Table::queue_ticked()`), which gives the role and the stamps read here. It checks
	 * neither the capability nor a nonce: the audience's handler checks both before it reads the
	 * ticked IDs, as the accounts screen's `handle_list_form()` does (`WPCPM_Accounts_Screen`).
	 *
	 * @param int[] $ids    The ticked accounts.
	 * @param bool  $resend Resend invite, rather than Send invite.
	 * @return array{0: string, 1: array} The outcome and its detail.
	 */
	public static function queue_ticked( array $ids, $resend ) {
		$detail  = array( 'resend' => (bool) $resend );
		$role    = static::role();
		$invited = array();
		$never   = array();

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

		// Once the IDs are read rather than before: what is no account's ID is no account ticked.
		if ( empty( $ids ) ) {
			return array( 'invites-none', $detail + array( 'why' => 'none-selected' ) );
		}

		// Every ticked account and its meta in two queries, not two queries an account: a page can
		// hold 999, and nothing has read them yet, since a press is handled before the list reads a page.
		cache_users( $ids );

		foreach ( $ids as $id ) {
			$user = get_user_by( 'id', $id );

			if ( ! $user instanceof WP_User || ! in_array( $role, (array) $user->roles, true ) ) {
				continue;
			}

			if ( self::holds_stamp( $user->ID ) ) {
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
			return array( 'invites-none', $detail + array( 'why' => $why ) );
		}

		return array( 'invites-queued', $detail + array( 'queued' => $queued ) );
	}

	/**
	 * What a press on the ticked accounts says it did, and how many a Resend invite left out for the
	 * fifteen minutes when it left any out.
	 *
	 * One wording for every audience's list, beside the arithmetic whose outcome it words
	 * (`queue_ticked()`): the audience's module prints it for the press the list's form made, from
	 * the detail the press carried back.
	 *
	 * @param array $detail `resend`, `queued` or `why`, and `recent`, as `queue_ticked()` returns them.
	 * @return string
	 */
	public static function selected_sentence( array $detail ) {
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
	 * A row's invitation: Send invite for an account never invited, Resend invite for one that was.
	 *
	 * A nonce link to the audience's invite handler, with the account as `user`, the tab to come back
	 * to and where the list stands (`list_state()`), under the nonce that handler checks: its own
	 * action keyed to the account, the action, then `_` and the account's ID, so a link taken from one
	 * row is no use on another. The audience adds its own actions, such as "View page", beside it.
	 *
	 * The link is a GET request, so the handler has to read the account from the query string as
	 * `user`, as the accounts screen's `handle_invite()` does (`WPCPM_Accounts_Screen`) after the
	 * posted `user_id` a form sends (`WPCPM_Request::posted_id( 'user_id' )`, then
	 * `WPCPM_Request::id( 'user' )`): a handler that reads the posted field alone finds no account
	 * behind the link and sends nothing.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string> `invite` or `reinvite` => the link.
	 */
	protected function invite_row_actions( WP_User $user ) {
		$action  = static::invite_action();
		$invited = self::holds_stamp( $user->ID );
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
			$action . '_' . (int) $user->ID
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
	 * already, so drawing the actions here, in place of the default, leaves one toggle a row. A row
	 * with no action, such as an administrator's for a person who may not open another account's
	 * editor, gets core's default, the toggle alone: `row_actions()` draws nothing for none, the
	 * toggle with it, and on a narrow screen the toggle is what opens a row's other cells. A record
	 * row, on a record view, gets the record's own actions (`record_row_actions()`) in the same
	 * markup, or the same default when it has none: it is no account, so an account's actions,
	 * the invitations among them, are never drawn for it.
	 *
	 * @param WP_User|array $item        The row: an account, or a record on a record view.
	 * @param string        $column_name The cell's column.
	 * @param string        $primary     The primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}

		$actions = is_array( $item ) ? $this->record_row_actions( $item ) : $this->row_actions_for( $item );

		return empty( $actions ) ? parent::handle_row_actions( $item, $column_name, $primary ) : $this->row_actions( $actions );
	}

	/**
	 * A row's actions, as action => link: the invitation, which an audience extends with its own,
	 * such as "View page", and with the account's editor, the base's Edit (`edit_row_action()`).
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		return $this->invite_row_actions( $user );
	}

	/**
	 * A row's Edit, as action => link: the account's editor, when the person looking may open it, and
	 * nothing when not.
	 *
	 * Every audience's accounts open in the same editor, so the link is drawn here once. An audience
	 * puts it first among its row's actions, where core's own Users list puts it. The base's own
	 * actions (`row_actions_for()`) stay the invitation alone, because an audience's own actions go
	 * between the two, as View page does, and only the audience can put the three in that order.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string> `edit` => the link, or nothing.
	 */
	protected function edit_row_action( WP_User $user ) {
		$edit = (string) get_edit_user_link( $user->ID );

		if ( '' === $edit ) {
			return array();
		}

		return array( 'edit' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit ), esc_html__( 'Edit', 'wpcredits-program-manager' ) ) );
	}

	/**
	 * The user meta the audience's own invitation is stamped in, or '' for a role the plugin never
	 * invites.
	 *
	 * The key the mail layer's one map holds for this role (`WPCPM_Mail::STAMPS`), read from it rather
	 * than spelled again here, and so the key `WPCPM_Mail::stamp_invited()` writes for the role: a
	 * row's invitation on the audience's list always writes it, the account holding the role, as the
	 * audience's own record of an invitation. Whether an account was sent one is read from every
	 * stamp, not this one alone (`stamps_read()`), since an account has one password.
	 *
	 * **No stamp for any other role**, WordPress's Administrator role among them: nothing invites those
	 * accounts, so the base reads no stamp for them, and an administrator invited as a student does
	 * not count as an invited administrator. Every reader reads '' as nobody invited, by WordPress's
	 * own rule: it writes no meta under an empty key (`add_metadata()` refuses one), so
	 * `metadata_exists()` finds it on no account, and a meta query's `EXISTS` on it finds nobody and its
	 * `NOT EXISTS` everybody. The views, a row's invitation, the bulk actions and
	 * `WPCPM_Mail::never_invited()` read it so.
	 *
	 * Public, so the plumbing every audience's screen shares hands it to `WPCPM_Mail::never_invited()`
	 * from the table, which tells the mail layer whether the plugin invites the role, rather than
	 * holding a copy of its own.
	 *
	 * @return string
	 */
	public static function invite_meta() {
		$role = static::role();

		return isset( WPCPM_Mail::STAMPS[ $role ] ) ? WPCPM_Mail::STAMPS[ $role ] : '';
	}

	/**
	 * The stamps whose presence makes an account of the audience one sent an invitation: every stamp
	 * an invitation leaves (`WPCPM_Mail::STAMPS`), since an account has one password and one sent an
	 * invitation under any of its roles was sent one, as the queue and the fifteen-minute guard read
	 * it. For a role the plugin never invites, its empty key alone (`invite_meta()`), which WordPress
	 * finds on no account.
	 *
	 * @return string[]
	 */
	private static function stamps_read() {
		return '' === static::invite_meta() ? array( '' ) : array_values( WPCPM_Mail::STAMPS );
	}

	/**
	 * Whether an account holds any of the stamps the views read (`stamps_read()`), whatever it holds:
	 * a stamp of 0 is still a stamp.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function holds_stamp( $user_id ) {
		foreach ( self::stamps_read() as $key ) {
			if ( metadata_exists( 'user', (int) $user_id, $key ) ) {
				return true;
			}
		}

		return false;
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
	 * What a query is sorted by: its `orderby`, or the first key of `WP_User_Query`'s array form, in
	 * which `prepare_items()` asks for the name with the ID after it to settle the ties.
	 *
	 * For an audience that sorts a column itself, which WordPress cannot sort by: its `query()` reads
	 * here what the list is sorted by, whichever form the base asked in.
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return string
	 */
	protected static function sorted_by( array $args ) {
		if ( ! isset( $args['orderby'] ) ) {
			return '';
		}

		if ( is_array( $args['orderby'] ) ) {
			$first = array_key_first( $args['orderby'] );

			return null === $first ? '' : (string) $first;
		}

		return (string) $args['orderby'];
	}

	/**
	 * Two names in A to Z order, for an audience that orders its accounts itself: accents taken away
	 * first (`remove_accents()`), so Álvaro sorts among the A's and Łukasz among the L's rather than
	 * after Z, where a comparison byte by byte puts every name that opens on an accented letter; then
	 * `strcasecmp()`, which folds the capitals of the Latin alphabet and no other, and reads a number
	 * digit by digit, so Student 10 comes before Student 2, as the database's own name sort puts them.
	 * A capital in another script is not folded: a Cyrillic name with a capital comes before every
	 * name of that script that opens on a small letter.
	 *
	 * This is not the order of `WPCPM_Dashboards::compare_names()`, which the Viewing as
	 * switchers and the reconciliation's names share and which folds the capitals of every script and
	 * reads numbers as numbers. The accounts lists keep this comparison, the one their sorts have
	 * always made. Two names alike in it, such as Ana and ana, compare as one, and the audience's own
	 * tie-break, the ID, settles them as it settles two of one name.
	 *
	 * @param string $a One name.
	 * @param string $b The other.
	 * @return int Below 0 when the first comes first, above 0 when the second does, 0 when alike.
	 */
	protected static function compare_names( $a, $b ) {
		return strcasecmp( remove_accents( (string) $a ), remove_accents( (string) $b ) );
	}

	/**
	 * Read one page of the view in force, searched and sorted, and set what the table draws: a page
	 * of the audience's accounts through its query (`page_args()`), or on a record view a page of its
	 * records (`records_page()`), for which no account is read.
	 */
	public function prepare_items() {
		$per_page = $this->per_page();
		$view     = $this->record_view();

		if ( '' === $view ) {
			$found = $this->query( $this->page_args( $per_page ) );
		} else {
			$found = $this->records_page( $view, $per_page );
		}

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
	 * The `WP_User_Query` arguments for one page of an account view: the role, the view's meta
	 * query, the search, the order and the page.
	 *
	 * The search covers the name, the username and the email; an audience searching its own columns
	 * too does it in its query.
	 *
	 * @param int $per_page Rows a page.
	 * @return array
	 */
	private function page_args( $per_page ) {
		$orderby = $this->orderby();
		$order   = $this->order();

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

		return $args;
	}

	/**
	 * One page of a record view, in the shape the audience's query answers for accounts (`query()`):
	 * the page's slice of every record the audience holds for the view (`record_rows()`), which
	 * searched and ordered them, and how many there are in all. A record is no account, so no
	 * account is read for it.
	 *
	 * @param string $view     The record view.
	 * @param int    $per_page Rows a page.
	 * @return array{items: array[], total: int}
	 */
	private function records_page( $view, $per_page ) {
		// Rows alone: anything else handed back in place of the list, such as an error from a failed
		// read, is no rows, and anything in the list that is not a row would be drawn as an account.
		$rows = $this->record_rows( $view );
		$rows = is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();

		return array(
			'items' => array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per_page, $per_page ),
			'total' => count( $rows ),
		);
	}

	/**
	 * One view's count, through the audience's query: one ID fetched, the total counted.
	 *
	 * Every draw of the views counts both, so the count is only as cheap as the view's meta query
	 * (`view_clause()`): WordPress still computes every matching row to report the total, which is why
	 * the Invited view asks its stamps in one clause rather than a clause a stamp.
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
	 * A view's meta query: any of the stamps the views read (`stamps_read()`) there for Invited, none
	 * of them for Never invited, nothing for All.
	 *
	 * **The two views ask in different shapes, for the joins WordPress makes of them.** Invited is one
	 * clause whose key is the list of stamps, which WordPress reads as `meta_key IN (...)`: one join of
	 * the account's meta rows. As a clause a stamp under OR, each `EXISTS` would take a join of its own
	 * with no key in it, since WordPress shares a join between OR clauses only for comparisons of a
	 * value, and the database would walk every account's meta rows to the fourth power. The OR stays
	 * on the one clause: it is what has WordPress select each account once, `DISTINCT`, when it holds
	 * two stamps. Never invited is a clause a stamp under AND: each `NOT EXISTS` is a LEFT JOIN with
	 * its key in its own join, at most one row an account, and WordPress binds one key there, so a
	 * list cannot be asked.
	 *
	 * Protected, so an audience whose list has a view of its own answers it here (the Mentors list's
	 * No students), and hands every other view on.
	 *
	 * @param string $view `all`, `invited` or `never-invited`.
	 * @return array
	 */
	protected function view_clause( $view ) {
		if ( 'invited' === $view ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => self::stamps_read(),
					'compare' => 'EXISTS',
				),
			);
		}

		if ( 'never-invited' !== $view ) {
			return array();
		}

		$query = array( 'relation' => 'AND' );

		foreach ( self::stamps_read() as $key ) {
			$query[] = array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			);
		}

		return $query;
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
	 * The primary column: the first of the view's columns (`view_columns()`), the audience's first on
	 * its views and a record view's own first on that view, so a record row has its header cell and
	 * its toggle too, and Screen Options is never offered to hide either.
	 *
	 * @return string
	 */
	private function primary_column() {
		$columns = array_keys( $this->view_columns() );

		return isset( $columns[0] ) ? (string) $columns[0] : '';
	}

	/**
	 * The columns of the view in force, before the checkbox is put in front of them: the audience's
	 * (`columns()`), or on a record view the view's own (`record_columns()`).
	 *
	 * @return array<string, string>
	 */
	private function view_columns() {
		return '' === $this->record_view() ? $this->columns() : $this->record_columns();
	}

	/**
	 * The number the list's card prints beside its heading, whose words name the audience's accounts:
	 * on a view of accounts, how many the list holds, every page of it, as WordPress counted them or,
	 * for a sort the table orders itself, as its own read of every account did; on a record view,
	 * whose rows are records the view's link counts, the accounts the list holds in all, All's count.
	 *
	 * Public, for the accounts screen every audience shares, which prints it
	 * (`WPCPM_Accounts_Screen::render_accounts_list()`).
	 *
	 * @return int
	 */
	public function heading_count() {
		if ( '' !== $this->record_view() ) {
			$counts = $this->invite_counts();

			return (int) $counts['all'];
		}

		return (int) $this->get_pagination_arg( 'total_items' );
	}

	/**
	 * Whether a record view is in force, whose rows are records with no account: for the screen
	 * around the list, which leaves out there what it says of accounts alone, such as how they are
	 * made and invited.
	 *
	 * @return bool
	 */
	public function is_record_view() {
		return '' !== $this->record_view();
	}

	/**
	 * The record view in force, or '' on a view of accounts.
	 *
	 * Protected, so an audience can draw what goes around its list by the view, such as the sentence
	 * in its empty row (`no_items()`) or a note above the list on a record view.
	 *
	 * @return string
	 */
	protected function record_view() {
		$view    = $this->current_view();
		$records = $this->record_views();

		return isset( $records[ $view ] ) ? $view : '';
	}
}
