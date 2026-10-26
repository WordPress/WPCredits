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
	 * The address, and the person's name at the time of sending, linked to the account's row on its
	 * audience's screen where Resend invite lives, while the account exists, or to the user editor
	 * when it belongs to no program audience.
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
	 * The link to the person's row on their accounts screen, where Resend invite lives, or to the
	 * user editor when the account belongs to no program audience, and empty while no account is
	 * attached.
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

		$audience = '';
		$type     = isset( $row['recipient_type'] ) ? $row['recipient_type'] : '';

		switch ( $type ) {
			case WPCPM_Mail_Catalog::TYPE_STUDENT:
				$audience = 'students';
				break;
			case WPCPM_Mail_Catalog::TYPE_MENTOR:
				$audience = 'mentors';
				break;
			case WPCPM_Mail_Catalog::TYPE_INSTITUTION:
				$audience = 'institutions';
				break;
			case WPCPM_Mail_Catalog::TYPE_SPONSOR:
				$audience = 'sponsors';
				break;
			case WPCPM_Mail_Catalog::TYPE_ADMIN:
				$audience = 'administrators';
				break;
		}

		if ( '' === $audience && isset( $user->roles ) ) {
			$roles = (array) $user->roles;

			if ( in_array( WPCPM_Roles::ROLE_STUDENT, $roles, true ) ) {
				$audience = 'students';
			} elseif ( in_array( WPCPM_Roles::ROLE_MENTOR, $roles, true ) ) {
				$audience = 'mentors';
			} elseif ( in_array( WPCPM_Roles::ROLE_INSTITUTION, $roles, true ) ) {
				$audience = 'institutions';
			} elseif ( in_array( WPCPM_Roles::ROLE_SPONSOR, $roles, true ) ) {
				$audience = 'sponsors';
			} elseif ( in_array( WPCPM_Roles::ROLE_ADMIN, $roles, true ) ) {
				$audience = 'administrators';
			}
		}

		if ( '' === $audience ) {
			return (string) get_edit_user_link( (int) $user->ID );
		}

		$search = ! empty( $user->user_login ) ? $user->user_login : ( ! empty( $user->display_name ) ? $user->display_name : '' );

		$args = array(
			'page' => 'wpcpm-' . $audience,
			'tab'  => 'accounts',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

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
