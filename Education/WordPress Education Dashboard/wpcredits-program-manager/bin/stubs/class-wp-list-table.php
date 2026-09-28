<?php
/**
 * WordPress's list table, for the suites, which run without WordPress.
 *
 * `WP_List_Table` is core's, in wp-admin/includes/class-wp-list-table.php: the class the lists in
 * wp-admin are drawn with, the Users and Posts screens among them, and the one the plugin's account
 * lists extend (WPCPM_Accounts_Table). A suite has no WordPress to lend it that class, so this
 * models its public surface as the plugin's tables use it: the constructor's arguments; `items` and
 * `_column_headers`; `get_columns()` and `get_sortable_columns()`; `get_views()` and `views()`;
 * `get_bulk_actions()` and `bulk_actions()`; `search_box()`; `set_pagination_args()`,
 * `get_pagination_arg()` and `get_pagenum()`; `get_items_per_page()`; `prepare_items()`;
 * `display()`; `row_actions()` and `handle_row_actions()`; `current_action()`; and `has_items()`.
 * `display()` draws the parts core's does: the table navigation above and below (the bulk form's
 * nonce and its actions, `extra_tablenav()`, the pagination), the column headers with their sort
 * links, and a row per item through `single_row()`, where the checkbox cell is `column_cb()` and
 * every other cell is a `column_<name>()` method or `column_default()` followed by
 * `handle_row_actions()`, whose default, as core's, gives the primary cell the "Show more details"
 * toggle; or `no_items()` in WordPress's empty row when there is nothing to list.
 *
 * The screen is the suite's to give. Core's constructor binds the table to the current screen through
 * `convert_to_screen()`, which lives in wp-admin; this calls a suite's `convert_to_screen()` when the
 * suite has one, and has no screen otherwise. With a screen, the constructor hands the screen the
 * table's columns as core's does, by adding `get_columns()` to the `manage_{screen id}_columns`
 * filter at priority 0, the filter core's Screen Options reads the columns it offers to hide from;
 * and the hidden columns are what `get_hidden_columns()` reads for it: core's reading of the columns
 * a person unticked under Screen Options, the user option `manage<screen id>columnshidden`, declared
 * below for a suite that has no `get_hidden_columns()` of its own. Without one, nothing is hidden.
 *
 * The markup is close enough to core's for a suite to pin what a screen depends on: the columns and
 * their order, the rows, the views and which is current, the row actions and their one toggle, the
 * bulk form's selects, Apply buttons and nonce, and the pagination's numbers and links. It is not
 * core's markup, and a check must not read it as that: core's markup is core's, it changes between
 * releases (this follows 7.1), and a site shows whatever the installed WordPress draws. What core
 * alone does, and this does not: every filter keyed to the screen but the one the constructor adds
 * (`views_{screen}`, `bulk_actions-{screen}` and the sortable columns' filter), and reading
 * `manage_{screen}_columns` back, which is core's Screen Options' part; the filters on the hidden
 * columns (`default_hidden_columns`, `hidden_columns`); the redirect `set_pagination_args()`
 * makes for a page number past the last page; the list mode; AJAX; the headings for screen readers;
 * the hidden "Sort ascending." and "Sort descending." text in an unsorted column's link; a cell
 * drawn by a `_column_<name>()` method; the `aria-label` 7.1 gives the primary cell; bulk actions in
 * option groups; the page number as an input in the top pagination; the query arguments the
 * pagination links drop; and the translation of its own strings, which are printed in English.
 * Where core ends in `die()` because a subclass had to override a method, this throws.
 *
 * It calls the WordPress functions a suite that draws a table stands in for already: `esc_attr()`,
 * `esc_url()`, `sanitize_key()`, `wp_unslash()`, `number_format_i18n()`, `wp_nonce_field()`,
 * `get_user_option()` and `apply_filters()`, and `add_filter()` for a table the suite gives a screen.
 * It reads the request where core does: `$_REQUEST` for the page number, the search, the sort and
 * the action, `$_GET` for the sort the headers mark, and `$_SERVER['REQUEST_URI']` for the address
 * the sort and page links are built on.
 *
 * Loaded with `require_once __DIR__ . '/stubs/class-wp-list-table.php';` from a suite's header,
 * before the plugin's table classes, which then find the class defined and leave core's file alone.
 */

