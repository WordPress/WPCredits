<?php
/**
 * The Mentors screen's list of Mentor accounts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every Mentor account as a WordPress list table: the name, the username, how many current and
 * past students the mentor has and whether Airtable still holds them as Active, searched, sorted
 * and paged.
 *
 * **Students and Status sort by what the mentors sync writes on each account**, and a query cannot
 * sort by it without losing accounts: the sync deletes the count of a mentor left with no student,
 * and an account made by hand holds neither the count nor the flag, and WordPress sorts by a meta
 * value only among the accounts that hold the key. So for those two the table reads every account
 * the list may hold and orders them itself (`by_value()`), each read as its cell shows it.
 *
 * **The No students view is the Students column's 0.** A mentor is in it when the count the column
 * shows is 0, whether the sync wrote a 0 (a mentor with past students only) or deleted the count
 * or never wrote it, so a meta clause could not say it without a join that treats a missing key as
 * a value; the table reads the accounts and keeps the ones the column shows 0 for
 * (`has_no_students()`), as it orders the sorted ones.
 */
class WPCPM_Mentors_Table extends WPCPM_Accounts_Table {

	/** The view of the mentors the Students column shows 0 for, beside the invitation views. */
	const VIEW_NO_STUDENTS = 'no-students';

	/**
	 * What the No students view's clause holds: a marker `query()` takes off before WordPress is
	 * asked, since the view cannot be said as a meta clause (see the class). Only this table's
	 * `view_clause()` writes it and only its `query()` reads it.
	 */
	const NO_STUDENTS_MARK = 'wpcpm_no_students';

	/** What the Students column sorts by: the count of current students, which the table orders itself (`by_value()`). */
	const ORDERBY_STUDENTS = 'students';

	/** What the Status column sorts by: whether Airtable holds the mentor as Active, ordered by the table as the count is. */
	const ORDERBY_STATUS = 'status';

	/**
	 * The Mentor Report Card's address, read once: '' while the page is missing.
	 *
	 * @var string|null
	 */
	private $mentor_page;

	/**
	 * Where the list stands, read once for every row's invitation.
	 *
	 * @var array<string, string>|null
	 */
	private $list_state;

	/**
	 * How many Mentor accounts the Students column shows 0 for, read once for the view's link.
	 *
	 * @var int|null
	 */
	private $without_students;

	/**
	 * The audience: the Mentors module's ID.
	 *
	 * @return string
	 */
	protected static function audience() {
		return 'mentors';
	}

	/**
	 * The role the accounts hold, which the Mentors module's invitations read here too.
	 *
	 * @return string
	 */
	public static function role() {
		return WPCPM_Roles::ROLE_MENTOR;
	}

