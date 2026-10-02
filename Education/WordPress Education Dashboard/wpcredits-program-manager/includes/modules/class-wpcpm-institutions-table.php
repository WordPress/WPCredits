<?php
/**
 * The Institutions screen's list of institution accounts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every institution account as a WordPress list table: the name, the username, the institution
 * the account acts for, since when, and whether it still does, searched, sorted and paged; and a
 * fourth view, No account, of the Confirmed institutions that have none yet, where the missing
 * accounts are created.
 *
 * **Institution, Member since and Status are sorted by the table, not by a query.** An account holds
 * its institution as a record ID (`WPCPM_Institution_Members::META_RECORD_ID`) whose name lives in
 * the pipeline index, the day its membership began inside a serialized row (`META_MEMBERSHIP`), and
 * nothing it holds says it is locked for the day: the roster's ceiling does
 * (`WPCPM_Institution_Roster::locked_today()`). No `orderby` reaches any of the three, so for those
 * sorts the table reads every account the list may hold and orders them itself (`by_value()`), as
 * the Mentors list orders its counts. That read is bounded by the pipeline: a few accounts for each
 * of some two hundred institution records, tens of accounts on the site. An audience past a thousand
 * accounts gets an indexed meta written by its sync before it gets a sort by value. The index and
 * the locked accounts are read once a draw and kept here, and so is each account's reading of them.
 *
 * **The search reaches the institution's name too**, which no column of an account holds: the
 * accounts WordPress finds by the username, the email or the name, and the accounts acting for an
 * institution whose name in the index holds the term, handed to WordPress as IDs (`include`), as the
 * Students list hands it the students its search finds by their institution.
 *
 * **No account lists records, not accounts** (`record_views()`): the Confirmed institutions with no
 * live member, the provisioning worklist (`WPCPM_Institutions::provision_worklist()`), the ready
 * ones first, each saying whether Airtable holds an address for it and, while it cannot be given an
 * account, why. Its bulk action, Create account, is the table's own (`owns_action()`,
 * `handle_action()`) and keeps the rules provisioning has always kept
 * (`WPCPM_Institutions::provision_ticked()`), and a row that is not ready cannot be ticked for it
 * (`row_tickable()`); a ready row's own Create account is a nonce link to the module's handler for
 * one institution, and a row's View page opens the Institution Dashboard as that institution, as
 * an account row's does (`record_row_actions()`). Above that view's list (`list_intro()`) come
 * the gate, while it holds back every Create account on the ticked institutions, and, while the
 * view lists an institution ready for one, what a Create account sends, which a list's Apply has no
 * dialog to say; under it (`list_outro()`), whether the sync creates these accounts too, and when
 * the index it is read from was read.
 *
 * **Manage members** is on every row that names an institution: every record's, and an account's by
 * the institution it acts for or, once its membership ended, the one it left (`members_action()`).
 * It opens that institution's view of the Accounts tab, where its accounts are added, removed and
 * re-added; an institution provisioning will not give its first account, such as one that has had a
 * member, is given one there by hand.
 *
 * No address is printed on either kind of view: the search covers the email, and the Contact column
 * says whether there is one.
 */
class WPCPM_Institutions_Table extends WPCPM_Accounts_Table {

	/** The record view of the Confirmed institutions that have no account yet. */
	const VIEW_NO_ACCOUNT = 'no-account';

	/** That view's bulk action, which the table carries out itself. */
	const ACTION_CREATE = 'create';

	/** What the Institution column sorts by: the institution's name as the cell shows it, ordered by the table. */
	const ORDERBY_INSTITUTION = 'institution';

	/** What the Member since column sorts by: the day the membership began, ordered by the table. */
	const ORDERBY_SINCE = 'since';

	/** What the Status column sorts by: the membership's state as its words run, ordered by the table. */
	const ORDERBY_STATUS = 'status';

	/**
	 * The pipeline index's rows, read once a draw: record ID => row.
	 *
	 * @var array|null
	 */
	private $index;

	/**
	 * The accounts locked out of roster changes for the rest of today, read once a draw: user ID => the account.
	 *
	 * @var array<int, WP_User>|null
	 */
	private $locked;

	/**
	 * What each account's cells and sorts read, worked out once a draw: user ID => facts.
	 *
	 * @var array<int, array>
	 */
	private $facts = array();

	/**
	 * Every institution account, read once a draw when the search needs it.
	 *
	 * @var WP_User[]|null
	 */
	private $every;

	/**
	 * The No account view's rows, read once a draw: the worklist costs a membership query or two for
	 * each Confirmed institution, and the view's count, its rows and its gate all read it.
	 *
	 * @var array[]|null
	 */
	private $worklist;

	/**
	 * The Institution Dashboard's address, read once: '' while the page is missing.
	 *
	 * @var string|null
	 */
	private $dashboard;

