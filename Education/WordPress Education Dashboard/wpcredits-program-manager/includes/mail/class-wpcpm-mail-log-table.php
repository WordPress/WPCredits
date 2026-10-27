<?php
/**
 * The Emails screen's Log as a WordPress list table.
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
 * Newest first, 50 a page, no sorting: the question it answers is "did this email go out", which
 * the newest-first order and the filters answer. Required by the Emails tool on its own screen
 * only, since it extends core's list table, which wp-admin loads after the plugins.
 */
class WPCPM_Mail_Log_Table extends WP_List_Table {

	/**
	 * The filters, as `WPCPM_Mail_Log::find()` reads them.
	 *
	 * @var array
	 */
	private $filters;

	/**
	 * The table, for one set of filters.
	 *
	 * @param array $filters The Log's filters (`WPCPM_Emails::filters_from_request()`).
	 */
	public function __construct( array $filters ) {
		$this->filters = $filters;

		parent::__construct(
			array(
				'singular' => 'wpcpm-email',
				'plural'   => 'wpcpm-emails',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The columns. The Area column's key is the table's `module`, the code's name for an area.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'when'    => __( 'When', 'wpcredits-program-manager' ),
			'to'      => __( 'To', 'wpcredits-program-manager' ),
			'type'    => __( 'Recipient type', 'wpcredits-program-manager' ),
			'module'  => __( 'Area', 'wpcredits-program-manager' ),
			'email'   => __( 'Email', 'wpcredits-program-manager' ),
			'subject' => __( 'Subject', 'wpcredits-program-manager' ),
			'status'  => __( 'Status', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * One page of rows for the filters, and the pagination's numbers.
	 */
	public function prepare_items() {
		$found = WPCPM_Mail_Log::find( $this->filters, $this->get_pagenum(), WPCPM_Mail_Log::PER_PAGE );

		$this->items = $found['rows'];

		// One query for the accounts this page names, so that column_to() finds each in the cache
		// instead of asking the database once per row.
		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', array_column( $found['rows'], 'user_id' ) ) ) ) );

		if ( $user_ids && function_exists( 'cache_users' ) ) {
			cache_users( $user_ids );
		}

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $found['total'],
				'per_page'    => WPCPM_Mail_Log::PER_PAGE,
			)
		);
	}

	/**
	 * What the table says when the filters match nothing.
	 */
	public function no_items() {
		esc_html_e( 'No email matches.', 'wpcredits-program-manager' );
	}

	/**
	 * When it was sent, stored in UTC, shown in the site's time zone.
	 *
	 * @param array $row The row.
	 */
	protected function column_when( $row ) {
		echo esc_html( wp_date( 'j M Y, H:i', (int) strtotime( $row['sent_at'] . ' UTC' ) ) );
	}

	/**
	 * The address, and the person's name at the time of sending, linked to the person's row on the
	 * Accounts tab of the screen for the role the account holds now, where the person's actions are
	 * (Send invite and Resend invite on every screen but the Administrators', whose accounts are never
	 * invited), while the account exists, or to the user editor when the account holds no program role.
	 *
	 * @param array $row The row.
	 */
	protected function column_to( $row ) {
		echo esc_html( $row['to_email'] );

		if ( '' !== (string) $row['to_name'] ) {
			$link = $this->account_link( $row );

			echo '<br />';
			echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $row['to_name'] ) . '</a>' : esc_html( $row['to_name'] );
		}
	}

	/**
	 * The link behind the person's name: empty while no account is attached or the account is gone,
	 * the person's row on the Accounts tab of their audience's screen where the account holds a
	 * program role, and the user editor where it holds none.
	 *
	 * **The screen is the role the account holds now, not the type the row was recorded under.** A
	 * row keeps the type the recipient had when the email went out (`WPCPM_Mail_Capture::type_of()`
	 * wrote it), so a student who has since become a mentor, and holds the student role no longer,
	 * has rows recorded as Student; the Students screen's Accounts tab no longer lists that person,
	 * so a link there would land on an empty search. The link asks the same method for the type again,
	 * with the recorded one as the audience to keep: it answers the recorded type while the account
	 * still holds that role, else the first role it holds (Student, Mentor, Institution, Sponsor,
	 * then Administrator, which the method also answers for an account that holds only the program's
	 * capability), else Other, which has no screen and falls back to the user editor.
	 *
	 * **The Administrators screen is for the Administrator role alone.** Its Accounts tab lists the
	 * accounts that hold the role, and shows whether each holds the program's capability, so an
	 * account that holds the capability and not the role is not on it: that account goes to its user
	 * editor, where its roles and capabilities are.
	 *
	 * **The search is the account's current email address.** An address belongs to one account, so
	 * the person is on the first page of the result, which a username does not promise: the Accounts
	 * tab matches a term anywhere in the username, the email or the name (the Students tab also in the
	 * institution, the Institutions and Sponsors tabs also in the name of the organization), so a
	 * username of a few letters can be held, or contained, by more accounts than a page lists. An
	 * address is matched as a term too, so an account whose address contains this one, such as the
	 * same name with a letter in front, is listed with it, but that is a rare extra row, far from
	 * filling a page. It is the address the account holds now and not the one the row was sent to,
	 * which the person may have changed since. It is encoded whole (`rawurlencode()`), because
	 * `add_query_arg()` sets a value as it is given and an address can hold a plus, which would be
	 * read as a space.
	 *
	 * The anchor is the accounts table's `LIST_ANCHOR`, written out here because this screen does
	 * not load that table (`wpcpm_load_accounts_tables()` is the Students, Mentors, Institutions,
	 * Sponsors and Administrators screens', and loading six classes for an anchor would cost the Log
	 * a require it never otherwise makes); bin/test-emails-screen.php holds the two equal.
	 *
	 * @param array $row The row.
	 * @return string
	 */
	protected function account_link( array $row ) {
		if ( empty( $row['user_id'] ) ) {
			return '';
		}

		$user = get_user_by( 'id', (int) $row['user_id'] );

		if ( ! $user ) {
			return '';
		}

		$screens = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => 'wpcpm-students',
			WPCPM_Mail_Catalog::TYPE_MENTOR      => 'wpcpm-mentors',
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => 'wpcpm-institutions',
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => 'wpcpm-sponsors',
			WPCPM_Mail_Catalog::TYPE_ADMIN       => 'wpcpm-administrators',
		);

		$type = WPCPM_Mail_Capture::type_of( $user, isset( $row['recipient_type'] ) ? (string) $row['recipient_type'] : '' );

		if ( ! isset( $screens[ $type ] ) || ( WPCPM_Mail_Catalog::TYPE_ADMIN === $type && ! in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true ) ) ) {
			return (string) get_edit_user_link( (int) $user->ID );
		}

