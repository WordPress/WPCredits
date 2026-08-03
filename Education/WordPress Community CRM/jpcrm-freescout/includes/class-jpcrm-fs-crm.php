<?php
/**
 * Jetpack CRM integration points.
 *
 * This is where the plugin stops being "a FreeScout viewer" and becomes part of
 * the CRM: a tab on the contact record, activity-log entries, an external source,
 * and a contact action.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRM hooks.
 */
class JPCRM_FS_CRM {

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Register FreeScout as a CRM external source.
		add_filter( 'zbs_approved_sources', array( $this, 'register_external_source' ) );
		add_filter( 'zbs_external_source_infobox_line', array( $this, 'external_source_infobox' ), 10, 2 );

		// Register our activity-log types so entries render with a label and icon.
		add_filter( 'zbs_logtype_array', array( $this, 'register_log_types' ) );

		// The support-tickets tab on the contact record.
		add_filter( 'jetpack-crm-contact-vital-tabs', array( $this, 'append_contact_tab' ), 20, 3 );

		// "New support ticket" contact action.
		add_filter( 'zbs_contact_actions_array', array( $this, 'append_contact_action' ), 10, 2 );
	}

	/**
	 * Register `freescout` as an external source.
	 *
	 * @param array $sources Approved sources.
	 * @return array
	 */
	public function register_external_source( $sources ) {

		if ( ! is_array( $sources ) ) {
			$sources = array();
		}

		$sources[ JPCRM_FS_Contacts::SOURCE ] = array(
			'FreeScout',
			'ico' => 'fa-life-ring',
		);

		return $sources;
	}

	/**
	 * Label FreeScout-sourced contacts in the external source infobox.
	 *
	 * @param string $line   Existing line.
	 * @param array  $source External source details.
	 * @return string
	 */
	public function external_source_infobox( $line, $source ) {

		if ( ! is_array( $source ) || ! isset( $source['source'] ) ) {
			return $line;
		}

		if ( $source['source'] !== JPCRM_FS_Contacts::SOURCE ) {
			return $line;
		}

		return __( 'Created from a FreeScout help desk conversation', 'jpcrm-freescout' );
	}

	/**
	 * Activity-log types this plugin writes.
	 *
	 * @return array
	 */
	public static function log_types() {

		return array(
			'freescout_ticket_created'  => array(
				'locked' => true,
				'label'  => __( 'Support Ticket Opened', 'jpcrm-freescout' ),
				'ico'    => 'fa-life-ring',
			),
			'freescout_customer_reply'  => array(
				'locked' => true,
				'label'  => __( 'Support: Customer Replied', 'jpcrm-freescout' ),
				'ico'    => 'fa-comment-o',
			),
			'freescout_agent_reply'     => array(
				'locked' => true,
				'label'  => __( 'Support: Agent Replied', 'jpcrm-freescout' ),
				'ico'    => 'fa-reply',
			),
			'freescout_note_added'      => array(
				'locked' => true,
				'label'  => __( 'Support: Internal Note', 'jpcrm-freescout' ),
				'ico'    => 'fa-sticky-note-o',
			),
			'freescout_status_changed'  => array(
				'locked' => true,
				'label'  => __( 'Support Ticket Status Changed', 'jpcrm-freescout' ),
				'ico'    => 'fa-random',
			),
		);
	}

	/**
	 * Merge our log types into the CRM's registry.
	 *
	 * @param array $log_types Existing log types, keyed by object type.
	 * @return array
	 */
	public function register_log_types( $log_types ) {

		if ( ! is_array( $log_types ) ) {
			return $log_types;
		}

		if ( ! isset( $log_types['zerobs_customer'] ) || ! is_array( $log_types['zerobs_customer'] ) ) {
			$log_types['zerobs_customer'] = array();
		}

		$log_types['zerobs_customer'] = array_merge( $log_types['zerobs_customer'], self::log_types() );

		return $log_types;
	}

	/**
	 * Add the "Support Tickets" tab to the contact record.
	 *
	 * @param array $tabs    Existing tabs.
	 * @param int   $id      Contact ID.
	 * @param array $contact Contact array (may be absent on older CRM versions).
	 * @return array
	 */
	public function append_contact_tab( $tabs, $id, $contact = array() ) {

		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		if ( ! jpcrm_fs()->is_configured() ) {
			return $tabs;
		}

		$email = '';

		if ( is_array( $contact ) && ! empty( $contact['email'] ) ) {
			$email = $contact['email'];
		} elseif ( function_exists( 'zeroBS_customerEmail' ) ) {
			$email = zeroBS_customerEmail( $id );
		}

		$email = sanitize_email( (string) $email );

		if ( $email === '' ) {
			return $tabs;
		}

		$tabs[] = array(
			'id'      => 'jpcrm-freescout-tab',
			'name'    => __( 'Support Tickets', 'jpcrm-freescout' ),
			'content' => $this->render_contact_tab( $email, absint( $id ) ),
		);

		return $tabs;
	}

	/**
	 * Render the contents of the support-tickets tab.
	 *
	 * @param string $email      Contact email address.
	 * @param int    $contact_id Contact ID.
	 * @return string HTML.
	 */
	private function render_contact_tab( $email, $contact_id ) {

		$conversations = jpcrm_fs()->api->get_conversations(
			array(
				'customerEmail' => $email,
				'status'        => 'all',
				'pageSize'      => 20,
			)
		);

		if ( is_wp_error( $conversations ) ) {
			return '<div class="ui warning message jpcrm-fs-message">' . esc_html( $conversations->get_error_message() ) . '</div>';
		}

		if ( empty( $conversations ) ) {
			return '<p class="jpcrm-fs-empty">' . esc_html__( 'No support tickets for this contact.', 'jpcrm-freescout' ) . '</p>';
		}

		$html  = '<div class="table-wrap jpcrm-fs-tickets">';
		$html .= '<table class="ui single line table">';
		$html .= '<thead><tr>';
		$html .= '<th scope="col">' . esc_html__( '#', 'jpcrm-freescout' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Subject', 'jpcrm-freescout' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Status', 'jpcrm-freescout' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Last Activity', 'jpcrm-freescout' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Actions', 'jpcrm-freescout' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $conversations as $conversation ) {

			$conversation_id = isset( $conversation['id'] ) ? absint( $conversation['id'] ) : 0;
			$number          = isset( $conversation['number'] ) ? absint( $conversation['number'] ) : 0;
			$subject         = isset( $conversation['subject'] ) ? $conversation['subject'] : __( '(no subject)', 'jpcrm-freescout' );
			$status          = isset( $conversation['status'] ) ? $conversation['status'] : '';
			$updated         = isset( $conversation['userUpdatedAt'] ) ? $conversation['userUpdatedAt'] : ( isset( $conversation['updatedAt'] ) ? $conversation['updatedAt'] : '' );

			$inbox_url = add_query_arg(
				array(
					'page'         => jpcrm_fs()->slugs['inbox'],
					'conversation' => $conversation_id,
				),
				admin_url( 'admin.php' )
			);

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $number ? $number : $conversation_id ) . '</td>';
			$html .= '<td><a href="' . esc_url( $inbox_url ) . '">' . esc_html( $subject ) . '</a></td>';
			$html .= '<td>' . self::render_status_label( $status ) . '</td>';
			$html .= '<td>' . esc_html( self::format_date( $updated ) ) . '</td>';
			$html .= '<td><a href="' . esc_url( jpcrm_fs()->conversation_url( $conversation_id ) ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'Open in FreeScout', 'jpcrm-freescout' ) . '</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';

		$new_ticket_url = add_query_arg(
			array(
				'page'    => jpcrm_fs()->slugs['inbox'],
				'action'  => 'new',
				'contact' => $contact_id,
			),
			admin_url( 'admin.php' )
		);

		$html .= '<p><a class="ui tiny button" href="' . esc_url( $new_ticket_url ) . '">'
			. esc_html__( 'New Support Ticket', 'jpcrm-freescout' ) . '</a></p>';

		return $html;
	}

	/**
	 * Render a status as a coloured label.
	 *
	 * @param string $status FreeScout status.
	 * @return string HTML.
	 */
	public static function render_status_label( $status ) {

		$labels = array(
			'active'  => array( __( 'Active', 'jpcrm-freescout' ), 'yellow' ),
			'pending' => array( __( 'Pending', 'jpcrm-freescout' ), 'orange' ),
			'closed'  => array( __( 'Closed', 'jpcrm-freescout' ), 'grey' ),
			'spam'    => array( __( 'Spam', 'jpcrm-freescout' ), 'red' ),
		);

		$status = is_string( $status ) ? strtolower( $status ) : '';

		if ( ! isset( $labels[ $status ] ) ) {
			return '<span class="ui label">' . esc_html( $status !== '' ? $status : '—' ) . '</span>';
		}

		return '<span class="ui ' . esc_attr( $labels[ $status ][1] ) . ' label">'
			. esc_html( $labels[ $status ][0] ) . '</span>';
	}

	/**
	 * Format an ISO 8601 timestamp in the site's timezone and format.
	 *
	 * @param string $iso_date ISO 8601 date.
	 * @return string
	 */
	public static function format_date( $iso_date ) {

		if ( ! is_string( $iso_date ) || $iso_date === '' ) {
			return '—';
		}

		$timestamp = strtotime( $iso_date );

		if ( ! $timestamp ) {
			return '—';
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);
	}

	/**
	 * Add a "New Support Ticket" action to the contact record.
	 *
	 * @param array $actions Existing actions.
	 * @param int   $cid     Contact ID.
	 * @return array
	 */
	public function append_contact_action( $actions, $cid ) {

		if ( ! is_array( $actions ) || ! jpcrm_fs()->is_configured() ) {
			return $actions;
		}

		$actions['jpcrm_fs_new_ticket'] = array(
			'url'   => add_query_arg(
				array(
					'page'    => jpcrm_fs()->slugs['inbox'],
					'action'  => 'new',
					'contact' => absint( $cid ),
				),
				admin_url( 'admin.php' )
			),
			'label' => __( 'New Support Ticket', 'jpcrm-freescout' ),
			'ico'   => 'life ring outline icon',
		);

		return $actions;
	}
}
