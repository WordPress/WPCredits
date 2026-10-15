<?php
/**
 * The Mentors screen's Engagement tab: one row per mentor.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Every mentor's sign-ins, students, schedule, notes and calls, read once by
 * `WPCPM_Mentor_Engagement::report()` and sorted, searched, filtered and paged here in PHP, as the
 * Accounts tab orders its Students column: about a hundred rows.
 *
 * Read-only: no checkbox, no bulk action, no form but the GET form of the view, the search and the
 * page. **Its column keys and its rows-per-page option are its own**, because the two tabs are one
 * screen to WordPress, which keeps one hidden-columns list per screen: a key shared with the
 * Accounts list (`students`, `past`, `status`) would hide in both.
 */
class WPCPM_Mentor_Engagement_Table extends WP_List_Table {
	/** Rows a page until the person chooses. */
	const PER_PAGE_DEFAULT = 20;

	/** The query argument the views use. */
	const VIEW_ARG = 'engagement_view';

	/** The list's anchor, which the form and the views return to. */
	const ANCHOR = 'wpcpm-mentor-engagement-list';

	/**
	 * What `WPCPM_Mentor_Engagement::report()` returned.
	 *
	 * @var array
	 */
	private $report;

	/**
	 * The Mentor Report Card's address, read once.
	 *
	 * @var string
	 */
	private $mentor_page;

	/**
	 * The table, over one report.
	 *
	 * @param array $report `rows` and `summary`.
	 */
	public function __construct( array $report ) {
		parent::__construct(
			array(
				'singular' => 'mentor',
				'plural'   => 'mentor-engagement',
				'ajax'     => false,
			)
		);

		$this->report      = $report;
		$this->mentor_page = WPCPM_Mentors_Dashboard::page_url();

		// Screen Options offers every column but the Mentor column, as the Accounts list does.
		if ( isset( $this->screen->id ) ) {
			$filter = 'manage_' . $this->screen->id . '_columns';

			remove_filter( $filter, array( $this, 'get_columns' ), 0 );
			add_filter( $filter, array( $this, 'columns_offered' ), 0 );
		}
	}

	/**
	 * The headline figures the tab prints above the list.
	 *
	 * @return array<string, int>
	 */
	public function summary() {
		return (array) $this->report['summary'];
	}

	/**
	 * The columns, their labels escaped: core prints them as given.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'mentor'       => esc_html__( 'Mentor', 'wpcredits-program-manager' ),
			'standing'     => esc_html__( 'Status', 'wpcredits-program-manager' ),
			'students_now' => esc_html__( 'Students', 'wpcredits-program-manager' ),
			'last_visit'   => esc_html__( 'Last visit', 'wpcredits-program-manager' ),
			'last_sign_in' => esc_html__( 'Last sign-in', 'wpcredits-program-manager' ),
			'schedule'     => esc_html__( 'Schedule', 'wpcredits-program-manager' ),
			'notes'        => esc_html__( 'Notes', 'wpcredits-program-manager' ),
			'calls'        => esc_html__( 'Calls', 'wpcredits-program-manager' ),
			'sessions'     => esc_html__( 'Group sessions', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * What Screen Options may hide: every column but Mentor.
	 *
	 * @return array<string, string>
	 */
	public function columns_offered() {
		return array_diff_key( $this->get_columns(), array( 'mentor' => true ) );
	}

	/**
	 * The sortable columns; a number column opens most first. The list opens on students now, most
	 * first, so that column's fifth entry says so and its header shows the arrow before anybody sorts.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'mentor'       => array( 'mentor', false ),
			'students_now' => array( 'students_now', true, '', '', 'desc' ),
			'last_visit'   => array( 'last_visit', true ),
			'last_sign_in' => array( 'last_sign_in', true ),
			'notes'        => array( 'notes', true ),
			'calls'        => array( 'calls', true ),
		);
	}

	/**
	 * The column the row actions sit under.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'mentor';
	}

	/**
	 * The view asked for, or All.
	 *
	 * @return string
	 */
	public function current_view() {
		$view = WPCPM_Request::key( self::VIEW_ARG );

		return array_key_exists( $view, WPCPM_Mentor_Engagement::views() ) ? $view : WPCPM_Mentor_Engagement::VIEW_ALL;
	}

