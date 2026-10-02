<?php
/**
 * The Sponsors screen's list of sponsor accounts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every sponsor account as a WordPress list table: the name, the username, the sponsor the account
 * acts for, since when, and whether it still does, searched, sorted and paged; and a fourth view, No
 * account, of the Approved sponsors that have none yet, where the missing accounts are created.
 *
 * **The list is the role**, as every audience's list is: every account holding the Sponsor role,
 * whatever its membership. An account whose membership ended keeps the role only when it was given
 * the role again by hand, or when it is an administrator's, whose roles
 * `WPCPM_Sponsor_Members::detach()` leaves alone; such an account is listed, and its Status says the
 * membership ended. The Status reads the flag the members class keeps on a live membership
 * (`WPCPM_Sponsor_Members::META_ACTIVE`), the one `live_accounts()` reads.
 *
 * **Sponsor, Member since and Status are sorted by the table, not by a query.** An account holds its
 * sponsor as a record ID (`WPCPM_Sponsor_Members::META_RECORD_ID`) whose name lives in the sponsors
 * index, the day its membership began inside a serialized row (`META_MEMBERSHIP`), and nothing it
 * holds says it is locked for the day: the roster's ceiling does
 * (`WPCPM_Sponsor_Roster::locked_today()`). No `orderby` reaches any of the three, so for those sorts
 * the table reads every account the list may hold and orders them itself (`by_value()`), as the
 * Institutions list orders its own. That read is bounded by the program: some thirty sponsors, each
 * with a handful of accounts, tens of accounts on the site. An audience past a thousand accounts gets
 * an indexed meta before it gets a sort by value. The index and the locked accounts are read once a
 * draw and kept here, and so is each account's reading of them.
 *
 * **The search reaches the sponsor's name too**, which no column of an account holds: the accounts
 * WordPress finds by the username, the email or the name, and the accounts stamped with a sponsor
 * whose name in the index holds the term, handed to WordPress as IDs (`include`), as the Institutions
 * list hands it the accounts its search finds by their institution.
 *
 * **No account lists records, not accounts** (`record_views()`): the Approved sponsors with no live
 * account, the worklist (`WPCPM_Sponsors::provision_worklist()`), the ready ones first, each with its
 * contact address masked and, while it cannot be given an account, why. Its bulk action, Create
 * account, is the table's own (`owns_action()`, `handle_action()`) and keeps the rules provisioning
 * keeps (`WPCPM_Sponsors::provision_ticked()`), and a row that is not ready cannot be ticked for it
 * (`row_tickable()`); a ready row's own Create account is a nonce link to the module's provisioning
 * handler, and a row's View page opens the Sponsor Dashboard as that sponsor, as an account row's
 * does (`record_row_actions()`). Above that view's list (`list_intro()`), while it lists a sponsor
 * ready for an account, what a Create account sends, which a list's Apply has no dialog to say;
 * under it (`list_outro()`), the other way a sponsor's account is made.
 *
 * **Manage accounts** is on every row that names a sponsor: every record's, and an account's by the
 * sponsor it is stamped with (`accounts_action()`). It opens that sponsor's accounts, a view of the
 * Accounts tab, where they are attached and removed and the sponsor's posting is switched; a sponsor
 * that cannot be given an account from its contact address is given one there, an existing account
 * attached by its address.
 *
 * No address is printed on the account views: the search covers the email, and no cell prints one.
 * The No account view prints each contact address masked, the one way to tell which address an
 * account would be made for.
 */
class WPCPM_Sponsors_Table extends WPCPM_Accounts_Table {

	/** The record view of the Approved sponsors that have no account yet. */
	const VIEW_NO_ACCOUNT = 'no-account';

	/** That view's bulk action, which the table carries out itself. */
	const ACTION_CREATE = 'create';

	/** What the Sponsor column sorts by: the sponsor as the cell shows it, ordered by the table. */
	const ORDERBY_SPONSOR = 'sponsor';

	/** What the Member since column sorts by: the day the membership began, ordered by the table. */
	const ORDERBY_SINCE = 'since';

