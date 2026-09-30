<?php
/**
 * The Students screen's list of Student accounts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every Student account as a WordPress list table: the name, the username, the program, the
 * institution and the mentor, searched, narrowed to one institution, sorted and paged.
 *
 * **The institution has no meta key of its own.** It is a field of the program row the students
 * sync writes (`WPCPM_Students_Sync::META_PROGRAM`), a serialized array, so no meta query can match
 * it, no search can reach it and no `orderby` can sort by it. `wpcpm_student_institution` holds an
 * Airtable record ID from another table, not the name the list shows. So the table reads every
 * Student account's row once, the read the institution picker needs for its counts anyway, and
 * hands `WP_User_Query` lists of IDs instead: the accounts at the chosen institution, the accounts
 * a search finds, and for the Institution sort, one page of the accounts in the order it put them in.
 */
class WPCPM_Students_Table extends WPCPM_Accounts_Table {

	/** The query argument naming the institution the list is narrowed to, which the invitations card reads too. */
	const INSTITUTION_ARG = 'wpcpm_institution';

	/** What the Institution column sorts by. No query can, so the table orders the IDs itself. */
	const ORDERBY_INSTITUTION = 'institution';

	/**
	 * Every Student account's name and institution, read once: user ID => name and institution.
	 *
	 * @var array<int, array{name: string, institution: string}>|null
	 */
	private $roster;

	/**
	 * The student page's address, read once: '' while the page is missing.
	 *
	 * @var string|null
	 */
	private $student_page;

	/**
	 * Where the list stands, read once for every row's invitation.
	 *
	 * @var array<string, string>|null
	 */
	private $list_state;

	/**
	 * The audience: the Students module's ID.
	 *
	 * @return string
	 */
	protected static function audience() {
		return 'students';
	}

	/**
	 * The role the accounts hold, which the Students module's invitations read here too.
	 *
	 * @return string
	 */
	public static function role() {
		return WPCPM_Roles::ROLE_STUDENT;
	}