	/**
	 * The page of rows: the view, the search, the order, then the page.
	 */
	public function prepare_items() {
		$view   = $this->current_view();
		$search = WPCPM_Request::text( 's' );
		$rows   = array();

		foreach ( (array) $this->report['rows'] as $row ) {
			if ( WPCPM_Mentor_Engagement::in_view( $row, $view ) && WPCPM_Mentor_Engagement::matches( $row, $search ) ) {
				$rows[] = $row;
			}
		}

		$rows     = WPCPM_Mentor_Engagement::sorted( $rows, WPCPM_Request::key( 'orderby' ), WPCPM_Request::key( 'order' ) );
		$per_page = $this->get_items_per_page( WPCPM_Mentors::ENGAGEMENT_PER_PAGE_OPTION, self::PER_PAGE_DEFAULT );

		$this->set_pagination_args(
			array(
				'total_items' => count( $rows ),
				'per_page'    => $per_page,
			)
		);

		$this->items = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per_page, $per_page );

		// The columns a person unticked under Screen Options stay hidden, as on core's own lists. The
		// Mentor column never is: it holds the row's actions, Screen Options is not offered it
		// (`columns_offered()`), and one hidden all the same is drawn. As the Accounts list reads them,
		// and only on a screen, which a table drawn without one has not.
		$hidden = isset( $this->screen )
			? array_values( array_diff( get_hidden_columns( $this->screen ), array( 'mentor' ) ) )
			: array();