	/** What the Status column sorts by: the membership's state as its words run, ordered by the table. */
	const ORDERBY_STATUS = 'status';

	/**
	 * The sponsors index's rows, read once a draw: record ID => row.
	 *
	 * @var array|null
	 */
	private $index;

	/**
	 * The accounts locked out of changes for the rest of today, read once a draw: user ID => the account.
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
	 * Every sponsor account, read once a draw when the search needs it.
	 *
	 * @var WP_User[]|null
	 */
	private $every;

	/**
	 * The No account view's rows, read once a draw: the worklist costs the live accounts and an
	 * account looked up by address for each sponsor with one, and the view's count, its rows, its
	 * sentences and its empty row all read it.
	 *
	 * @var array[]|null
	 */
	private $worklist;

	/**
	 * Where the list stands, read once for every row's links.
	 *
	 * @var array<string, string>|null
	 */
	private $list_state;

	/**
	 * The Sponsor Dashboard's address, read once for every row's View page.
	 *
	 * @var string|null
	 */
	private $dashboard;

	/**
	 * The audience: the Sponsors module's ID.
	 *
	 * @return string
	 */
	protected static function audience() {
		return 'sponsors';
	}

	/**
	 * The role the accounts hold, which the Sponsors module's invitations read here too.
	 *
	 * @return string
	 */
	public static function role() {
		return WPCPM_Roles::ROLE_SPONSOR;
	}

