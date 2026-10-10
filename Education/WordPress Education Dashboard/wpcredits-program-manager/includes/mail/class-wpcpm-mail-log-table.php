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
	 * The address, and the person's name at the time of sending, linked to the account while it
	 * exists.
	 *
	 * @param array $row The row.
	 */
	protected function column_to( $row ) {
		echo esc_html( $row['to_email'] );

		if ( '' !== (string) $row['to_name'] ) {
			$link = ! empty( $row['user_id'] ) && get_user_by( 'id', (int) $row['user_id'] ) ? get_edit_user_link( (int) $row['user_id'] ) : '';

			echo '<br />';
			echo $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $row['to_name'] ) . '</a>' : esc_html( $row['to_name'] );
		}
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