		$args = array(
			'page' => $screens[ $type ],
			'tab'  => 'accounts',
			's'    => rawurlencode( (string) $user->user_email ),
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) ) . '#wpcpm-accounts-list';
	}

	/**
	 * The recipient type, as the account said at the time of sending.
	 *
	 * @param array $row The row.
	 */
	protected function column_type( $row ) {
		$types = WPCPM_Mail_Catalog::types();

		echo esc_html( isset( $types[ $row['recipient_type'] ] ) ? $types[ $row['recipient_type'] ] : __( 'Not recorded', 'wpcredits-program-manager' ) );
	}

	/**
	 * The area that sent it.
	 *
	 * @param array $row The row.
	 */
	protected function column_module( $row ) {
		$areas = WPCPM_Mail_Catalog::modules();

		echo esc_html( isset( $areas[ $row['module'] ] ) ? $areas[ $row['module'] ] : $areas[ WPCPM_Mail_Catalog::MODULE_OTHER ] );
	}

	/**
	 * Which email it was.
	 *
	 * @param array $row The row.
	 */
	protected function column_email( $row ) {
		echo esc_html( WPCPM_Mail_Catalog::label( $row['template'] ) );
	}

	/**
	 * Its subject, as sent; none was kept for the entries moved in from the old option.
	 *
	 * @param array $row The row.
	 */
	protected function column_subject( $row ) {
		echo '' === (string) $row['subject'] ? '<span class="description">' . esc_html__( 'Not recorded', 'wpcredits-program-manager' ) . '</span>' : esc_html( $row['subject'] );
	}

	/**
	 * Its status, the Test mark, and WordPress's message for a failure.
	 *
	 * @param array $row The row.
	 */
	protected function column_status( $row ) {
		echo esc_html( WPCPM_Mail_Log::status_label( $row['status'] ) );

		if ( ! empty( $row['is_test'] ) ) {
			echo ' <span class="wpcpm-emails__test">' . esc_html__( 'Test', 'wpcredits-program-manager' ) . '</span>';
		}

		if ( '' !== (string) $row['error'] ) {
			echo '<br /><span class="description">' . esc_html( $row['error'] ) . '</span>';
		}
	}
}