	/**
	 * The columns: the mentor's name first, the primary column, which Screen Options never offers to
	 * hide, so the row's actions under it cannot be hidden with it.
	 *
	 * @return array<string, string>
	 */
	protected function columns() {
		return array(
			'name'     => __( 'Mentor', 'wpcredits-program-manager' ),
			'login'    => __( 'Username', 'wpcredits-program-manager' ),
			'students' => __( 'Students', 'wpcredits-program-manager' ),
			'past'     => __( 'Past', 'wpcredits-program-manager' ),
			'status'   => __( 'Status', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The name sorts by display name and the username by login, as WordPress sorts them; Students
	 * and Status by the order the table puts the accounts in (`by_value()`).
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array(
			'name'     => 'display_name',
			'login'    => 'login',
			'students' => self::ORDERBY_STUDENTS,
			'status'   => self::ORDERBY_STATUS,
		);
	}

	/**
	 * The accounts a set of `WP_User_Query` arguments finds, sorted by Students or Status when the
	 * list is.
	 *
	 * The role, the view and the search are the base's, and WordPress answers them as they come:
	 * the search covers the name, the username and the email, all this list searches.
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	protected function query( array $args ) {
		$only_without = isset( $args['meta_query'][ self::NO_STUDENTS_MARK ] );

		if ( $only_without ) {
			unset( $args['meta_query'][ self::NO_STUDENTS_MARK ] );
		}

		$sorted_by = self::sorted_by( $args );

		if ( self::ORDERBY_STUDENTS === $sorted_by || self::ORDERBY_STATUS === $sorted_by ) {
			return $this->by_value( $args, $sorted_by, $only_without );
		}

		if ( $only_without ) {
			return $this->without_students( $args );
		}

		$query = new WP_User_Query( $args );

		return array(
			'items' => (array) $query->get_results(),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * One page of the accounts sorted by Students or Status: every account the page's other arguments
	 * find, read whole and ordered here, and the page's slice of them.
	 *
	 * Each account sorts where its cell says (`value_of()`), so every account is listed, in the order
	 * `compare_accounts()` puts them in, which leaves no two accounts tied, so none shows on two pages
	 * while another is on none. The order they are read in does not settle a tie: PHP's sort before
	 * 8.0 may put two equal items either way round.
	 *
	 * The read is the one the list made for every Mentor account before it was paged, and WordPress
	 * primes each account's meta with it, so the values cost no query each.
	 *
	 * @param array  $args         The page's `WP_User_Query` arguments.
	 * @param string $sorted_by    `ORDERBY_STUDENTS` or `ORDERBY_STATUS`.
	 * @param bool   $only_without Whether the page is the No students view's, so the accounts the
	 *                             column shows a student for are left out.
	 * @return array{items: WP_User[], total: int}
	 */
	private function by_value( array $args, $sorted_by, $only_without = false ) {
		$number = isset( $args['number'] ) ? (int) $args['number'] : 0;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$desc   = isset( $args['order'] ) && 'DESC' === $args['order'];

		$every = new WP_User_Query(
			array_merge(
				$args,
				array(
					'number'      => -1,
					'offset'      => 0,
					'orderby'     => 'ID',
					'order'       => 'ASC',
					'count_total' => false,
				)
			)
		);

		$accounts = array();

		foreach ( (array) $every->get_results() as $user ) {
			if ( $only_without && ! self::has_no_students( $user->ID ) ) {
				continue;
			}

			$accounts[] = array(
				'user'  => $user,
				'value' => self::value_of( $user->ID, $sorted_by ),
			);
		}

		usort(
			$accounts,
			static function ( $a, $b ) use ( $desc ) {
				return self::compare_accounts( $a, $b, $desc );
			}
		);

		return array(
			'items' => array_column( array_slice( $accounts, $offset, $number > 0 ? $number : null ), 'user' ),
			'total' => count( $accounts ),
		);
	}

	/**
	 * One page of the No students view: every account the page's other arguments find, in the order
	 * WordPress puts them in, kept when the Students column shows 0 for it, and the page's slice.
	 *
	 * @param array $args The page's `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	private function without_students( array $args ) {
		$number = isset( $args['number'] ) ? (int) $args['number'] : 0;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		$every = new WP_User_Query(
			array_merge(
				$args,
				array(
					'number'      => -1,
					'offset'      => 0,
					'count_total' => false,
				)
			)
		);

		$found = array();

		foreach ( (array) $every->get_results() as $user ) {
			if ( self::has_no_students( $user->ID ) ) {
				$found[] = $user;
			}
		}

		return array(
			'items' => array_slice( $found, $offset, $number > 0 ? $number : null ),
			'total' => count( $found ),
		);
	}

	/**
	 * Whether the Students column shows 0 for a mentor: the same count, read the same way.
	 *
	 * @param int $user_id The mentor's account.
	 * @return bool
	 */
	private static function has_no_students( $user_id ) {
		return 0 === WPCPM_Mentors_Dashboard::get_mentee_count( (int) $user_id );
	}

	/**
	 * How many Mentor accounts are in the No students view: the whole list, not the search, as the
	 * other views count. Read once a table.
	 *
	 * @return int
	 */
	private function count_without_students() {
		if ( null === $this->without_students ) {
			$every = new WP_User_Query(
				array(
					'role'        => static::role(),
					'number'      => -1,
					'fields'      => 'ID',
					'count_total' => false,
				)
			);

			$ids = array_map( 'intval', (array) $every->get_results() );

			// A query of IDs does not load the accounts' meta, and the count of each is a read of it:
			// loaded in one go, the whole list costs one query and not one a mentor.
			if ( ! empty( $ids ) && function_exists( 'update_meta_cache' ) ) {
				update_meta_cache( 'user', $ids );
			}

			$this->without_students = count(
				array_filter(
					$ids,
					static function ( $user_id ) {
						return self::has_no_students( $user_id );
					}
				)
			);
		}

		return $this->without_students;
	}

	/**
	 * The views: the base's, then No students with how many mentors it holds.
	 *
	 * @return array<string, string> View => link.
	 */
	protected function get_views() {
		$views = parent::get_views();
		$count = $this->count_without_students();

		$views[ self::VIEW_NO_STUDENTS ] = $this->view_link(
			self::VIEW_NO_STUDENTS,
			/* translators: %s: how many accounts, in parentheses. */
			_n( 'No students %s', 'No students %s', $count, 'wpcredits-program-manager' ),
			$count,
			self::VIEW_NO_STUDENTS === $this->current_view()
		);

		return $views;
	}

	/**
	 * The view in force: No students when the address names it, else the base's reading.
	 *
	 * @return string
	 */
	protected function current_view() {
		return self::VIEW_NO_STUDENTS === WPCPM_Request::key( self::VIEW_ARG ) ? self::VIEW_NO_STUDENTS : parent::current_view();
	}

	/**
	 * A view's meta query: the marker for No students, which `query()` reads, else the base's.
	 *
	 * @param string $view The view.
	 * @return array
	 */
	protected function view_clause( $view ) {
		if ( self::VIEW_NO_STUDENTS === $view ) {
			return array( self::NO_STUDENTS_MARK => true );
		}

		return parent::view_clause( $view );
	}

	/**
	 * Two accounts in the order a sort by a value puts them: by the value, whichever way the list runs;
	 * the accounts of one value by name, A to Z whichever way the values run, as a reader expects
	 * names (`compare_names()`); and the accounts of one name by ID. No two accounts are ever tied, so
	 * the order is one on every PHP, 7.4's unstable sort included.
	 *
	 * @param array{user: WP_User, value: int} $a    One account and its value.
	 * @param array{user: WP_User, value: int} $b    The other.
	 * @param bool                             $desc Whether the values run from the highest.
	 * @return int Below 0 when the first comes first, above 0 when the second does.
	 */
	private static function compare_accounts( array $a, array $b, $desc ) {
		$order = $a['value'] <=> $b['value'];

		if ( 0 !== $order ) {
			return $desc ? -$order : $order;
		}

		$order = self::compare_names( $a['user']->display_name, $b['user']->display_name );

		return 0 !== $order ? $order : (int) $a['user']->ID - (int) $b['user']->ID;
	}

	/**
	 * What an account sorts by, read as its cell shows it: the count of current students, 0 where
	 * the sync deleted it or never wrote it; or for Status, 0 while the mentor is Active and 1
	 * otherwise, a flag never written included, so Status runs from Active to Not in Airtable from
	 * A to Z, as the two words run.
	 *
	 * @param int    $user_id   The account.
	 * @param string $sorted_by `ORDERBY_STUDENTS` or `ORDERBY_STATUS`.
	 * @return int
	 */
	private static function value_of( $user_id, $sorted_by ) {
		if ( self::ORDERBY_STATUS === $sorted_by ) {
			return self::is_active( $user_id ) ? 0 : 1;
		}

		return WPCPM_Mentors_Dashboard::get_mentee_count( $user_id );
	}

	/**
	 * Whether Airtable holds a mentor as Active, as the mentors sync last read it: the flag it writes,
	 * which a mentor it no longer finds holds as 0 and an account made by hand does not hold at all.
	 *
	 * @param int $user_id The mentor's account.
	 * @return bool
	 */
	private static function is_active( $user_id ) {
		return (bool) (int) get_user_meta( (int) $user_id, WPCPM_Mentors_Sync::META_ACTIVE, true );
	}

	/**
	 * The sentence in the empty row: how accounts arrive while there are none, by the sync on the
	 * screen's other tab, and that the list found nobody when a search or a view narrowed it.
	 */
	public function no_items() {
		if ( '' === $this->search_clause() && 'all' === $this->current_view() ) {
			esc_html_e( 'No mentor accounts yet. Run a sync on the Sync tab to create them.', 'wpcredits-program-manager' );

			return;
		}

		if ( '' === $this->search_clause() && self::VIEW_NO_STUDENTS === $this->current_view() ) {
			esc_html_e( 'Every mentor account has a current student.', 'wpcredits-program-manager' );

			return;
		}

		esc_html_e( 'No mentor accounts found.', 'wpcredits-program-manager' );
	}

	/**
	 * How many current students the mentor has, from the count the mentors sync writes: 0 where it
	 * wrote none.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_students( $user ) {
		return esc_html( number_format_i18n( WPCPM_Mentors_Dashboard::get_mentee_count( $user->ID ) ) );
	}

	/**
	 * How many past students the mentor has, from the same sync.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_past( $user ) {
		return esc_html( number_format_i18n( (int) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_PAST_COUNT, true ) ) );
	}

	/**
	 * Active while Airtable holds the mentor as Active, and Not in Airtable otherwise.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_status( $user ) {
		return self::is_active( $user->ID )
			? esc_html__( 'Active', 'wpcredits-program-manager' )
			: esc_html__( 'Not in Airtable', 'wpcredits-program-manager' );
	}

	/**
	 * A row's actions: the base's Edit, the account's editor, when the person looking may open it;
	 * View page, the Mentor Report Card as that mentor, while the page exists; then the base's
	 * invitation.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		$actions = $this->edit_row_action( $user );
		$page    = $this->mentor_page();

		if ( '' !== $page ) {
			$actions['view'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( add_query_arg( 'wpcpm_mentor', (int) $user->ID, $page ) ),
				esc_html__( 'View page', 'wpcredits-program-manager' )
			);
		}

		return array_merge( $actions, parent::row_actions_for( $user ) );
	}

	/**
	 * Where the list stands, which a row's invitation carries, so the invitation comes back to the
	 * same view, search, sort and page: the Mentors screen's reading of it, the accounts screen's own,
	 * which a press on the ticked accounts comes back through (`WPCPM_Mentors::list_state()`).
	 *
	 * @return array<string, string>
	 */
	protected function list_state() {
		if ( null === $this->list_state ) {
			$this->list_state = WPCPM_Mentors::list_state();
		}

		return $this->list_state;
	}

	/**
	 * The Mentor Report Card's address, or '' while the page is missing.
	 *
	 * @return string
	 */
	private function mentor_page() {
		if ( null === $this->mentor_page ) {
			$this->mentor_page = (string) WPCPM_Mentors_Dashboard::page_url();
		}

		return $this->mentor_page;
	}
}