	/**
	 * The columns: the student's name first, the primary column, which Screen Options never offers to
	 * hide, so the row's actions under it cannot be hidden with it.
	 *
	 * @return array<string, string>
	 */
	protected function columns() {
		return array(
			'name'        => __( 'Student', 'wpcredits-program-manager' ),
			'login'       => __( 'Username', 'wpcredits-program-manager' ),
			'program'     => __( 'Program', 'wpcredits-program-manager' ),
			'institution' => __( 'Institution', 'wpcredits-program-manager' ),
			'mentor'      => __( 'Mentor', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The name sorts by display name, the username by login, and the institution by the order the
	 * table puts the IDs in (`by_institution()`).
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array(
			'name'        => 'display_name',
			'login'       => 'login',
			'institution' => self::ORDERBY_INSTITUTION,
		);
	}

	/**
	 * The accounts a set of `WP_User_Query` arguments finds, narrowed to the institution chosen and to
	 * what the search finds, and sorted by institution when the list is.
	 *
	 * The search goes into the IDs rather than to WordPress, whose search covers only the account's
	 * own columns, and the page's query then holds no search of its own.
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	protected function query( array $args ) {
		$scope = $this->scope( $args );

		unset( $args['search'], $args['search_columns'] );

		if ( null !== $scope ) {
			// An empty list is asked for as the one ID nobody holds: WordPress reads an empty
			// `include` as no narrowing at all, which would list every account.
			$args['include'] = empty( $scope ) ? array( 0 ) : $scope;
		}

		if ( self::ORDERBY_INSTITUTION === self::sorted_by( $args ) ) {
			return $this->by_institution( $args );
		}

		$query = new WP_User_Query( $args );

		return array(
			'items' => (array) $query->get_results(),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * The IDs the list may hold, or null when nothing narrows it: the accounts at the institution
	 * chosen, and the accounts a search finds.
	 *
	 * @param array $args The query's arguments, the search among them.
	 * @return int[]|null
	 */
	private function scope( array $args ) {
		$scope       = null;
		$institution = WPCPM_Students::institution_filter();

		if ( '' !== $institution ) {
			$scope = $this->ids_at( $institution );
		}

		if ( ! empty( $args['search'] ) ) {
			$found = $this->search_ids( $args );
			$scope = null === $scope ? $found : array_values( array_intersect( $scope, $found ) );
		}

		return $scope;
	}

	/**
	 * The accounts at one institution: the name the row holds, trimmed, equal to the one asked for.
	 *
	 * The reading `WPCPM_Mail::only_institution()` narrows the invitations card by, so the list and
	 * the card agree on who is at a school, and a school whose name holds another's is not the other.
	 *
	 * @param string $institution The institution's name.
	 * @return int[]
	 */
	private function ids_at( $institution ) {
		$institution = trim( (string) $institution );
		$ids         = array();

		foreach ( $this->roster() as $id => $row ) {
			if ( $row['institution'] === $institution ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The accounts a search finds: the ones WordPress finds by the username, the email or the name,
	 * then the ones whose institution holds the term, without regard to case.
	 *
	 * @param array $args The query's arguments: the search and its columns.
	 * @return int[]
	 */
	private function search_ids( array $args ) {
		$query = new WP_User_Query(
			array(
				'role'           => static::role(),
				'search'         => $args['search'],
				'search_columns' => isset( $args['search_columns'] ) ? $args['search_columns'] : array(),
				'fields'         => 'ID',
				'number'         => -1,
				'count_total'    => false,
			)
		);

		// Each ID through `id_of()`, because the program's own site answers a request for IDs with rows.
		$ids  = array_map( array( 'WPCPM_Roles', 'id_of' ), (array) $query->get_results() );
		$term = trim( (string) $args['search'], '*' );

		foreach ( $this->roster() as $id => $row ) {
			if ( '' !== $row['institution'] && self::contains( $row['institution'], $term ) ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * One page of the accounts sorted by institution, then name: every ID the page's other arguments
	 * find, ordered here, and the page's slice of them fetched from WordPress in that order.
	 *
	 * Each school's students run A to Z by name whichever way the schools run, and an account at no
	 * school comes after every school from A to Z, and before them from Z to A. Schools and names are
	 * read as a reader of the list expects them, without regard to case or accents
	 * (`compare_names()`), and the accounts of one name by ID, so no two accounts are ever tied.
	 *
	 * @param array $args The page's `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	private function by_institution( array $args ) {
		$number = isset( $args['number'] ) ? (int) $args['number'] : 0;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$desc   = isset( $args['order'] ) && 'DESC' === $args['order'];

		$every = new WP_User_Query(
			array_merge(
				$args,
				array(
					'fields'      => 'ID',
					'number'      => -1,
					'offset'      => 0,
					'orderby'     => 'ID',
					'order'       => 'ASC',
					'count_total' => false,
				)
			)
		);

		$ids    = array_values( array_filter( array_map( array( 'WPCPM_Roles', 'id_of' ), (array) $every->get_results() ) ) );
		$roster = $this->roster();

		usort(
			$ids,
			static function ( $a, $b ) use ( $roster, $desc ) {
				$left  = isset( $roster[ $a ] ) ? $roster[ $a ] : array(
					'name'        => '',
					'institution' => '',
				);
				$right = isset( $roster[ $b ] ) ? $roster[ $b ] : array(
					'name'        => '',
					'institution' => '',
				);
				$order = self::compare_institutions( $left['institution'], $right['institution'] );

				if ( 0 !== $order ) {
					return $desc ? -$order : $order;
				}

				$order = self::compare_names( $left['name'], $right['name'] );

				return 0 !== $order ? $order : $a - $b;
			}
		);

		$page = array_slice( $ids, $offset, $number > 0 ? $number : null );

		if ( empty( $page ) ) {
			return array(
				'items' => array(),
				'total' => count( $ids ),
			);
		}

		$query = new WP_User_Query(
			array(
				'role'        => static::role(),
				'include'     => $page,
				'orderby'     => 'include',
				'number'      => count( $page ),
				'count_total' => false,
			)
		);

		return array(
			'items' => (array) $query->get_results(),
			'total' => count( $ids ),
		);
	}

	/**
	 * Two institutions in A to Z order, without regard to case or accents (`compare_names()`), so
	 * École sorts among the E's, and no institution after every one.
	 *
	 * @param string $a One institution, or ''.
	 * @param string $b The other.
	 * @return int
	 */
	private static function compare_institutions( $a, $b ) {
		if ( $a === $b ) {
			return 0;
		}

		if ( '' === $a ) {
			return 1;
		}

		if ( '' === $b ) {
			return -1;
		}

		return self::compare_names( $a, $b );
	}

	/**
	 * Whether a name holds a term, without regard to case, in any script PHP's multibyte functions
	 * know when they are there, and in plain letters otherwise.
	 *
	 * @param string $name The name.
	 * @param string $term The term.
	 * @return bool
	 */
	private static function contains( $name, $term ) {
		if ( '' === $term ) {
			return true;
		}

		return false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $name, $term ) : stripos( $name, $term ) );
	}

	/**
	 * Every Student account's name and institution, read once for the table.
	 *
	 * **The one read of every Student account's program row a screen load makes**, as the list has
	 * made since 1.117.1: the institution picker counts from it, and the filter, the search and the
	 * Institution sort take the institution from it, because the institution has no meta key a query
	 * could use. WordPress primes every account's meta in the same read, so the program rows cost no
	 * query each. A meta key of the institution's own, written by the sync, would let a query do all
	 * three.
	 *
	 * @return array<int, array{name: string, institution: string}>
	 */
	private function roster() {
		if ( null !== $this->roster ) {
			return $this->roster;
		}

		$this->roster = array();

		$query = new WP_User_Query(
			array(
				'role'        => static::role(),
				'number'      => -1,
				'orderby'     => 'ID',
				'count_total' => false,
			)
		);

		foreach ( (array) $query->get_results() as $user ) {
			$id = WPCPM_Roles::id_of( $user );

			if ( ! $id ) {
				continue;
			}

			$program = WPCPM_Students_Sync::get_program( $id );

			$this->roster[ $id ] = array(
				'name'        => isset( $user->display_name ) ? (string) $user->display_name : '',
				'institution' => isset( $program['institution'] ) ? trim( (string) $program['institution'] ) : '',
			);
		}

		return $this->roster;
	}

	/**
	 * Every institution with Student accounts, and how many each has, by name.
	 *
	 * @return array<string, int> Institution => accounts.
	 */
	private function institution_counts() {
		$counts = array();

		foreach ( $this->roster() as $row ) {
			if ( '' === $row['institution'] ) {
				continue;
			}

			$counts[ $row['institution'] ] = isset( $counts[ $row['institution'] ] ) ? $counts[ $row['institution'] ] + 1 : 1;
		}

		ksort( $counts );

		return $counts;
	}

	/**
	 * Above the table, the institution picker: every school with its count, the one in force chosen,
	 * and a Filter button, which core never reads as a bulk action.
	 *
	 * In the list's own form, which goes to the screen by GET, so a narrowed list is an address
	 * somebody can bookmark or send to a colleague. That matters here, because the next thing they
	 * do with it may be to email a few dozen people.
	 *
	 * @param string $which `top` or `bottom`.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$institutions = $this->institution_counts();

		if ( empty( $institutions ) ) {
			return;
		}

		$current = WPCPM_Students::institution_filter();

		echo '<div class="alignleft actions">';
		printf( '<label for="wpcpm-institution" class="screen-reader-text">%s</label>', esc_html__( 'Filter by institution', 'wpcredits-program-manager' ) );
		printf( '<select name="%s" id="wpcpm-institution">', esc_attr( self::INSTITUTION_ARG ) );
		printf( '<option value="">%s</option>', esc_html__( 'All institutions', 'wpcredits-program-manager' ) );

		foreach ( $institutions as $name => $count ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $name ),
				selected( (string) $name, $current, false ),
				esc_html( sprintf( '%1$s (%2$s)', $name, number_format_i18n( $count ) ) )
			);
		}

		echo '</select>';
		submit_button( __( 'Filter', 'wpcredits-program-manager' ), '', 'filter_action', false, array( 'id' => 'wpcpm-institution-submit' ) );

		if ( '' !== $current ) {
			printf(
				' <a href="%1$s">%2$s</a>',
				esc_url( $this->page_url( array( self::INSTITUTION_ARG => false ) ) ),
				esc_html__( 'Show all students', 'wpcredits-program-manager' )
			);
		}

		echo '</div>';
	}

	/**
	 * The Accounts tab, keeping the institution in force, so a view keeps the list narrowed.
	 *
	 * Encoded here, because `add_query_arg()` sets a value as it is given, and a school's name can
	 * hold an ampersand.
	 *
	 * @param array $args Query arguments; false removes one.
	 * @return string
	 */
	protected function page_url( array $args = array() ) {
		$institution = WPCPM_Students::institution_filter();

		if ( '' !== $institution ) {
			$args = array_merge( array( self::INSTITUTION_ARG => rawurlencode( $institution ) ), $args );
		}

		return parent::page_url( $args );
	}

	/**
	 * The sentence in the empty row: how accounts arrive while there are none, by the sync on the
	 * screen's other tab, and that the list found nobody when a search, a view or an institution
	 * narrowed it.
	 */
	public function no_items() {
		if ( '' === $this->search_clause() && 'all' === $this->current_view() && '' === WPCPM_Students::institution_filter() ) {
			esc_html_e( 'No student accounts yet. Run a sync on the Sync tab to create them.', 'wpcredits-program-manager' );

			return;
		}

		esc_html_e( 'No student accounts found.', 'wpcredits-program-manager' );
	}

	/**
	 * The program, from the row the students sync writes.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_program( $user ) {
		$program = WPCPM_Students_Sync::get_program( $user->ID );

		return self::or_dash( isset( $program['program'] ) ? $program['program'] : '' );
	}

	/**
	 * The institution, from the same row.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_institution( $user ) {
		$program = WPCPM_Students_Sync::get_program( $user->ID );

		return self::or_dash( isset( $program['institution'] ) ? $program['institution'] : '' );
	}

	/**
	 * The mentor's name, from the contact card the sync writes.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_mentor( $user ) {
		$mentor = WPCPM_Students_Sync::get_mentor( $user->ID );

		return self::or_dash( isset( $mentor['name'] ) ? $mentor['name'] : '' );
	}

	/**
	 * A value for a cell, escaped, or a hyphen where there is none, as the list has always shown it.
	 *
	 * @param mixed $value The value.
	 * @return string
	 */
	private static function or_dash( $value ) {
		$value = trim( (string) $value );

		return esc_html( '' === $value ? '-' : $value );
	}

	/**
	 * A row's actions: the base's Edit, the account's editor, when the person looking may open it;
	 * View page, the student page as that student, while the page exists; then the base's invitation.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		$actions = $this->edit_row_action( $user );
		$page    = $this->student_page();

		if ( '' !== $page ) {
			$actions['view'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( add_query_arg( 'wpcpm_student_view', (int) $user->ID, $page ) ),
				esc_html__( 'View page', 'wpcredits-program-manager' )
			);
		}

		return array_merge( $actions, parent::row_actions_for( $user ) );
	}

	/**
	 * Where the list stands, which a row's invitation carries, so the invitation comes back to the same
	 * view, search, institution, sort and page: the Students screen's own reading of it, the one a
	 * press on the ticked accounts comes back through (`WPCPM_Students::list_state()`).
	 *
	 * @return array<string, string>
	 */
	protected function list_state() {
		if ( null === $this->list_state ) {
			$this->list_state = WPCPM_Students::list_state();
		}

		return $this->list_state;
	}

	/**
	 * The student page's address, or '' while the page is missing.
	 *
	 * @return string
	 */
	private function student_page() {
		if ( null === $this->student_page ) {
			$this->student_page = (string) WPCPM_Students_Dashboard::page_url();
		}

		return $this->student_page;
	}
}