class WP_List_Table {

	/**
	 * The rows, as prepare_items() left them.
	 *
	 * @var array
	 */
	public $items = array();

	/**
	 * The constructor's arguments: plural, singular, ajax and screen.
	 *
	 * @var array
	 */
	protected $_args = array();

	/**
	 * What set_pagination_args() was given, with the page count worked out.
	 *
	 * @var array
	 */
	protected $_pagination_args = array();

	/**
	 * The columns, the hidden ones, the sortable ones and the primary one, when a table sets them.
	 *
	 * @var array|null
	 */
	protected $_column_headers;

	/**
	 * The screen the table is drawn on: the suite's `convert_to_screen()`'s answer, or null.
	 *
	 * @var object|null
	 */
	protected $screen;

	/**
	 * The bulk actions, read on the first bulk_actions() of a display, the one above the table.
	 *
	 * @var array|null
	 */
	private $_actions;

	/**
	 * Keep the arguments, as core does, and the screen when the suite gives one, handing that screen
	 * the table's columns as core's constructor does. An empty plural stays empty: core would take the
	 * screen's base.
	 *
	 * @param array $args plural, singular, ajax, screen.
	 */
	public function __construct( $args = array() ) {
		$args = array_merge(
			array(
				'plural'   => '',
				'singular' => '',
				'ajax'     => false,
				'screen'   => null,
			),
			is_array( $args ) ? $args : array()
		);

		$args['plural']   = sanitize_key( $args['plural'] );
		$args['singular'] = sanitize_key( $args['singular'] );

		$this->screen = function_exists( 'convert_to_screen' ) ? convert_to_screen( $args['screen'] ) : null;
		$this->_args  = $args;

		if ( isset( $this->screen->id ) ) {
			add_filter( "manage_{$this->screen->id}_columns", array( $this, 'get_columns' ), 0 );
		}
	}

	/**
	 * Core dies here: a table has to prepare its own items.
	 *
	 * @throws LogicException Always.
	 */
	public function prepare_items() {
		throw new LogicException( 'function WP_List_Table::prepare_items() must be overridden in a subclass.' );
	}

	/**
	 * The pagination's numbers, the page count worked out from the total and the page size.
	 *
	 * @param array $args total_items, total_pages, per_page.
	 */
	protected function set_pagination_args( $args ) {
		$args = array_merge(
			array(
				'total_items' => 0,
				'total_pages' => 0,
				'per_page'    => 0,
			),
			(array) $args
		);

		if ( ! $args['total_pages'] && $args['per_page'] > 0 ) {
			$args['total_pages'] = (int) ceil( $args['total_items'] / $args['per_page'] );
		}

		$this->_pagination_args = $args;
	}

	/**
	 * One of the pagination's numbers, as core reads them: the page asked for as `page`, 0 for one never set.
	 *
	 * @param string $key `page`, `total_items`, `total_pages` or `per_page`.
	 * @return int
	 */
	public function get_pagination_arg( $key ) {
		if ( 'page' === $key ) {
			return $this->get_pagenum();
		}

		return isset( $this->_pagination_args[ $key ] ) ? $this->_pagination_args[ $key ] : 0;
	}

	/**
	 * Whether there is anything to list.
	 *
	 * @return bool
	 */
	public function has_items() {
		return ! empty( $this->items );
	}

	/**
	 * Core's sentence for an empty list.
	 */
	public function no_items() {
		echo 'No items found.';
	}

