<?php
/**
 * The Administrators screen's list of administrator accounts.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every administrator account as a WordPress list table: the name, the username and whether the
 * account holds the program's capability, searched, sorted and paged.
 *
 * **Nothing to invite, so nothing to tick.** Administrators hold WordPress's own role, which the
 * plugin never invites or syncs, so this list leaves out what the other audiences' lists draw for
 * their invitations: the views by invitation state (All is the one view), the bulk actions and the
 * checkbox column they are pressed with, and a row's invitation (Edit is a row's one action).
 */
class WPCPM_Administrators_Table extends WPCPM_Accounts_Table {

	/**
	 * The audience: the Administrators module's ID.
	 *
	 * @return string
	 */
	protected static function audience() {
		return 'administrators';
	}

	/**
	 * The role the accounts hold: WordPress's own Administrator role, which program managers use.
	 *
	 * @return string
	 */
	public static function role() {
		return WPCPM_Roles::ROLE_ADMIN;
	}

	/**
	 * The columns, as the screen has always listed them: the name first, the primary column, which
	 * Screen Options never offers to hide, so the row's actions under it cannot be hidden with it.
	 *
	 * @return array<string, string>
	 */
	protected function columns() {
		return array(
			'name'   => __( 'Name', 'wpcredits-program-manager' ),
			'login'  => __( 'Username', 'wpcredits-program-manager' ),
			'manage' => __( 'Can manage program', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The audience's columns alone, their labels escaped: no checkbox before them, since the list
	 * offers no bulk action to press on ticked accounts (`get_bulk_actions()`).
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array_map( 'esc_html', $this->columns() );
	}

	/**
	 * The name sorts by display name and the username by login, as WordPress sorts them.
	 *
	 * @return array<string, string>
	 */
	protected function sortable_map() {
		return array(
			'name'  => 'display_name',
			'login' => 'login',
		);
	}

	/**
	 * The accounts a set of `WP_User_Query` arguments finds.
	 *
	 * The role, the search and the order are the base's, and WordPress answers them as they come:
	 * the search covers the name, the username and the email, all this list searches. There is no
	 * view to narrow by (`current_view()`).
	 *
	 * @param array $args `WP_User_Query` arguments.
	 * @return array{items: WP_User[], total: int}
	 */
	protected function query( array $args ) {
		$query = new WP_User_Query( $args );

		return array(
			'items' => (array) $query->get_results(),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * The one view, All, whatever the request names: the others are invitation states, which
	 * administrators do not have.
	 *
	 * The base reads the view a request names into the list's query and its form, so an address
	 * naming Never invited would narrow this list by an invitation stamp, and keep it narrowed.
	 *
	 * @return string
	 */
	protected function current_view() {
		return 'all';
	}

	/**
	 * The one view, All, with how many administrator accounts there are, marked as the view shown, in
	 * the base's one view markup (`view_link()`).
	 *
	 * The whole list, as WordPress's own views count, not the search: one count through the query.
	 *
	 * @return array<string, string> View => link.
	 */
	protected function get_views() {
		$found = $this->query(
			array(
				'role'        => static::role(),
				'number'      => 1,
				'fields'      => 'ID',
				'count_total' => true,
			)
		);
		$count = isset( $found['total'] ) ? (int) $found['total'] : 0;

		/* translators: %s: how many accounts, in parentheses. */
		$label = _n( 'All %s', 'All %s', $count, 'wpcredits-program-manager' );

		return array( 'all' => $this->view_link( 'all', $label, $count, true ) );
	}

	/**
	 * No bulk actions: administrators are never invited by the plugin, and nothing else is pressed
	 * on ticked accounts. Core prints no bulk select for a list with none.
	 *
	 * @return array<string, string> Action => label.
	 */
	public function get_bulk_actions() {
		return array();
	}

	/**
	 * No action is ever pressed in this list, whatever the request names: it offers none.
	 *
	 * Core reads the action a request names whether its list offers it or not, and the accounts
	 * screen every audience shares takes Send invite and Resend invite from there
	 * (`WPCPM_Accounts_Screen::handle_list_form()`), so an address naming one, with the nonce the
	 * list's form carries, would queue invitations to administrators. Read as none, a request sent
	 * from the form still comes back to the list, as a search does.
	 *
	 * @return false
	 */
	public function current_action() {
		return false;
	}

	/**
	 * Whether the account holds the program's capability: Yes, or No with what to do about it, as the
	 * screen has always said it. Activation grants the capability to the Administrator role, so
	 * re-activating the plugin grants it again.
	 *
	 * @param WP_User $user The row's account.
	 * @return string
	 */
	protected function column_manage( $user ) {
		return user_can( $user->ID, WPCPM_Roles::CAP_MANAGE )
			? esc_html__( 'Yes', 'wpcredits-program-manager' )
			: esc_html__( 'No - re-activate the plugin', 'wpcredits-program-manager' );
	}

	/**
	 * A row's actions: the base's Edit alone, the account's editor, when the person looking may open
	 * it. No invitation: administrators are never invited by the plugin.
	 *
	 * @param WP_User $user The row's account.
	 * @return array<string, string>
	 */
	protected function row_actions_for( WP_User $user ) {
		return $this->edit_row_action( $user );
	}

	/**
	 * The sentence in the empty row: no administrators found, as the screen has always said it, and
	 * for a search, that it found no administrator account. No word about a sync: administrators do
	 * not arrive by one.
	 */
	public function no_items() {
		if ( '' !== $this->search_clause() ) {
			esc_html_e( 'No administrator accounts found.', 'wpcredits-program-manager' );

			return;
		}

		esc_html_e( 'No administrators found.', 'wpcredits-program-manager' );
	}
}