	/**
	 * Where the list stands, read once for every row's links.
	 *
	 * @var array<string, string>|null
	 */
	private $list_state;

	/**
	 * The audience: the Institutions module's ID.
	 *
	 * @return string
	 */
	protected static function audience() {
		return 'institutions';
	}

	/**
	 * The role the accounts hold, which the Institutions module's invitations read here too.
	 *
	 * @return string
	 */
	public static function role() {
		return WPCPM_Roles::ROLE_INSTITUTION;
	}

	/**
	 * The columns: the account's name first, the primary column, which Screen Options never offers to
	 * hide, so the row's actions under it cannot be hidden with it.
	 *
	 * @return array<string, string>
	 */
	protected function columns() {
		return array(
			'name'        => __( 'Name', 'wpcredits-program-manager' ),
			'login'       => __( 'Username', 'wpcredits-program-manager' ),
			'institution' => __( 'Institution', 'wpcredits-program-manager' ),
			'since'       => __( 'Member since', 'wpcredits-program-manager' ),
			'status'      => __( 'Status', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The name sorts by display name and the username by login, as WordPress sorts them; Institution,
	 * Member since and Status by the order the table puts the accounts in (`by_value()`).
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array(
			'name'        => 'display_name',
			'login'       => 'login',
			'institution' => self::ORDERBY_INSTITUTION,
			'since'       => self::ORDERBY_SINCE,
			'status'      => self::ORDERBY_STATUS,
		);
	}

	/**
	 * The accounts a set of `WP_User_Query` arguments finds, narrowed to what the search finds and
	 * sorted by a value when the list is.
	 *
	 * The search goes into the IDs rather than to WordPress, whose search covers only the account's
	 * own columns (`search_ids()`), and the query then holds no search of its own. A search that finds
	 * nobody asks for the one ID nobody holds: WordPress reads an empty `include` as no narrowing at
	 * all, which would list every account.
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	protected function query( array $args ) {
		if ( ! empty( $args['search'] ) ) {
			$found           = $this->search_ids( (string) $args['search'], isset( $args['search_columns'] ) ? (array) $args['search_columns'] : array() );
			$args['include'] = empty( $found ) ? array( 0 ) : $found;
		}

		unset( $args['search'], $args['search_columns'] );

		$sorted_by = self::sorted_by( $args );

		if ( in_array( $sorted_by, array( self::ORDERBY_INSTITUTION, self::ORDERBY_SINCE, self::ORDERBY_STATUS ), true ) ) {
			return $this->by_value( $args, $sorted_by );
		}

		$query = new WP_User_Query( $args );

		return array(
			'items' => (array) $query->get_results(),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * The accounts a search finds: the ones WordPress finds by the username, the email or the name,
	 * then the ones acting for an institution whose name in the index holds the term, without regard
	 * to case. Only the index's name: an account whose institution the index no longer holds shows its
	 * record ID, which names nothing.
	 *
	 * @param string   $search  The search, with its wildcards.
	 * @param string[] $columns The account columns WordPress searches.
	 * @return int[]
	 */
	private function search_ids( $search, array $columns ) {
		$query = new WP_User_Query(
			array(
				'role'           => static::role(),
				'search'         => $search,
				'search_columns' => $columns,
				'fields'         => 'ID',
				'number'         => -1,
				'count_total'    => false,
			)
		);

		// Each ID through `id_of()`, because the program's own site answers a request for IDs with rows.
		$ids   = array_map( array( 'WPCPM_Roles', 'id_of' ), (array) $query->get_results() );
		$term  = trim( $search, '*' );
		$index = $this->index();

		foreach ( $this->every_account() as $user ) {
			$record = $this->facts_of( $user )['record'];

			if ( isset( $index[ $record ]['name'] ) && self::contains( (string) $index[ $record ]['name'], $term ) ) {
				$ids[] = (int) $user->ID;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * One page of the accounts sorted by a value: every account the page's other arguments find, read
	 * whole and ordered here, and the page's slice of them.
	 *
	 * Each account sorts where its cell says (`value_of()`), in the order `compare_accounts()` puts
	 * them in, which leaves no two accounts tied, so none shows on two pages while another is on
	 * none. The order they are read in does not settle a tie: PHP's sort before 8.0 may put two equal
	 * items either way round. WordPress primes every account's meta with the read, so the values cost
	 * no query each.
	 *
	 * @param array  $args      The page's `WP_User_Query` arguments.
	 * @param string $sorted_by One of the three `ORDERBY_` values the table sorts by itself.
	 * @return array{items: WP_User[], total: int}
	 */
	private function by_value( array $args, $sorted_by ) {
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
			$accounts[] = array(
				'user'  => $user,
				'value' => $this->value_of( $user, $sorted_by ),
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
	 * Two accounts in the order a sort by a value puts them: by the value, whichever way the list
	 * runs, a name read as a reader expects names (`compare_names()`) and a number as a number, a
	 * missing value first from A to Z; the accounts of one value by name, A to Z whichever way the
	 * values run; and the accounts of one name by ID. No two accounts are ever tied, so the order is
	 * one on every PHP, 7.4's unstable sort included.
	 *
	 * @param array{user: WP_User, value: string|int} $a    One account and its value.
	 * @param array{user: WP_User, value: string|int} $b    The other.
	 * @param bool                                    $desc Whether the values run from the highest.
	 * @return int Below 0 when the first comes first, above 0 when the second does.
	 */
	private static function compare_accounts( array $a, array $b, $desc ) {
		$order = is_string( $a['value'] )
			? self::compare_names( $a['value'], (string) $b['value'] )
			: (int) $a['value'] <=> (int) $b['value'];

		if ( 0 !== $order ) {
			return $desc ? -$order : $order;
		}

		$order = self::compare_names( $a['user']->display_name, $b['user']->display_name );

		return 0 !== $order ? $order : (int) $a['user']->ID - (int) $b['user']->ID;
	}

	/**
	 * What an account sorts by, read as its cell shows it: the institution's name, '' for an account
	 * acting for none; the day its membership began, 0 where none is written; or its status's rank,
	 * the order its words run in from A to Z: none, Active, Active and locked today, Membership
	 * ended, and that ended and locked.
	 *
	 * @param WP_User $user      The account.
	 * @param string  $sorted_by One of the three `ORDERBY_` values the table sorts by itself.
	 * @return string|int
	 */
	private function value_of( WP_User $user, $sorted_by ) {
		$facts = $this->facts_of( $user );

		if ( self::ORDERBY_INSTITUTION === $sorted_by ) {
			return $facts['institution'];
		}

		if ( self::ORDERBY_SINCE === $sorted_by ) {
			return $facts['since'];
		}

		$ranks = array(
			''       => 0,
			'active' => 1,
			'ended'  => 3,
		);
		$rank  = $ranks[ $facts['state'] ];

		return ( $facts['locked'] && $rank > 0 ) ? $rank + 1 : $rank;
	}

	/**
	 * What an account's cells and sorts read, worked out once a draw: the record it acts for
	 * (`record`), that record's name in the index, trimmed, or the record ID where the index lacks it,
	 * or '' for an account acting for none (`institution`), the day its membership began, 0 where
	 * none is written (`since`), its membership's state (`state`) and whether the roster's ceiling
	 * locked it today (`locked`).
	 *
	 * The state is `active` while the members class holds the membership open (its flag at 1),
	 * `ended` once it closed it, the flag at 0, or moved the stamp aside (`META_RECORD_ID_WAS`) and
	 * left none, and '' for an account holding the role and nothing of a membership, such as one
	 * provisioning made and could not stamp. An ended membership shows on this list only for an
	 * account that still holds the role: closing one takes the role away (`detach()`) unless the
	 * account is a WordPress administrator, so it is one of those, or one given the role again.
	 *
	 * @param WP_User $user The account.
	 * @return array{record: string, institution: string, since: int, state: string, locked: bool}
	 */
	private function facts_of( WP_User $user ) {
		$id = (int) $user->ID;

		if ( isset( $this->facts[ $id ] ) ) {
			return $this->facts[ $id ];
		}

		$record     = trim( (string) get_user_meta( $id, WPCPM_Institution_Members::META_RECORD_ID, true ) );
		$membership = get_user_meta( $id, WPCPM_Institution_Members::META_MEMBERSHIP, true );
		$index      = $this->index();
		$name       = ( '' !== $record && isset( $index[ $record ]['name'] ) ) ? trim( (string) $index[ $record ]['name'] ) : '';

		if ( '1' === (string) get_user_meta( $id, WPCPM_Institution_Members::META_ACTIVE, true ) ) {
			$state = 'active';
		} elseif ( metadata_exists( 'user', $id, WPCPM_Institution_Members::META_ACTIVE ) || ( '' === $record && '' !== trim( (string) get_user_meta( $id, WPCPM_Institution_Members::META_RECORD_ID_WAS, true ) ) ) ) {
			$state = 'ended';
		} else {
			$state = '';
		}

		$locked = $this->locked();

		$this->facts[ $id ] = array(
			'record'      => $record,
			'institution' => '' !== $name ? $name : $record,
			'since'       => ( is_array( $membership ) && isset( $membership['since'] ) ) ? (int) $membership['since'] : 0,
			'state'       => $state,
			'locked'      => isset( $locked[ $id ] ),
		);

		return $this->facts[ $id ];
	}

	/**
	 * Whether a name holds a term, without regard to case or accents, as the list sorts names
	 * (`compare_names()`), so "Ecole" finds École: both read through `remove_accents()`, then
	 * compared in any script PHP's multibyte functions know when they are there, and in plain letters
	 * otherwise.
	 *
	 * @param string $name The name.
	 * @param string $term The term.
	 * @return bool
	 */
	private static function contains( $name, $term ) {
		if ( '' === $term ) {
			return true;
		}

		$name = remove_accents( (string) $name );
		$term = remove_accents( (string) $term );

		return false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $name, $term ) : stripos( $name, $term ) );
	}

	/**
	 * The pipeline index's rows, read once a draw.
	 *
	 * @return array Record ID => row.
	 */
	private function index() {
		if ( null === $this->index ) {
			$this->index = WPCPM_Institutions_Index::rows();
		}

		return $this->index;
	}

	/**
	 * The accounts the roster's ceiling locked for the rest of today, as the list read them once for
	 * its Status cells: for the notice above the list that names them, so one draw of the Accounts tab
	 * asks the ceiling once (`WPCPM_Institutions::render_tab_accounts()`).
	 *
	 * @return WP_User[]
	 */
	public function locked_accounts() {
		return array_values( $this->locked() );
	}

	/**
	 * The accounts the roster's ceiling locked for the rest of today, read once a draw.
	 *
	 * @return array<int, WP_User> User ID => the account.
	 */
	private function locked() {
		if ( null === $this->locked ) {
			$this->locked = array();

			foreach ( (array) WPCPM_Institution_Roster::locked_today() as $user ) {
				if ( $user instanceof WP_User ) {
					$this->locked[ (int) $user->ID ] = $user;
				}
			}
		}

		return $this->locked;
	}

	/**
	 * Every institution account, read once a draw, for the search by an institution's name.
	 *
	 * @return WP_User[]
	 */
	private function every_account() {
		if ( null === $this->every ) {
			$query = new WP_User_Query(
				array(
					'role'        => static::role(),
					'number'      => -1,
					'orderby'     => 'ID',
					'count_total' => false,
				)
			);

			$this->every = array_values(
				array_filter(
					(array) $query->get_results(),
					static function ( $user ) {
						return $user instanceof WP_User;
					}
				)
			);
		}

		return $this->every;
	}

	/**
	 * The No account view's rows, read once a draw.
	 *
	 * @return array[]
	 */
	private function worklist() {
		if ( null === $this->worklist ) {
			$this->worklist = WPCPM_Institutions::provision_worklist();
		}

		return $this->worklist;
	}

	/**
	 * The sentence in the empty row: on the account views, that the list found nobody; on the No
	 * account view, when a search narrowed it to nothing, that it found none; when it holds nothing,
	 * that every Confirmed institution has an account, or, while the index holds no institution at
	 * Confirmed at all, that none has reached it, which would make the first sentence a false one.
	 */
	public function no_items() {
		if ( '' === $this->record_view() ) {
			esc_html_e( 'No institution accounts found.', 'wpcredits-program-manager' );

			return;
		}

		if ( ! empty( $this->worklist() ) ) {
			esc_html_e( 'No institutions found.', 'wpcredits-program-manager' );

			return;
		}

		foreach ( $this->index() as $row ) {
			if ( is_array( $row ) && isset( $row['stage'] ) && 'Confirmed' === trim( (string) $row['stage'] ) ) {
				esc_html_e( 'Every Confirmed institution has an account.', 'wpcredits-program-manager' );

				return;
			}
		}

		esc_html_e( 'No institution has reached Confirmed yet, so there is nothing to create.', 'wpcredits-program-manager' );
	}

	/**
	 * Whether a row may be ticked: every account row, and on the No account view a row that is ready.
	 *
	 * Create account is the one bulk action on that view, and a row it can only refuse would turn
	 * the header's tick-all into a notice that names refusals beside every account it made. Core's
	 * own users list leaves the box out of a row the person looking may not act on in the same way.
	 * `WPCPM_Institutions::provision_ticked()` still refuses a row that stopped being ready after
	 * the page was drawn.
	 *
	 * @param WP_User|array $item The row: an account, or a record on the No account view.
	 * @return bool
	 */
	protected function row_tickable( $item ) {
		return ! is_array( $item ) || ! empty( $item['ready'] );
	}

	/**
	 * The institution an account acts for, by its name in the index, or the record ID where the index
	 * lacks it, and nothing for an account acting for none; on the No account view, the institution's
	 * name over its record ID, which a manager searches the base by.
	 *
	 * @param WP_User|array $item The row: an account, or a record on the No account view.
	 * @return string
	 */
	protected function column_institution( $item ) {
		if ( is_array( $item ) ) {
			$id   = ( isset( $item['id'] ) && is_scalar( $item['id'] ) ) ? (string) $item['id'] : '';
			$name = ( isset( $item['name'] ) && is_scalar( $item['name'] ) && '' !== (string) $item['name'] ) ? (string) $item['name'] : $id;

			return sprintf( '<strong>%1$s</strong><br /><code class="wpcpm-inst-record">%2$s</code>', esc_html( $name ), esc_html( $id ) );
		}

		return $item instanceof WP_User ? esc_html( $this->facts_of( $item )['institution'] ) : '';
	}

	/**
	 * The day the account's membership began, in the site's date format, and nothing where none is
	 * written, as for an account older than the membership facts.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_since( $user ) {
		$since = $user instanceof WP_User ? $this->facts_of( $user )['since'] : 0;

		return $since > 0 ? esc_html( wp_date( (string) get_option( 'date_format' ), $since ) ) : '';
	}

	/**
	 * Active while the membership is open, Membership ended once it closed, and either followed by
	 * "locked today" for an account the roster's ceiling locked for the rest of the day.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_status( $user ) {
		if ( ! $user instanceof WP_User ) {
			return '';
		}

		$facts = $this->facts_of( $user );

		if ( 'active' === $facts['state'] ) {
			$label = __( 'Active', 'wpcredits-program-manager' );
		} elseif ( 'ended' === $facts['state'] ) {
			$label = __( 'Membership ended', 'wpcredits-program-manager' );
		} else {
			return '';
		}

		if ( $facts['locked'] ) {
			/* translators: %s: the account's membership, "Active" or "Membership ended". */
			$label = sprintf( __( '%s, locked today', 'wpcredits-program-manager' ), $label );
		}

		return esc_html( $label );
	}

	/**
	 * On the No account view, whether the institution can be given an account: Ready, or why not, in
	 * the words the institutions sync's own run uses (`WPCPM_Institutions_Sync::provision_message()`).
	 *
	 * @param array $row The record row.
	 * @return string
	 */
	protected function column_account( $row ) {
		if ( ! is_array( $row ) ) {
			return '';
		}

		if ( ! empty( $row['ready'] ) ) {
			return esc_html__( 'Ready', 'wpcredits-program-manager' );
		}

		return esc_html( WPCPM_Institutions_Sync::provision_message( isset( $row['reason'] ) ? (string) $row['reason'] : '' ) );
	}

	/**
	 * A row's actions: the base's Edit, the account's editor, when the person looking may open it;
	 * View page, the Institution Dashboard as the institution the account acts for, through the
	 * switcher's own argument, as the Administrator Dashboard links an institution, while the page
	 * exists; then the base's invitation; then Manage members, the view of the institution the
	 * account acts for or, once its membership ended, of the one it left, and nothing for an account
	 * that names no institution.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		$actions = $this->edit_row_action( $user );
		$record  = $this->facts_of( $user )['record'];
		$actions = array_merge( $actions, $this->view_action( $record ) );

		// An ended membership keeps no live stamp: the members class moved it aside, and the stamp it
		// moved names the institution whose former members list this account with its Re-add.
		$members = WPCPM_Mentors_Sync::is_record_id( $record )
			? $record
			: trim( (string) get_user_meta( $user->ID, WPCPM_Institution_Members::META_RECORD_ID_WAS, true ) );

		return array_merge( $actions, parent::row_actions_for( $user ), $this->members_action( $members ) );
	}

	/**
	 * A No account row's actions: Create account, for an institution ready for one; View page, the
	 * Institution Dashboard as that institution, the address an account row's View page opens, while
	 * the page exists; then Manage members, on every row, since an institution provisioning will not
	 * give its first account is given one there by hand, and one that has had a member has that member
	 * to re-add there.
	 *
	 * View page is offered on a row with no account because the institution still has its Institution
	 * Dashboard, which a manager opens as that institution through the switcher, as the Administrator
	 * Dashboard links an institution.
	 *
	 * Create account is a nonce link to the module's handler for one institution, with the record ID
	 * as Airtable writes it, case and all, the tab and where the list stands, so the handler comes back
	 * to the view as it stood, under a nonce keyed to the institution, so a link taken from one row is
	 * no use on another. The table is one form, so a row's action cannot be a form of its own.
	 *
	 * @param array $row The record row.
	 * @return array<string, string> `create`, `view` and `members` => the links, in that order: no
	 *                               `create` for a row that is not ready, no `view` while the page is
	 *                               missing, and nothing for a row that names no record.
	 */
	protected function record_row_actions( array $row ) {
		$record = ( isset( $row['id'] ) && is_scalar( $row['id'] ) ) ? (string) $row['id'] : '';
		$links  = $this->view_action( $record ) + $this->members_action( $record );

		if ( '' === $record || empty( $row['ready'] ) ) {
			return $links;
		}

		$action = WPCPM_Institutions::ACTION_PROVISION_ONE;
		$url    = wp_nonce_url(
			add_query_arg(
				// The link's own arguments win over the list's state, which only adds to them.
				array(
					'action'                            => $action,
					WPCPM_Institutions::ARG_INSTITUTION => rawurlencode( $record ),
					self::TAB_FIELD                     => self::TAB,
				) + $this->list_state(),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $record
		);

		return array( 'create' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Create account', 'wpcredits-program-manager' ) ) ) + $links;
	}

	/**
	 * View page, as action => link: the Institution Dashboard as one institution, through the
	 * switcher's own argument, as the Administrator Dashboard links an institution; nothing while the
	 * page is missing, or for a value that is no record ID, which names no institution to show.
	 *
	 * One address for both kinds of row: an account's institution and a No account row's record.
	 *
	 * @param string $record The institution's record ID, with its case.
	 * @return array<string, string> `view` => the link, or nothing.
	 */
	private function view_action( $record ) {
		$page = $this->dashboard();

		if ( '' === $page || ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		return array(
			'view' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( add_query_arg( WPCPM_Institution_Roster::ARG_VIEW, rawurlencode( $record ), $page ) ),
				esc_html__( 'View page', 'wpcredits-program-manager' )
			),
		);
	}

	/**
	 * Manage members, as action => link: the Accounts tab's view of one institution's members, with
	 * where the list stands, so the view's way back returns to the list as it stood; nothing for a
	 * value that is no record ID, which names no institution to open.
	 *
	 * @param string $record The institution's record ID, with its case.
	 * @return array<string, string> `members` => the link, or nothing.
	 */
	private function members_action( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return array();
		}

		// The record wins over the list's state, which only adds to it.
		$url = $this->page_url( array( WPCPM_Institutions::ARG_INSTITUTION => rawurlencode( $record ) ) + $this->list_state() );

		return array( 'members' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Manage members', 'wpcredits-program-manager' ) ) );
	}

	/**
	 * The record view: No account, the Confirmed institutions with no account yet, its label picked
	 * by its count by the base.
	 *
	 * @return array<string, array> View => its label, as `_n_noop()` returns it.
	 */
	protected function record_views() {
		return array(
			/* translators: %s: how many institutions, in parentheses. */
			self::VIEW_NO_ACCOUNT => _n_noop( 'No account %s', 'No account %s', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * How many institutions the No account view holds: the whole worklist, not the search.
	 *
	 * @param string $view A record view.
	 * @return int
	 */
	protected function count_records( $view ) {
		return self::VIEW_NO_ACCOUNT === $view ? count( $this->worklist() ) : 0;
	}

	/**
	 * The No account view's rows: the worklist, the ready ones first and then by name, searched by
	 * the institution's name, or ordered by it from A to Z or Z to A when the list is sorted by the
	 * Institution column, the record ID settling two of one name.
	 *
	 * @param string $view A record view.
	 * @return array[]
	 */
	protected function record_rows( $view ) {
		if ( self::VIEW_NO_ACCOUNT !== $view ) {
			return array();
		}

		$rows = $this->worklist();
		$term = WPCPM_Request::text( 's' );

		if ( '' !== $term ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $term ) {
						return self::contains( (string) $row['name'], $term );
					}
				)
			);
		}

		$asked = WPCPM_Request::key( 'orderby' );

		foreach ( $this->record_sortable() as $sortable ) {
			if ( '' === $asked || sanitize_key( (string) $sortable[0] ) !== $asked ) {
				continue;
			}

			$desc = 'desc' === WPCPM_Request::key( 'order' );

			usort(
				$rows,
				static function ( $a, $b ) use ( $desc ) {
					$order = self::compare_names( $a['name'], $b['name'] );

					if ( 0 !== $order ) {
						return $desc ? -$order : $order;
					}

					return strcmp( (string) $a['id'], (string) $b['id'] );
				}
			);
		}

		return $rows;
	}

	/**
	 * The No account view's columns: the institution, whether Airtable holds an address for it, and
	 * whether it can be given an account.
	 *
	 * @return array<string, string>
	 */
	protected function record_columns() {
		return array(
			'institution' => __( 'Institution', 'wpcredits-program-manager' ),
			'contact'     => __( 'Contact', 'wpcredits-program-manager' ),
			'account'     => __( 'Account', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The No account view sorts by the institution's name, which the table orders itself (`record_rows()`).
	 *
	 * @return array<string, array>
	 */
	protected function record_sortable() {
		return array( 'institution' => array( self::ORDERBY_INSTITUTION, false ) );
	}

	/**
	 * The No account view's bulk action: Create account.
	 *
	 * @param string $view A record view.
	 * @return array<string, string> Action => label.
	 */
	protected function record_bulk_actions( $view ) {
		return self::VIEW_NO_ACCOUNT === $view ? array( self::ACTION_CREATE => __( 'Create account', 'wpcredits-program-manager' ) ) : array();
	}

	/**
	 * Whether a bulk action is the table's own: Create account is.
	 *
	 * @param string $action The bulk action pressed.
	 * @return bool
	 */
	public function owns_action( $action ) {
		return self::ACTION_CREATE === $action;
	}

	/**
	 * Create the accounts of the ticked institutions, by the rules provisioning keeps
	 * (`WPCPM_Institutions::provision_ticked()`), and say what came of it.
	 *
	 * Reachable from whichever view the list was on, so it reads the ticked records alone, under
	 * `records[]`, keeping each only when the whole of it is a record ID as Airtable writes one, with
	 * its case: an account's ID ticked on an account view is none of them.
	 *
	 * @param string $action The bulk action pressed.
	 * @return array{0: string, 1: array} The outcome and its detail.
	 */
	public function handle_action( $action ) {
		if ( self::ACTION_CREATE !== $action ) {
			return array( '', array() );
		}

		return WPCPM_Institutions::provision_ticked( WPCPM_Request::list( self::RECORDS_FIELD, WPCPM_Airtable::RECORD_ID_PATTERN ) );
	}

	/**
	 * The words for what a Create account did, on the ticked institutions or on one row, from the
	 * detail `WPCPM_Institutions::provision_ticked()` returned, or the one a row's press carries: how
	 * many accounts it created; how many ticked institutions it left for the next press, since one
	 * press creates at most `PROVISION_LIMIT`; which already had an account, a press between the page
	 * and this one or the second press of a double click having made it; and, when it could not give
	 * some an account, which they were, by name, the ones the view explains apart from the ones whose
	 * creation failed, each of those with what was said. A list names its first few and how many
	 * more. Every other outcome, and any of these with nothing to say, keeps the screen's own
	 * sentence.
	 *
	 * @param string $status The outcome `handle_action()` returned.
	 * @param array  $detail What it returned beside it: `created`, `held`, `already` and
	 *                       `already_more`, `names` and `more`, `failed` and `failed_more`.
	 * @return string The sentence, as text; '' for the screen's.
	 */
	public static function action_sentence( $status, array $detail ) {
		if ( ! in_array( $status, array( 'provisioned', 'provision-failed', 'provision-already' ), true ) ) {
			return '';
		}

		$created   = isset( $detail['created'] ) ? (int) $detail['created'] : 0;
		$held      = isset( $detail['held'] ) ? (int) $detail['held'] : 0;
		$sentences = array();

		if ( $created > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: how many accounts were created. */
				_n( '%s account was created, with an invitation queued.', '%s accounts were created, each with an invitation queued.', $created, 'wpcredits-program-manager' ),
				number_format_i18n( $created )
			);
		}

		if ( $held > 0 ) {
			$sentences[] = sprintf(
				/* translators: 1: how many accounts one press creates at most, 2: how many ticked institutions were left for the next press. */
				_n( 'One press creates at most %1$s; %2$s ticked institution stays listed here.', 'One press creates at most %1$s; %2$s ticked institutions stay listed here.', $held, 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Institutions::PROVISION_LIMIT ),
				number_format_i18n( $held )
			);
		}

		list( $already, $had ) = self::names_in( $detail, 'already', 'already_more' );

		if ( $had > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: the institutions that already had an account, comma-separated. */
				_n( '%s already had an account.', 'These already had an account: %s.', $had, 'wpcredits-program-manager' ),
				implode( ', ', $already )
			);
		}

		if ( 'provision-failed' === $status ) {
			list( $names, $refused ) = self::names_in( $detail, 'names', 'more' );

			if ( $refused > 0 ) {
				$sentences[] = sprintf(
					/* translators: %s: the institutions, comma-separated. */
					__( 'These could not be given an account: %s. The No account view says why for each.', 'wpcredits-program-manager' ),
					implode( ', ', $names )
				);
			}

			list( $failed, $not_made ) = self::names_in( $detail, 'failed', 'failed_more' );

			if ( $not_made > 0 ) {
				$sentences[] = sprintf(
					/* translators: %s: the institutions, each with why its account could not be created, comma-separated. */
					_n( 'This could not be created: %s.', 'These could not be created: %s.', $not_made, 'wpcredits-program-manager' ),
					implode( ', ', $failed )
				);
			}
		}

		return implode( ' ', $sentences );
	}

	/**
	 * One list of institutions from a press's detail, as the sentence prints it: the names, then
	 * "and N more" where the detail counts more; and how many institutions that is in all, which
	 * picks the sentence's plural.
	 *
	 * @param array  $detail   The press's detail.
	 * @param string $key      The key holding the names.
	 * @param string $more_key The key holding how many more there are.
	 * @return array{0: string[], 1: int} The list as printed, and how many institutions it stands for.
	 */
	private static function names_in( array $detail, $key, $more_key ) {
		$names = ( isset( $detail[ $key ] ) && is_array( $detail[ $key ] ) ) ? array_map( 'strval', array_values( array_filter( $detail[ $key ], 'is_scalar' ) ) ) : array();
		$more  = ( ! empty( $names ) && isset( $detail[ $more_key ] ) ) ? max( 0, (int) $detail[ $more_key ] ) : 0;
		$total = count( $names ) + $more;

		if ( $more > 0 ) {
			$names[] = sprintf(
				/* translators: %s: how many more institutions there are. */
				_n( 'and %s more', 'and %s more', $more, 'wpcredits-program-manager' ),
				number_format_i18n( $more )
			);
		}

		return array( $names, $total );
	}

	/**
	 * Above the views, on the No account view: the gate while it applies and, while the view lists an
	 * institution ready for an account, what a Create account sends.
	 *
	 * Above the views, the first part of the list the screen draws, so both are read before the list
	 * and its search. What a Create account sends is said in words because the list's Apply has no
	 * dialog to ask with, and an invitation cannot be recalled; over a view where nothing can be
	 * created, it would warn of nothing.
	 */
	protected function list_intro() {
		if ( self::VIEW_NO_ACCOUNT !== $this->record_view() ) {
			return;
		}

		$this->render_gate();

		if ( in_array( true, array_column( $this->worklist(), 'ready' ), true ) ) {
			echo '<p>' . esc_html__( 'Creating an account emails a password-set link to the address Airtable holds for the institution. An invitation cannot be recalled once sent.', 'wpcredits-program-manager' ) . '</p>';
		}
	}

	/**
	 * Under the table, on the No account view: whether the sync creates these accounts too, and when
	 * the index the view is read from was read.
	 *
	 * Which of the two ways in is live is said plainly: the same rule decides both, and a manager who
	 * creates nothing here should still know whether accounts appear by the next sync. The view joins
	 * the index, as old as the last sync, to the memberships, read now, and says which is which.
	 */
	protected function list_outro() {
		if ( self::VIEW_NO_ACCOUNT !== $this->record_view() ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				WPCPM_Settings::get_value( 'institution_provision' )
					? __( 'The sync creates these accounts too, by the same rule, on top of anything made here.', 'wpcredits-program-manager' )
					: __( 'The sync does not create accounts: this tab is the only way one is made.', 'wpcredits-program-manager' )
			)
		);

		$index = WPCPM_Institutions_Index::read();

		printf( '<p class="wpcpm-inst-read">%s</p>', esc_html( WPCPM_Institutions::membership_read_line( isset( $index['read'] ) ? (int) $index['read'] : 0 ) ) );
	}

	/**
	 * The gate, while any Confirmed institution with no member has no agreement recorded: no account
	 * is created in bulk then, whatever else is ready, naming the first few in the view's order and
	 * counting the rest, and the way to the Agreements tab, which lists every one of them above the
	 * form that records them.
	 */
	private function render_gate() {
		$blocked = array();

		foreach ( $this->worklist() as $row ) {
			if ( WPCPM_Institutions_Sync::BLOCK_NO_AGREEMENT === $row['reason'] ) {
				$blocked[] = (string) $row['name'];
			}
		}

		if ( empty( $blocked ) ) {
			return;
		}

		$names = array_slice( $blocked, 0, WPCPM_Institutions::PROVISION_NAMES );
		$rest  = count( $blocked ) - count( $names );

		if ( $rest > 0 ) {
			$names[] = sprintf(
				/* translators: %s: how many more institutions there are. */
				_n( 'and %s more', 'and %s more', $rest, 'wpcredits-program-manager' ),
				number_format_i18n( $rest )
			);
		}

		printf(
			'<p class="wpcpm-warning">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html(
				sprintf(
					/* translators: 1: number of institutions, 2: their names, comma-separated. */
					_n(
						'No account is created in bulk while %1$s Confirmed institution on this view has no agreement recorded: %2$s.',
						'No account is created in bulk while %1$s Confirmed institutions on this view have no agreement recorded: %2$s.',
						count( $blocked ),
						'wpcredits-program-manager'
					),
					number_format_i18n( count( $blocked ) ),
					implode( ', ', $names )
				)
			),
			esc_url(
				add_query_arg(
					array(
						'page' => 'wpcpm-' . static::audience(),
						'tab'  => 'agreements',
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html( _n( 'Record it on the Agreements tab.', 'Record them on the Agreements tab.', count( $blocked ), 'wpcredits-program-manager' ) )
		);
	}

	/**
	 * Where the list stands, which a row's links carry, so the press comes back to the same view,
	 * search, sort and page: the Institutions screen's reading of it, the accounts screen's own, which
	 * a press on the ticked rows comes back through (`WPCPM_Institutions::list_state()`).
	 *
	 * @return array<string, string>
	 */
	protected function list_state() {
		if ( null === $this->list_state ) {
			$this->list_state = WPCPM_Institutions::list_state();
		}

		return $this->list_state;
	}

	/**
	 * The Institution Dashboard's address, or '' while the page is missing.
	 *
	 * @return string
	 */
	private function dashboard() {
		if ( null === $this->dashboard ) {
			$this->dashboard = (string) WPCPM_Institutions_Dashboard::page_url();
		}

		return $this->dashboard;
	}
}