	/**
	 * The search box: the term searched for, and the sort kept, unless there is neither a search nor a row.
	 *
	 * @param string $text     The button's label.
	 * @param string $input_id The input's ID, before core's `-search-input`.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}

		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . $text . ':</label>';
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . esc_attr( isset( $_REQUEST['s'] ) ? wp_unslash( $_REQUEST['s'] ) : '' ) . '" />';
		echo '<input type="submit" id="search-submit" class="button button-compact" value="' . esc_attr( $text ) . '"  />';
		echo '</p>';
	}

	/**
	 * No views unless a table has some.
	 *
	 * @return array<string, string> View => link.
	 */
	protected function get_views() {
		return array();
	}

	/**
	 * The views, as core prints them: a list, the links as the table gave them, one per item.
	 */
	public function views() {
		$views = $this->get_views();

		if ( empty( $views ) ) {
			return;
		}

		echo "<ul class='subsubsub'>\n";

		foreach ( $views as $class => $view ) {
			$views[ $class ] = "\t<li class='$class'>$view";
		}

		echo implode( " |</li>\n", $views ) . "</li>\n";
		echo '</ul>';
	}

	/**
	 * No bulk actions unless a table has some.
	 *
	 * @return array<string, string> Action => label.
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * The bulk actions' select and its Apply button: `action` above the table, `action2` below it.
	 *
	 * The labels are printed as the table gave them, as core prints them.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( null === $this->_actions ) {
			$this->_actions = $this->get_bulk_actions();
			$two            = '';
		} else {
			$two = '2';
		}

		if ( empty( $this->_actions ) ) {
			return;
		}

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
		echo '<option value="-1">Bulk actions</option>' . "\n";

		foreach ( $this->_actions as $key => $label ) {
			echo "\t" . '<option value="' . esc_attr( $key ) . '">' . $label . "</option>\n";
		}

		echo "</select>\n";
		echo '<input type="submit" name="bulk_action" id="doaction' . $two . '" class="button action button-compact" value="Apply"  />' . "\n";
	}

	/**
	 * The bulk action picked, or false for none: "Bulk actions" is -1, and a filter press is not an action.
	 *
	 * @return string|false
	 */
	public function current_action() {
		if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) ) {
			return false;
		}

		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return $_REQUEST['action'];
		}

		return false;
	}

	/**
	 * A row's actions, as core draws them: each in a span named for it, a bar between them.
	 *
	 * @param string[] $actions        Action => link.
	 * @param bool     $always_visible Whether they show without a hover.
	 * @return string
	 */
	protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );

		if ( ! $action_count ) {
			return '';
		}

		$output = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';
		$i      = 0;

		foreach ( $actions as $action => $link ) {
			++$i;
			$separator = ( $i < $action_count ) ? ' | ' : '';
			$output   .= "<span class='$action'>{$link}{$separator}</span>";
		}

		$output .= '</div>';
		$output .= '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>';

		return $output;
	}

	/**
	 * The page asked for, at least 1, and no further than the last page once the count is known.
	 *
	 * @return int
	 */
	public function get_pagenum() {
		$pagenum = isset( $_REQUEST['paged'] ) ? abs( (int) $_REQUEST['paged'] ) : 0;

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}

	/**
	 * Rows a page: the person's choice in the user option, the default for anything not a positive
	 * number, and the option's own filter, which core's Screen Options box applies to what it shows.
	 *
	 * @param string $option        The user option.
	 * @param int    $default_value Rows a page with no choice.
	 * @return int
	 */
	protected function get_items_per_page( $option, $default_value = 20 ) {
		$per_page = (int) get_user_option( $option );

		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = $default_value;
		}

		return (int) apply_filters( "{$option}", $per_page );
	}

	/**
	 * The pagination: how many items, then the first, previous, next and last page links.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args['total_items'] ) ) {
			echo '<div class="tablenav-pages no-pages"><span class="displaying-num">0 items</span></div>';
			return;
		}

		$total_items = (int) $this->_pagination_args['total_items'];
		$total_pages = (int) $this->_pagination_args['total_pages'];
		$current     = $this->get_pagenum();
		$url         = self::request_url();
		$output      = '<span class="displaying-num">' . sprintf( 1 === $total_items ? '%s item' : '%s items', number_format_i18n( $total_items ) ) . '</span>';
		$links       = array();

		if ( 1 === $current ) {
			$links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
			$links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		} else {
			$links[] = "<a class='first-page button' href='" . esc_url( self::with_args( $url, array( 'paged' => false ) ) ) . "'><span class='screen-reader-text'>First page</span><span aria-hidden='true'>&laquo;</span></a>";
			$links[] = "<a class='prev-page button' href='" . esc_url( self::with_args( $url, array( 'paged' => max( 1, $current - 1 ) ) ) ) . "'><span class='screen-reader-text'>Previous page</span><span aria-hidden='true'>&lsaquo;</span></a>";
		}

		$links[] = '<span class="paging-input">' . $current . " of <span class='total-pages'>" . number_format_i18n( $total_pages ) . '</span></span>';

		if ( $total_pages === $current ) {
			$links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
			$links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		} else {
			$links[] = "<a class='next-page button' href='" . esc_url( self::with_args( $url, array( 'paged' => min( $total_pages, $current + 1 ) ) ) ) . "'><span class='screen-reader-text'>Next page</span><span aria-hidden='true'>&rsaquo;</span></a>";
			$links[] = "<a class='last-page button' href='" . esc_url( self::with_args( $url, array( 'paged' => $total_pages ) ) ) . "'><span class='screen-reader-text'>Last page</span><span aria-hidden='true'>&raquo;</span></a>";
		}

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}

		echo "<div class='tablenav-pages{$page_class}'>" . $output . "\n<span class='pagination-links'>" . implode( "\n", $links ) . '</span></div>';
	}

	/**
	 * Core dies here: a table has to name its columns.
	 *
	 * @throws LogicException Always.
	 */
	public function get_columns() {
		throw new LogicException( 'function WP_List_Table::get_columns() must be overridden in a subclass.' );
	}

	/**
	 * No sortable columns unless a table has some.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
	}

	/**
	 * The columns, hidden, sortable and primary: as the table set them, or worked out as core does.
	 *
	 * @return array
	 */
	protected function get_column_info() {
		if ( isset( $this->_column_headers ) && is_array( $this->_column_headers ) ) {
			if ( 4 === count( $this->_column_headers ) ) {
				return $this->_column_headers;
			}

			$headers = array( array(), array(), array(), $this->default_primary() );

			foreach ( $this->_column_headers as $key => $value ) {
				$headers[ $key ] = $value;
			}

			$this->_column_headers = $headers;

			return $this->_column_headers;
		}

		$sortable = array();

		foreach ( $this->get_sortable_columns() as $id => $data ) {
			if ( empty( $data ) ) {
				continue;
			}

			$data = (array) $data;

			foreach ( array( 1 => false, 2 => '', 3 => false, 4 => false ) as $i => $default ) {
				if ( ! isset( $data[ $i ] ) ) {
					$data[ $i ] = $default;
				}
			}

			$sortable[ $id ] = $data;
		}

		$hidden = isset( $this->screen ) ? get_hidden_columns( $this->screen ) : array();

		$this->_column_headers = array( $this->get_columns(), $hidden, $sortable, $this->default_primary() );

		return $this->_column_headers;
	}

	/**
	 * How many columns show, for the empty row's span.
	 *
	 * @return int
	 */
	public function get_column_count() {
		list( $columns, $hidden ) = $this->get_column_info();

		return count( $columns ) - count( array_intersect( array_keys( $columns ), array_filter( $hidden ) ) );
	}

	/**
	 * The column headers: the select-all checkbox, then each column, sortable ones as sort links.
	 *
	 * The column the list is sorted by is marked `sorted` with its order and its link reverses it;
	 * before anybody sorts, that is the column whose fifth entry names the order the list starts in.
	 *
	 * @param bool $with_id Whether each header carries its column's ID (the footer's do not).
	 */
	public function print_column_headers( $with_id = true ) {
		static $cb_counter = 1;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$url             = self::with_args( self::request_url(), array( 'paged' => false ) );
		$current_orderby = isset( $_GET['orderby'] ) ? (string) $_GET['orderby'] : '';
		$current_order   = ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) ? 'desc' : 'asc';

		if ( ! empty( $columns['cb'] ) ) {
			$columns['cb'] = '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" /><label for="cb-select-all-' . $cb_counter . '"><span class="screen-reader-text">Select All</span></label>';
			++$cb_counter;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );
			$aria  = '';
			$abbr  = '';

			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				$orderby    = isset( $sortable[ $column_key ][0] ) ? $sortable[ $column_key ][0] : '';
				$desc_first = isset( $sortable[ $column_key ][1] ) ? $sortable[ $column_key ][1] : false;
				$abbr       = isset( $sortable[ $column_key ][2] ) ? $sortable[ $column_key ][2] : '';
				$initial    = isset( $sortable[ $column_key ][4] ) ? $sortable[ $column_key ][4] : '';

				if ( '' === $current_orderby && $initial ) {
					$current_orderby = $orderby;
					$current_order   = $initial;
				}

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$aria    = ' aria-sort="' . ( 'asc' === $current_order ? 'ascending' : 'descending' ) . '"';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					// A string second entry names the first order ("asc" or "desc"), anything else says
					// whether it is descending, as core reads it.
					$order = is_string( $desc_first ) ? strtolower( $desc_first ) : '';

					if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
						$order = $desc_first ? 'desc' : 'asc';
					}

					$class[] = 'sortable';
					$class[] = 'desc' === $order ? 'asc' : 'desc';
				}

				$column_display_name = '<a href="' . esc_url( self::with_args( $url, array( 'orderby' => $orderby, 'order' => $order ) ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>';
			}

			$tag       = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope     = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id        = $with_id ? "id='$column_key'" : '';
			$abbr_attr = $abbr ? ' abbr="' . esc_attr( $abbr ) . '"' : '';

			echo "<$tag $scope $id class='" . implode( ' ', $class ) . "' $aria $abbr_attr>$column_display_name</$tag>";
		}
	}

	/**
	 * The table: its navigation above, the headers, the rows, the headers again, its navigation below.
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		echo '<table class="wp-list-table ' . implode( ' ', $this->get_table_classes() ) . '">';
		echo '<thead><tr>';
		$this->print_column_headers();
		echo '</tr></thead>';
		echo '<tbody id="the-list"' . ( $singular ? " data-wp-lists='list:$singular'" : '' ) . '>';
		$this->display_rows_or_placeholder();
		echo '</tbody>';
		echo '<tfoot><tr>';
		$this->print_column_headers( false );
		echo '</tr></tfoot>';
		echo '</table>';

		$this->display_tablenav( 'bottom' );
	}

	/**
	 * The table's classes: core's, the list mode's, and the plural the table was made with.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'table-view-list', $this->_args['plural'] );
	}

	/**
	 * The navigation around the table: above it the bulk form's nonce; on both sides the bulk
	 * actions, the table's extras and the pagination. None below a table with nothing in it.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function display_tablenav( $which ) {
		if ( 'bottom' === $which && ! $this->has_items() ) {
			return;
		}

		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}

		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		echo '<div class="alignleft actions bulkactions' . ( $this->has_items() ? '' : ' hidden' ) . '">';
		$this->bulk_actions( $which );
		echo '</div>';
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear" /></div>';
	}

	/**
	 * Nothing between the bulk actions and the pagination unless a table adds it.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function extra_tablenav( $which ) {}

	/**
	 * The rows, or WordPress's empty row across every column holding no_items().
	 */
	public function display_rows_or_placeholder() {
		if ( $this->has_items() ) {
			$this->display_rows();
			return;
		}

		echo '<tr class="no-items"><td class="colspanchange" colspan="' . (int) $this->get_column_count() . '">';
		$this->no_items();
		echo '</td></tr>';
	}

	/**
	 * A row per item.
	 */
	public function display_rows() {
		foreach ( $this->items as $item ) {
			$this->single_row( $item );
		}
	}

	/**
	 * One item's row.
	 *
	 * @param object|array $item The item.
	 */
	public function single_row( $item ) {
		echo '<tr>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * A cell no method of its own draws: empty unless a table says otherwise.
	 *
	 * @param object|array $item        The item.
	 * @param string       $column_name The column.
	 */
	protected function column_default( $item, $column_name ) {}

	/**
	 * The checkbox cell: empty unless a table says otherwise.
	 *
	 * @param object|array $item The item.
	 */
	protected function column_cb( $item ) {}

	/**
	 * One row's cells: the checkbox in a `check-column` cell, the primary column as the row's header.
	 *
	 * @param object|array $item The item.
	 */
	protected function single_row_columns( $item ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";

			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden, true ) ) {
				$classes .= ' hidden';
			}

			if ( 'cb' === $column_name ) {
				echo '<td class="check-column">';
				echo $this->column_cb( $item );
				echo '</td>';
				continue;
			}

			$is_primary = ( $primary === $column_name );
			$tag        = $is_primary ? 'th' : 'td';

			echo "<$tag class='$classes' data-colname=\"" . esc_attr( strip_tags( $column_display_name ) ) . '"' . ( $is_primary ? ' scope="row"' : '' ) . '>';

			if ( method_exists( $this, 'column_' . $column_name ) ) {
				echo call_user_func( array( $this, 'column_' . $column_name ), $item );
			} else {
				echo $this->column_default( $item, $column_name );
			}

			echo $this->handle_row_actions( $item, $column_name, $primary );
			echo "</$tag>";
		}
	}

	/**
	 * What core adds after every cell but the checkbox: by default, the primary cell's "Show more
	 * details" toggle, which opens a row's other cells on a narrow screen, and nothing elsewhere.
	 *
	 * @param object|array $item        The item.
	 * @param string       $column_name The cell's column.
	 * @param string       $primary     The primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>' : '';
	}

	/**
	 * The address this request was made to, which the sort and page links are built on.
	 *
	 * @return string
	 */
	private static function request_url() {
		return isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	}

	/**
	 * An address with query arguments set, or removed where the value is false.
	 *
	 * Its own, rather than the suite's add_query_arg(): a suite's stand-in for that may not remove.
	 *
	 * @param string $url  The address.
	 * @param array  $args Argument => value, or false to remove it.
	 * @return string
	 */
	private static function with_args( $url, array $args ) {
		$parts = explode( '?', (string) $url, 2 );
		$query = array();

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		foreach ( $args as $key => $value ) {
			if ( false === $value ) {
				unset( $query[ $key ] );
			} else {
				$query[ $key ] = $value;
			}
		}

		return $parts[0] . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
	}

	/**
	 * The first column that is not the checkbox, which core falls back to as the primary one.
	 *
	 * @return string
	 */
	private function default_primary() {
		foreach ( array_keys( $this->get_columns() ) as $column ) {
			if ( 'cb' !== $column ) {
				return (string) $column;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'get_hidden_columns' ) ) {
	/**
	 * Core's reading of the columns a person hid under Screen Options: the user option
	 * `manage<screen id>columnshidden` when it holds a list, and none otherwise. Without core's
	 * two filters on the list.
	 *
	 * @param object $screen The screen, with the `id` core keys the option by.
	 * @return string[]
	 */
	function get_hidden_columns( $screen ) {
		$hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );

		return is_array( $hidden ) ? $hidden : array();
	}
}