	/**
	 * The columns: the account's name first, the primary column, which Screen Options never offers to
	 * hide, so the row's actions under it cannot be hidden with it.
	 *
	 * @return array<string, string>
	 */
	protected function columns() {
		return array(
			'name'    => __( 'Name', 'wpcredits-program-manager' ),
			'login'   => __( 'Username', 'wpcredits-program-manager' ),
			'sponsor' => __( 'Sponsor', 'wpcredits-program-manager' ),
			'since'   => __( 'Member since', 'wpcredits-program-manager' ),
			'status'  => __( 'Status', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The name sorts by display name and the username by login, as WordPress sorts them; Sponsor,
	 * Member since and Status by the order the table puts the accounts in (`by_value()`).
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array(
			'name'    => 'display_name',
			'login'   => 'login',
			'sponsor' => self::ORDERBY_SPONSOR,
			'since'   => self::ORDERBY_SINCE,
			'status'  => self::ORDERBY_STATUS,
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

		if ( in_array( $sorted_by, array( self::ORDERBY_SPONSOR, self::ORDERBY_SINCE, self::ORDERBY_STATUS ), true ) ) {
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
	 * then the ones stamped with a sponsor whose name in the index holds the term, without regard to
	 * case or accents. Only the index's name, never the status the cell adds after it, and never a
	 * record ID: an account whose sponsor the index no longer holds shows its record ID, which names
	 * nothing.
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
	 * What an account sorts by, read as its cell shows it: the sponsor as the cell names it, '' for
	 * an account stamped with none; the day its membership began, 0 where none is written; or its
	 * status's rank, the order its words run in from A to Z: Active, Active and locked today,
	 * Membership ended, and that ended and locked.
	 *
	 * @param WP_User $user      The account.
	 * @param string  $sorted_by One of the three `ORDERBY_` values the table sorts by itself.
	 * @return string|int
	 */
	private function value_of( WP_User $user, $sorted_by ) {
		$facts = $this->facts_of( $user );

		if ( self::ORDERBY_SPONSOR === $sorted_by ) {
			return $facts['sponsor'];
		}

		if ( self::ORDERBY_SINCE === $sorted_by ) {
			return $facts['since'];
		}

		$rank = $facts['active'] ? 1 : 3;

		return $facts['locked'] ? $rank + 1 : $rank;
	}

	/**
	 * What an account's cells and sorts read, worked out once a draw: the record it is stamped with
	 * (`record`), and that record as the Sponsor cell names it (`sponsor`, `sponsor_label()`); the day
	 * its membership began, 0 where none is written (`since`); whether its membership is live
	 * (`active`); and whether the roster's ceiling locked it today (`locked`).
	 *
	 * Live is the members class's own reading of its flag, 1 while the membership is open, the flag
	 * `WPCPM_Sponsor_Members::live_accounts()` and `sponsor_of()` read; anything else is a membership
	 * that ended, or none ever written on an account given the role by hand.
	 *
	 * @param WP_User $user The account.
	 * @return array{record: string, sponsor: string, since: int, active: bool, locked: bool}
	 */
	private function facts_of( WP_User $user ) {
		$id = (int) $user->ID;

		if ( isset( $this->facts[ $id ] ) ) {
			return $this->facts[ $id ];
		}

		$record     = trim( (string) get_user_meta( $id, WPCPM_Sponsor_Members::META_RECORD_ID, true ) );
		$membership = get_user_meta( $id, WPCPM_Sponsor_Members::META_MEMBERSHIP, true );
		$locked     = $this->locked();

		$this->facts[ $id ] = array(
			'record'  => $record,
			'sponsor' => $this->sponsor_label( $record ),
			'since'   => ( is_array( $membership ) && isset( $membership['since'] ) ) ? (int) $membership['since'] : 0,
			'active'  => 1 === (int) get_user_meta( $id, WPCPM_Sponsor_Members::META_ACTIVE, true ),
			'locked'  => isset( $locked[ $id ] ),
		);

		return $this->facts[ $id ];
	}

	/**
	 * The sponsor an account is stamped with, as the Sponsor cell names it: its name in the index,
	 * trimmed, or the record ID where the index lacks it or holds no name; '' for no stamp. A sponsor
	 * that is not Approved says so after its name, with its status as the index holds it, or "no
	 * status" where it holds none: such a sponsor can keep its accounts, and they can act for it, so
	 * the status is what tells a manager why such an account is on the list.
	 *
	 * @param string $record The record ID the account is stamped with, trimmed.
	 * @return string
	 */
	private function sponsor_label( $record ) {
		if ( '' === $record ) {
			return '';
		}

		$index = $this->index();

		if ( ! isset( $index[ $record ] ) || ! is_array( $index[ $record ] ) ) {
			return $record;
		}

		$row    = $index[ $record ];
		$name   = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		$name   = '' !== $name ? $name : $record;
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';

		if ( WPCPM_Sponsors_Index::STATUS_APPROVED === $status ) {
			return $name;
		}

		$shown = trim( $status );

		return sprintf(
			/* translators: 1: the sponsor's name, 2: its status in the sponsors base, such as Paused, or "no status". */
			__( '%1$s (%2$s)', 'wpcredits-program-manager' ),
			$name,
			'' !== $shown ? $shown : __( 'no status', 'wpcredits-program-manager' )
		);
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
	 * The sponsors index's rows, read once a draw.
	 *
	 * @return array Record ID => row.
	 */
	private function index() {
		if ( null === $this->index ) {
			$this->index = WPCPM_Sponsors_Index::rows();
		}

		return $this->index;
	}

	/**
	 * The accounts the roster's ceiling locked for the rest of today, as the list read them once for
	 * its Status cells: for the notice above the list that names them, so one draw of the Accounts tab
	 * asks the ceiling once (`WPCPM_Sponsors::render_tab_accounts()`).
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

			foreach ( (array) WPCPM_Sponsor_Roster::locked_today() as $user ) {
				if ( $user instanceof WP_User ) {
					$this->locked[ (int) $user->ID ] = $user;
				}
			}
		}

		return $this->locked;
	}

	/**
	 * Every sponsor account, read once a draw, for the search by a sponsor's name.
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
			$this->worklist = WPCPM_Sponsors::provision_worklist();
		}

		return $this->worklist;
	}

	/**
	 * The sentence in the empty row: on the account views, that the list found nobody; on the No
	 * account view, when a search narrowed it to nothing, that it found no sponsor; when it holds
	 * nothing, that every Approved sponsor has an account, or, while the index holds no Approved
	 * sponsor at all, that none is, which would make the first sentence a false one.
	 */
	public function no_items() {
		if ( '' === $this->record_view() ) {
			esc_html_e( 'No sponsor accounts found.', 'wpcredits-program-manager' );

			return;
		}

		if ( ! empty( $this->worklist() ) ) {
			esc_html_e( 'No sponsors found.', 'wpcredits-program-manager' );

			return;
		}

		foreach ( $this->index() as $row ) {
			if ( is_array( $row ) && isset( $row['status'] ) && WPCPM_Sponsors_Index::STATUS_APPROVED === (string) $row['status'] ) {
				esc_html_e( 'Every Approved sponsor has an account.', 'wpcredits-program-manager' );

				return;
			}
		}

		esc_html_e( 'No sponsor is Approved yet, so there is nothing to create.', 'wpcredits-program-manager' );
	}

	/**
	 * Whether a row may be ticked: every account row, and on the No account view a row that is ready.
	 *
	 * Create account is the one bulk action on that view, and a row it can only refuse would turn the
	 * header's tick-all into a notice that names refusals beside every account it made. Core's own
	 * users list leaves the box out of a row the person looking may not act on in the same way.
	 * `WPCPM_Sponsors::provision_ticked()` still matches every ticked record against the view as it is
	 * when the press arrives.
	 *
	 * @param WP_User|array $item The row: an account, or a record on the No account view.
	 * @return bool
	 */
	protected function row_tickable( $item ) {
		return ! is_array( $item ) || ! empty( $item['ready'] );
	}

	/**
	 * The sponsor an account is stamped with, as `sponsor_label()` names it, and nothing for an account
	 * stamped with none; on the No account view, the sponsor's name over its record ID, which a manager
	 * searches the base by.
	 *
	 * @param WP_User|array $item The row: an account, or a record on the No account view.
	 * @return string
	 */
	protected function column_sponsor( $item ) {
		if ( is_array( $item ) ) {
			$id   = ( isset( $item['id'] ) && is_scalar( $item['id'] ) ) ? (string) $item['id'] : '';
			$name = ( isset( $item['name'] ) && is_scalar( $item['name'] ) && '' !== (string) $item['name'] ) ? (string) $item['name'] : $id;

			return sprintf( '<strong>%1$s</strong><br /><code class="wpcpm-inst-record">%2$s</code>', esc_html( $name ), esc_html( $id ) );
		}

		return $item instanceof WP_User ? esc_html( $this->facts_of( $item )['sponsor'] ) : '';
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
	 * Active while the membership is live, Membership ended otherwise, and either followed by "locked
	 * today" for an account the roster's ceiling locked for the rest of the day.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_status( $user ) {
		if ( ! $user instanceof WP_User ) {
			return '';
		}

		$facts = $this->facts_of( $user );
		$label = $facts['active'] ? __( 'Active', 'wpcredits-program-manager' ) : __( 'Membership ended', 'wpcredits-program-manager' );

		if ( $facts['locked'] ) {
			/* translators: %s: the account's membership, "Active" or "Membership ended". */
			$label = sprintf( __( '%s, locked today', 'wpcredits-program-manager' ), $label );
		}

		return esc_html( $label );
	}

	/**
	 * On the No account view, the sponsor's contact address masked, or that it has none.
	 *
	 * @param array $row The record row.
	 * @return string
	 */
	protected function column_contact( $row ) {
		$contact = ( is_array( $row ) && isset( $row['contact'] ) && is_scalar( $row['contact'] ) ) ? (string) $row['contact'] : '';

		return '' !== $contact ? esc_html( $contact ) : esc_html__( 'no email', 'wpcredits-program-manager' );
	}

	/**
	 * On the No account view, whether the sponsor can be given an account: Ready, or Ready with the
	 * account that already uses its address to be attached, or why not, in words that say what to do.
	 *
	 * @param array $row The record row.
	 * @return string
	 */
	protected function column_account( $row ) {
		if ( ! is_array( $row ) ) {
			return '';
		}

		if ( ! empty( $row['ready'] ) ) {
			return empty( $row['existing'] )
				? esc_html__( 'Ready', 'wpcredits-program-manager' )
				: esc_html__( 'Ready: the account that already uses this address will be attached', 'wpcredits-program-manager' );
		}

		$reason = isset( $row['reason'] ) ? (string) $row['reason'] : '';

		if ( 'admin' === $reason ) {
			return esc_html__( 'That address belongs to an administrator, who already reaches every sponsor.', 'wpcredits-program-manager' );
		}

		if ( 'no-email' === $reason ) {
			return esc_html__( 'No Contact Email in Airtable. Add one there and sync.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * A row's actions: the base's Edit, the account's editor, when the person looking may open it;
	 * View page, the Sponsor Dashboard as the sponsor the account acts for, for a live membership while
	 * the page exists; then the base's invitation; then Manage accounts, the accounts of the sponsor the
	 * account is stamped with, and nothing for an account that holds no sponsor's stamp.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		$facts   = $this->facts_of( $user );
		$actions = $this->edit_row_action( $user );

		if ( $facts['active'] ) {
			$actions = array_merge( $actions, $this->view_action( $facts['record'] ) );
		}

		return array_merge( $actions, parent::row_actions_for( $user ), $this->accounts_action( $facts['record'] ) );
	}

	/**
	 * A No account row's actions: Create account, for a sponsor ready for one; then View page, the
	 * Sponsor Dashboard as that sponsor, the address an account row's View page opens, while the page
	 * exists; then Manage accounts, on every row, since a sponsor that cannot be given an account from
	 * its contact address is given one there, an existing account attached by its address. A manager's
	 * dashboard opens as any sponsor the index holds, whatever its accounts.
	 *
	 * Create account is a nonce link to the module's provisioning handler, the record ID with its case,
	 * the tab and where the list stands, so the handler comes back to the view as it stood, under the
	 * nonce that handler has always checked, keyed to the sponsor, so a link taken from one row is no
	 * use on another. The table is one form, so a row's action cannot be a form of its own.
	 *
	 * @param array $row The record row.
	 * @return array<string, string> `create`, `view` and `accounts` => the links, in that order: no
	 *                               `create` for a row that is not ready, no `view` while the page is
	 *                               missing.
	 */
	protected function record_row_actions( array $row ) {
		$record = ( isset( $row['id'] ) && is_scalar( $row['id'] ) ) ? (string) $row['id'] : '';
		$links  = $this->view_action( $record ) + $this->accounts_action( $record );

		if ( ! self::is_record( $record ) || empty( $row['ready'] ) ) {
			return $links;
		}

		$action = WPCPM_Sponsors::ACTION_PROVISION;
		$url    = wp_nonce_url(
			add_query_arg(
				// The link's own arguments win over the list's state, which only adds to them.
				array(
					'action'                    => $action,
					WPCPM_Sponsors::ARG_SPONSOR => rawurlencode( $record ),
					self::TAB_FIELD             => self::TAB,
				) + $this->list_state(),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $record
		);

		return array( 'create' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Create account', 'wpcredits-program-manager' ) ) ) + $links;
	}

	/**
	 * View page, as action => link: the Sponsor Dashboard as one sponsor, through the switcher's own
	 * argument (`WPCPM_Sponsor_Roster::ARG_VIEW`), the address the Sponsors card's name opens; nothing
	 * while the page is missing, or for a value that is no sponsor's identifier, which names no
	 * sponsor to show. The page's address is read once for the table (`dashboard()`), not once a row.
	 *
	 * One address for both kinds of row: an account's sponsor and a No account row's record.
	 *
	 * @param string $record The sponsor's record ID, with its case.
	 * @return array<string, string> `view` => the link, or nothing.
	 */
	private function view_action( $record ) {
		if ( ! self::is_record( $record ) ) {
			return array();
		}

		$page = $this->dashboard();

		if ( '' === $page ) {
			return array();
		}

		return array( 'view' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( WPCPM_Sponsor_Roster::ARG_VIEW, $record, $page ) ), esc_html__( 'View page', 'wpcredits-program-manager' ) ) );
	}

	/**
	 * Manage accounts, as action => link: one sponsor's accounts, a view of the Accounts tab, with
	 * where the list stands, so the view's way back returns to the list as it stood; nothing for a
	 * value that is no sponsor's identifier, which names no sponsor to open.
	 *
	 * One address for both kinds of row: an account's sponsor and a No account row's record. It is the
	 * address `WPCPM_Sponsors::accounts_view_url()` builds, the Accounts tab with the record, and the
	 * list's state after it.
	 *
	 * @param string $record The sponsor's record ID, with its case.
	 * @return array<string, string> `accounts` => the link, or nothing.
	 */
	private function accounts_action( $record ) {
		if ( ! self::is_record( $record ) ) {
			return array();
		}

		// The record wins over the list's state, which only adds to it.
		$url = $this->page_url( array( WPCPM_Sponsors::ARG_SPONSOR => rawurlencode( $record ) ) + $this->list_state() );

		return array( 'accounts' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Manage accounts', 'wpcredits-program-manager' ) ) );
	}

	/**
	 * The record view: No account, the Approved sponsors with no account yet, its label picked by its
	 * count by the base.
	 *
	 * @return array<string, array> View => its label, as `_n_noop()` returns it.
	 */
	protected function record_views() {
		return array(
			/* translators: %s: how many sponsors, in parentheses. */
			self::VIEW_NO_ACCOUNT => _n_noop( 'No account %s', 'No account %s', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * How many sponsors the No account view holds: the whole worklist, not the search.
	 *
	 * @param string $view A record view.
	 * @return int
	 */
	protected function count_records( $view ) {
		return self::VIEW_NO_ACCOUNT === $view ? count( $this->worklist() ) : 0;
	}

	/**
	 * The No account view's rows: the worklist, the ready ones first and then by name, searched by
	 * the sponsor's name, or ordered by it from A to Z or Z to A when the list is sorted by the
	 * Sponsor column, the record ID settling two of one name.
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
	 * The No account view's columns: the sponsor, its contact address masked, and whether it can be
	 * given an account.
	 *
	 * @return array<string, string>
	 */
	protected function record_columns() {
		return array(
			'sponsor' => __( 'Sponsor', 'wpcredits-program-manager' ),
			'contact' => __( 'Contact', 'wpcredits-program-manager' ),
			'account' => __( 'Account', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The No account view sorts by the sponsor's name, which the table orders itself (`record_rows()`).
	 *
	 * @return array<string, array>
	 */
	protected function record_sortable() {
		return array( 'sponsor' => array( self::ORDERBY_SPONSOR, false ) );
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
	 * Create the accounts of the ticked sponsors, by the rules provisioning keeps
	 * (`WPCPM_Sponsors::provision_ticked()`), and say what came of it.
	 *
	 * Reachable from whichever view the list was on, so it reads the ticked records alone, under
	 * `records[]`, keeping each only when the whole of it is a sponsor's identifier, with its case
	 * (`record_pattern()`): an account's ID ticked on an account view is none of them.
	 *
	 * @param string $action The bulk action pressed.
	 * @return array{0: string, 1: array} The outcome and its detail.
	 */
	public function handle_action( $action ) {
		if ( self::ACTION_CREATE !== $action ) {
			return array( '', array() );
		}

		return WPCPM_Sponsors::provision_ticked( WPCPM_Request::list( self::RECORDS_FIELD, self::record_pattern() ) );
	}

	/**
	 * What a sponsor's identifier looks like: the shape of the key the sponsors index keys its rows
	 * by, an Airtable record ID.
	 *
	 * The one place the table matches a sponsor's identifier, the ticked records read back from the
	 * list's form and the record a row's links name alike (`is_record()`), so a change to what
	 * identifies a sponsor is an edit here.
	 *
	 * @return string A regular expression.
	 */
	private static function record_pattern() {
		return WPCPM_Airtable::RECORD_ID_PATTERN;
	}

	/**
	 * Whether a value is, whole, a sponsor's identifier (`record_pattern()`), with its case.
	 *
	 * The whole of it: a `$` without the `D` modifier matches before a trailing newline too.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private static function is_record( $value ) {
		return is_string( $value ) && 1 === preg_match( self::record_pattern(), $value, $matched ) && $matched[0] === $value;
	}

	/**
	 * The words for what a Create account did, on the ticked sponsors or on one row, from the detail
	 * `WPCPM_Sponsors::provision_ticked()` returned, or the reason a row's press carries.
	 *
	 * On the ticked sponsors: how many accounts it created, and how many accounts it attached that
	 * already used a sponsor's address; of those, how many Airtable could not be told about; when it
	 * left some of the ticked for the next press, since one press creates at most
	 * `WPCPM_Sponsors::PROVISION_LIMIT`, that it did; and, when it could not give some sponsors an
	 * account, which, each with why, the first few named and the rest counted. On one row: why that
	 * sponsor's account could not be made, or why the account at its address cannot act for it. Every
	 * other outcome, and any of these with nothing to say, keeps the screen's own sentence.
	 *
	 * @param string $status The outcome `handle_action()` returned, or a row's press left.
	 * @param array  $detail What came with it: `created`, `attached`, `airtable`, `left`, `limit`,
	 *                       `failed` and `failed_more` from the ticked sponsors; `reason` from a row.
	 * @return string The sentence, as text; '' for the screen's.
	 */
	public static function action_sentence( $status, array $detail ) {
		$reason = ( isset( $detail['reason'] ) && is_scalar( $detail['reason'] ) ) ? trim( (string) $detail['reason'] ) : '';

		if ( 'provision-refused' === $status ) {
			return '' === $reason ? '' : sprintf(
				/* translators: %s: why the account at the sponsor's address cannot act for it. */
				__( 'That account cannot act for this sponsor: %s', 'wpcredits-program-manager' ),
				$reason
			);
		}

		if ( 'provision-failed' === $status && '' !== $reason ) {
			return sprintf(
				/* translators: %s: why the account could not be created, as WordPress said it. */
				__( 'The account could not be created: %s', 'wpcredits-program-manager' ),
				$reason
			);
		}

		if ( ! in_array( $status, array( 'provisioned', 'provision-failed' ), true ) ) {
			return '';
		}

		$created   = isset( $detail['created'] ) ? (int) $detail['created'] : 0;
		$attached  = isset( $detail['attached'] ) ? (int) $detail['attached'] : 0;
		$airtable  = isset( $detail['airtable'] ) ? (int) $detail['airtable'] : 0;
		$left      = isset( $detail['left'] ) ? (int) $detail['left'] : 0;
		$limit     = isset( $detail['limit'] ) ? (int) $detail['limit'] : 0;
		$sentences = array();

		if ( $created > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: how many accounts were created. */
				_n( '%s account was created and its invitation queued.', '%s accounts were created, each with its invitation queued.', $created, 'wpcredits-program-manager' ),
				number_format_i18n( $created )
			);
		}

		if ( $attached > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: how many accounts that already used a sponsor's address were attached to it. */
				_n( '%s existing account was attached.', '%s existing accounts were attached.', $attached, 'wpcredits-program-manager' ),
				number_format_i18n( $attached )
			);
		}

		if ( $airtable > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: how many of the accounts created or attached Airtable could not be told about. */
				_n( 'Airtable could not be told about %s of them: its Dashboard account checkbox is out of step until the next attempt.', 'Airtable could not be told about %s of them: its Dashboard account checkbox is out of step until the next attempt.', $airtable, 'wpcredits-program-manager' ),
				number_format_i18n( $airtable )
			);
		}

		if ( $left > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: how many accounts one press creates at most. */
				_n( 'One press creates at most %s; tick the rest and press again.', 'One press creates at most %s; tick the rest and press again.', $limit, 'wpcredits-program-manager' ),
				number_format_i18n( $limit )
			);
		}

		if ( 'provision-failed' === $status ) {
			list( $failed, $not_made ) = self::names_in( $detail, 'failed', 'failed_more' );

			if ( $not_made > 0 ) {
				$sentences[] = sprintf(
					/* translators: %s: the sponsors, each with why it was not given an account, comma-separated. */
					__( 'Not created: %s.', 'wpcredits-program-manager' ),
					implode( ', ', $failed )
				);
			}
		}

		return implode( ' ', $sentences );
	}

	/**
	 * One list of sponsors from a press's detail, as the sentence prints it: the entries, then "and N
	 * more" where the detail counts more; and how many sponsors that is in all.
	 *
	 * @param array  $detail   The press's detail.
	 * @param string $key      The key holding the entries.
	 * @param string $more_key The key holding how many more there are.
	 * @return array{0: string[], 1: int} The list as printed, and how many sponsors it stands for.
	 */
	private static function names_in( array $detail, $key, $more_key ) {
		$names = ( isset( $detail[ $key ] ) && is_array( $detail[ $key ] ) ) ? array_map( 'strval', array_values( array_filter( $detail[ $key ], 'is_scalar' ) ) ) : array();
		$more  = ( ! empty( $names ) && isset( $detail[ $more_key ] ) ) ? max( 0, (int) $detail[ $more_key ] ) : 0;
		$total = count( $names ) + $more;

		if ( $more > 0 ) {
			$names[] = sprintf(
				/* translators: %s: how many more sponsors there are. */
				_n( 'and %s more', 'and %s more', $more, 'wpcredits-program-manager' ),
				number_format_i18n( $more )
			);
		}

		return array( $names, $total );
	}

	/**
	 * Above the views, on the No account view, while the view holds a sponsor ready for an account,
	 * whether or not a search shows it: what a Create account sends.
	 *
	 * Above the views, the first part of the list the screen draws, so it is read before the list and
	 * its search. It is said in words because the list's Apply has no dialog to ask with, and an
	 * invitation cannot be recalled; over a view that holds nothing to create, it would warn of
	 * nothing. The view's worklist decides it, not the rows drawn: a search narrows the rows, not
	 * what the view holds.
	 */
	protected function list_intro() {
		if ( self::VIEW_NO_ACCOUNT !== $this->record_view() ) {
			return;
		}

		if ( in_array( true, array_column( $this->worklist(), 'ready' ), true ) ) {
			echo '<p>' . esc_html__( 'Creating an account emails a password-set link to the sponsor\'s contact address. An invitation cannot be recalled once sent.', 'wpcredits-program-manager' ) . '</p>';
		}
	}

	/**
	 * Under the table, on the No account view: the other way a sponsor's account is made, so a manager
	 * who creates nothing here knows where else accounts come from.
	 */
	protected function list_outro() {
		if ( self::VIEW_NO_ACCOUNT !== $this->record_view() ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'An approved application creates its sponsor\'s account. For any other Approved sponsor, this view is the way one is made.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Where the list stands, which a row's links carry, so the press comes back to the same view,
	 * search, sort and page: the Sponsors screen's reading of it, the accounts screen's own, which a
	 * press on the ticked rows comes back through (`WPCPM_Sponsors::list_state()`).
	 *
	 * @return array<string, string>
	 */
	protected function list_state() {
		if ( null === $this->list_state ) {
			$this->list_state = WPCPM_Sponsors::list_state();
		}

		return $this->list_state;
	}

	/**
	 * The Sponsor Dashboard's address, or '' while the page is missing, read once for the table.
	 *
	 * Guarded, as the module's own reading of it is (`WPCPM_Sponsors::dashboard_url()`), so the list
	 * draws with or without the front end.
	 *
	 * @return string
	 */
	private function dashboard() {
		if ( null === $this->dashboard ) {
			$this->dashboard = class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ? (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ) : '';
		}

		return $this->dashboard;
	}
}