		$this->_column_headers = array( $this->get_columns(), $hidden, $this->get_sortable_columns(), 'mentor' );
	}

	/**
	 * The views, each with its count over every row.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->current_view();
		$base    = admin_url( 'admin.php?page=wpcpm-mentors&tab=engagement' );
		$links   = array();

		foreach ( WPCPM_Mentor_Engagement::views() as $view => $label ) {
			$count = 0;

			foreach ( (array) $this->report['rows'] as $row ) {
				$count += WPCPM_Mentor_Engagement::in_view( $row, $view ) ? 1 : 0;
			}

			$links[ $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( WPCPM_Mentor_Engagement::VIEW_ALL === $view ? $base . '#' . self::ANCHOR : add_query_arg( self::VIEW_ARG, $view, $base ) . '#' . self::ANCHOR ),
				$view === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $links;
	}

	/**
	 * What an empty list says.
	 */
	public function no_items() {
		echo esc_html__( 'No mentors match.', 'wpcredits-program-manager' );
	}

	/**
	 * One row, carrying the mentor's ID for the suite to read (there is no checkbox to carry it).
	 *
	 * @param array $item A row.
	 */
	public function single_row( $item ) {
		printf( '<tr id="mentor-%d">', (int) $item['id'] );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * The name. Its actions are drawn under it by `handle_row_actions()`.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_mentor( $item ) {
		return '<strong>' . esc_html( $item['name'] ) . '</strong>';
	}

	/**
	 * The row's actions, drawn under its primary cell the way core's own lists draw them, as the
	 * Accounts list does.
	 *
	 * Core's `single_row_columns()` adds this after every cell, and its default gives the primary
	 * cell the "Show more details" toggle. `row_actions()` ends in that toggle already, so the
	 * actions are drawn here in place of the default and never inside `column_mentor()`, which would
	 * leave two toggles a row.
	 *
	 * @param array  $item        A row.
	 * @param string $column_name The cell's column.
	 * @param string $primary     The primary column.
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}

		$actions = array();
		$edit    = (string) get_edit_user_link( (int) $item['id'] );

		if ( '' !== $edit ) {
			$actions['edit'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit ), esc_html__( 'Edit', 'wpcredits-program-manager' ) );
		}

		if ( '' !== $this->mentor_page ) {
			$actions['view'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( 'wpcpm_mentor', (int) $item['id'], $this->mentor_page ) ), esc_html__( 'View page', 'wpcredits-program-manager' ) );
		}

		return empty( $actions ) ? parent::handle_row_actions( $item, $column_name, $primary ) : $this->row_actions( $actions );
	}

	/**
	 * Active or not in Airtable, and Administrator.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_standing( $item ) {
		$text = $item['active'] ? esc_html__( 'Active', 'wpcredits-program-manager' ) : esc_html__( 'Not active', 'wpcredits-program-manager' );

		return $item['staff'] ? $text . '<br />' . esc_html__( 'Administrator', 'wpcredits-program-manager' ) : $text;
	}

	/**
	 * Students now, and before.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_students_now( $item ) {
		/* translators: %s: a number of past students. */
		return esc_html( number_format_i18n( $item['students_now'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( '%s before', 'wpcredits-program-manager' ), number_format_i18n( $item['students_past'] ) ) ) . '</span>';
	}

	/**
	 * The last visit, and how long ago.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_last_visit( $item ) {
		if ( $item['last_visit'] <= 0 ) {
			return esc_html__( 'Not recorded yet', 'wpcredits-program-manager' );
		}

		/* translators: %s: a time span, such as "3 days". */
		return esc_html( $this->day( $item['last_visit'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( '%s ago', 'wpcredits-program-manager' ), human_time_diff( $item['last_visit'], time() ) ) ) . '</span>';
	}

	/**
	 * The last sign-in.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_last_sign_in( $item ) {
		return $item['last_sign_in'] > 0 ? esc_html( $this->day( $item['last_sign_in'] ) ) : esc_html__( 'Not recorded yet', 'wpcredits-program-manager' );
	}

	/**
	 * The schedule in words.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_schedule( $item ) {
		return esc_html( WPCPM_Mentor_Engagement::schedule_label( $item ) );
	}

	/**
	 * Notes written, and the last one's day.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_notes( $item ) {
		if ( $item['notes'] <= 0 ) {
			return esc_html( number_format_i18n( 0 ) );
		}

		/* translators: %s: a date. */
		return esc_html( number_format_i18n( $item['notes'] ) ) . '<br /><span class="description">' . esc_html( sprintf( __( 'last on %s', 'wpcredits-program-manager' ), $this->day( $item['last_note'] ) ) ) . '</span>';
	}

	/**
	 * One-to-one calls: held, upcoming, and how many held have a note.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_calls( $item ) {
		/* translators: 1: calls held, 2: calls to come. */
		$html = esc_html( sprintf( __( '%1$s held, %2$s upcoming', 'wpcredits-program-manager' ), number_format_i18n( $item['calls_held'] ), number_format_i18n( $item['calls_upcoming'] ) ) );

		if ( $item['calls_held'] > 0 ) {
			/* translators: 1: calls with a note, 2: calls held. */
			$html .= '<br /><span class="description">' . esc_html( sprintf( __( '%1$s of %2$s noted', 'wpcredits-program-manager' ), number_format_i18n( $item['calls_noted'] ), number_format_i18n( $item['calls_held'] ) ) ) . '</span>';
		}

		return $html;
	}

	/**
	 * Group sessions: held and upcoming.
	 *
	 * @param array $item A row.
	 * @return string
	 */
	protected function column_sessions( $item ) {
		/* translators: 1: sessions held, 2: sessions to come. */
		return esc_html( sprintf( __( '%1$s held, %2$s upcoming', 'wpcredits-program-manager' ), number_format_i18n( $item['sessions_held'] ), number_format_i18n( $item['sessions_upcoming'] ) ) );
	}

	/**
	 * A day in the site's format and time zone.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	private function day( $time ) {
		return (string) wp_date( (string) get_option( 'date_format' ), (int) $time );
	}
}
