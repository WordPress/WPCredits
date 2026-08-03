<?php
/**
 * The inbox screen inside the WordPress dashboard.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inbox admin page.
 */
class JPCRM_FS_Inbox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'zbs_menu_wpmenu', array( $this, 'register_menu_item' ) );
	}

	/**
	 * Add the inbox as a sub-item of the CRM menu.
	 *
	 * @param array $menu CRM menu definition.
	 * @return array
	 */
	public function register_menu_item( $menu ) {

		if ( ! is_array( $menu ) || ! isset( $menu['jpcrm'] ) ) {
			return $menu;
		}

		$menu['jpcrm']['subitems']['jpcrm-freescout'] = array(
			'title'      => __( 'Inbox', 'jpcrm-freescout' ),
			'url'        => jpcrm_fs()->slugs['inbox'],
			'perms'      => 'admin_zerobs_customers',
			'order'      => 8,
			'wpposition' => 8,
			'callback'   => 'jpcrm_fs_render_inbox_page',
			'stylefuncs' => array( 'zeroBSCRM_global_admin_styles', 'jpcrm_fs_enqueue_inbox_assets' ),
		);

		return $menu;
	}

	/**
	 * Render whichever view the query string asks for.
	 */
	public function render() {

		if ( ! current_user_can( 'admin_zerobs_customers' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the support inbox.', 'jpcrm-freescout' ) );
		}

		echo '<div class="wrap jpcrm-fs-wrap">';

		if ( ! jpcrm_fs()->is_configured() ) {
			$this->render_setup_prompt();
			echo '</div>';
			return;
		}

		$action          = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$conversation_id = isset( $_GET['conversation'] ) ? absint( wp_unslash( $_GET['conversation'] ) ) : 0;

		if ( $action === 'new' ) {
			$this->render_new_ticket_view();
		} elseif ( $conversation_id > 0 ) {
			$this->render_conversation_view( $conversation_id );
		} else {
			$this->render_list_view();
		}

		echo '</div>';
	}

	/**
	 * Shown when the plugin has no URL/API key yet.
	 */
	private function render_setup_prompt() {

		$settings_url = jpcrm_fs()->settings_url();

		echo '<h1>' . esc_html__( 'Support Inbox', 'jpcrm-freescout' ) . '</h1>';
		echo '<div class="notice notice-info inline"><p>';
		printf(
			/* translators: %s: settings page URL. */
			wp_kses_post( __( 'Connect your FreeScout help desk to get started — add its URL and API key in <a href="%s">the FreeScout settings</a>.', 'jpcrm-freescout' ) ),
			esc_url( $settings_url )
		);
		echo '</p></div>';
	}

	/**
	 * The conversation list.
	 */
	private function render_list_view() {

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$query = array(
			'status'   => $status,
			'page'     => $paged,
			'pageSize' => 25,
		);

		if ( $search !== '' ) {
			// FreeScout matches a bare subject substring on this parameter.
			$query['subject'] = $search;
		}

		$conversations = jpcrm_fs()->api->get_conversations( $query );

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Support Inbox', 'jpcrm-freescout' ) . '</h1>';

		$this->render_status_filter( $status, $search );

		if ( is_wp_error( $conversations ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $conversations->get_error_message() ) . '</p></div>';
			return;
		}

		if ( empty( $conversations ) ) {
			echo '<p>' . esc_html__( 'Nothing here.', 'jpcrm-freescout' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped jpcrm-fs-list">';
		echo '<thead><tr>';
		echo '<th scope="col" class="jpcrm-fs-col-number">' . esc_html__( '#', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Subject', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Customer', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'jpcrm-freescout' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Last Activity', 'jpcrm-freescout' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $conversations as $conversation ) {

			$conversation_id = isset( $conversation['id'] ) ? absint( $conversation['id'] ) : 0;
			$number          = isset( $conversation['number'] ) ? absint( $conversation['number'] ) : $conversation_id;
			$subject         = isset( $conversation['subject'] ) ? $conversation['subject'] : __( '(no subject)', 'jpcrm-freescout' );
			$updated         = isset( $conversation['userUpdatedAt'] ) ? $conversation['userUpdatedAt'] : ( isset( $conversation['updatedAt'] ) ? $conversation['updatedAt'] : '' );
			$customer        = isset( $conversation['customer'] ) && is_array( $conversation['customer'] ) ? $conversation['customer'] : array();

			$view_url = add_query_arg(
				array(
					'page'         => jpcrm_fs()->slugs['inbox'],
					'conversation' => $conversation_id,
				),
				admin_url( 'admin.php' )
			);

			echo '<tr>';
			echo '<td>' . esc_html( $number ) . '</td>';
			echo '<td><strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $subject ) . '</a></strong></td>';
			echo '<td>' . $this->render_customer_cell( $customer ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo '<td>' . JPCRM_FS_CRM::render_status_label( isset( $conversation['status'] ) ? $conversation['status'] : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo '<td>' . esc_html( JPCRM_FS_CRM::format_date( $updated ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$this->render_pagination( $paged, count( $conversations ), $status, $search );
	}

	/**
	 * Status tabs plus a search box.
	 *
	 * @param string $current Current status filter.
	 * @param string $search  Current search term.
	 */
	private function render_status_filter( $current, $search ) {

		$statuses = array(
			'active'  => __( 'Active', 'jpcrm-freescout' ),
			'pending' => __( 'Pending', 'jpcrm-freescout' ),
			'closed'  => __( 'Closed', 'jpcrm-freescout' ),
			'all'     => __( 'All', 'jpcrm-freescout' ),
		);

		echo '<ul class="subsubsub">';

		$last = array_key_last( $statuses );

		foreach ( $statuses as $key => $label ) {

			$url = add_query_arg(
				array(
					'page'   => jpcrm_fs()->slugs['inbox'],
					'status' => $key,
				),
				admin_url( 'admin.php' )
			);

			echo '<li>';
			echo '<a href="' . esc_url( $url ) . '"' . ( $current === $key ? ' class="current"' : '' ) . '>';
			echo esc_html( $label );
			echo '</a>';
			echo $key === $last ? '' : ' | ';
			echo '</li>';
		}

		echo '</ul>';

		echo '<form method="get" class="search-form jpcrm-fs-search">';
		echo '<input type="hidden" name="page" value="' . esc_attr( jpcrm_fs()->slugs['inbox'] ) . '" />';
		echo '<input type="hidden" name="status" value="' . esc_attr( $current ) . '" />';
		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="jpcrm-fs-search-input">' . esc_html__( 'Search tickets by subject', 'jpcrm-freescout' ) . '</label>';
		echo '<input type="search" id="jpcrm-fs-search-input" name="s" value="' . esc_attr( $search ) . '" />';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Search Tickets', 'jpcrm-freescout' ) . '" />';
		echo '</p>';
		echo '</form>';
	}

	/**
	 * Previous/next links.
	 *
	 * FreeScout's list response doesn't reliably carry a total count, so this is
	 * deliberately a simple prev/next rather than a numbered pager.
	 *
	 * @param int    $paged   Current page.
	 * @param int    $count   Rows on this page.
	 * @param string $status  Current status filter.
	 * @param string $search  Current search term.
	 */
	private function render_pagination( $paged, $count, $status, $search ) {

		$base = array(
			'page'   => jpcrm_fs()->slugs['inbox'],
			'status' => $status,
		);

		if ( $search !== '' ) {
			$base['s'] = $search;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';

		if ( $paged > 1 ) {
			$prev = add_query_arg( array_merge( $base, array( 'paged' => $paged - 1 ) ), admin_url( 'admin.php' ) );
			echo '<a class="button" href="' . esc_url( $prev ) . '">' . esc_html__( '‹ Newer', 'jpcrm-freescout' ) . '</a> ';
		}

		// A full page implies there may be more.
		if ( $count >= 25 ) {
			$next = add_query_arg( array_merge( $base, array( 'paged' => $paged + 1 ) ), admin_url( 'admin.php' ) );
			echo '<a class="button" href="' . esc_url( $next ) . '">' . esc_html__( 'Older ›', 'jpcrm-freescout' ) . '</a>';
		}

		echo '</div></div>';
	}

	/**
	 * Customer cell, linked to the CRM contact when one exists.
	 *
	 * @param array $customer Customer payload.
	 * @return string HTML.
	 */
	private function render_customer_cell( $customer ) {

		$email = jpcrm_fs()->contacts->get_customer_email( $customer );

		$name = trim(
			( isset( $customer['firstName'] ) ? $customer['firstName'] : '' ) . ' ' .
			( isset( $customer['lastName'] ) ? $customer['lastName'] : '' )
		);

		if ( $name === '' ) {
			$name = $email !== '' ? $email : __( 'Unknown', 'jpcrm-freescout' );
		}

		if ( $email === '' ) {
			return esc_html( $name );
		}

		$contact_id = jpcrm_fs()->contacts->get_contact_id_by_email( $email );

		if ( $contact_id < 1 ) {
			return esc_html( $name ) . '<br /><span class="jpcrm-fs-muted">' . esc_html( $email ) . '</span>';
		}

		return '<a href="' . esc_url( jpcrm_fs()->contacts->contact_url( $contact_id ) ) . '">' . esc_html( $name ) . '</a>'
			. '<br /><span class="jpcrm-fs-muted">' . esc_html( $email ) . '</span>';
	}

	/**
	 * A single conversation, with its thread history and a reply box.
	 *
	 * @param int $conversation_id FreeScout conversation ID.
	 */
	private function render_conversation_view( $conversation_id ) {

		$conversation = jpcrm_fs()->api->get_conversation( $conversation_id );

		$back_url = add_query_arg( 'page', jpcrm_fs()->slugs['inbox'], admin_url( 'admin.php' ) );

		echo '<p><a href="' . esc_url( $back_url ) . '">' . esc_html__( '← Back to inbox', 'jpcrm-freescout' ) . '</a></p>';

		if ( is_wp_error( $conversation ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $conversation->get_error_message() ) . '</p></div>';
			return;
		}

		$subject  = isset( $conversation['subject'] ) ? $conversation['subject'] : __( '(no subject)', 'jpcrm-freescout' );
		$number   = isset( $conversation['number'] ) ? absint( $conversation['number'] ) : $conversation_id;
		$status   = isset( $conversation['status'] ) ? $conversation['status'] : '';
		$customer = isset( $conversation['customer'] ) && is_array( $conversation['customer'] ) ? $conversation['customer'] : array();
		$threads  = jpcrm_fs()->api->get_threads_from_conversation( $conversation );

		echo '<h1 class="wp-heading-inline">' . esc_html( $subject ) . '</h1>';
		echo ' <span class="jpcrm-fs-number">#' . esc_html( $number ) . '</span> ';
		echo JPCRM_FS_CRM::render_status_label( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.

		echo '<p><a class="button" href="' . esc_url( jpcrm_fs()->conversation_url( $conversation_id ) ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Open in FreeScout', 'jpcrm-freescout' ) . '</a></p>';

		$this->render_customer_panel( $customer );
		$this->render_threads( $threads );
		$this->render_reply_box( $conversation_id );
	}

	/**
	 * Customer summary panel, with a CRM link.
	 *
	 * @param array $customer Customer payload.
	 */
	private function render_customer_panel( $customer ) {

		$email = jpcrm_fs()->contacts->get_customer_email( $customer );

		if ( $email === '' ) {
			return;
		}

		$contact_id = jpcrm_fs()->contacts->get_contact_id_by_email( $email );

		echo '<div class="jpcrm-fs-panel">';
		echo '<strong>' . esc_html__( 'Customer', 'jpcrm-freescout' ) . ':</strong> ';
		echo esc_html( $email );

		if ( $contact_id > 0 ) {
			echo ' — <a href="' . esc_url( jpcrm_fs()->contacts->contact_url( $contact_id ) ) . '">'
				. esc_html__( 'View CRM contact', 'jpcrm-freescout' ) . '</a>';
		} else {
			echo ' — <span class="jpcrm-fs-muted">' . esc_html__( 'no matching CRM contact', 'jpcrm-freescout' ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * The thread history.
	 *
	 * @param array $threads Thread payloads.
	 */
	private function render_threads( $threads ) {

		if ( empty( $threads ) ) {
			echo '<p>' . esc_html__( 'This conversation has no messages yet.', 'jpcrm-freescout' ) . '</p>';
			return;
		}

		// FreeScout returns newest first; read it like an email client instead.
		$threads = array_reverse( $threads );

		echo '<div class="jpcrm-fs-threads">';

		foreach ( $threads as $thread ) {

			$type = isset( $thread['type'] ) ? $thread['type'] : '';

			// Line-item threads are status/assignment bookkeeping, not messages.
			if ( $type === 'lineitem' ) {
				continue;
			}

			$body    = isset( $thread['body'] ) ? $thread['body'] : '';
			$created = isset( $thread['createdAt'] ) ? $thread['createdAt'] : '';
			$author  = $this->describe_thread_author( $thread );

			echo '<div class="jpcrm-fs-thread jpcrm-fs-thread-' . esc_attr( $type ) . '">';
			echo '<div class="jpcrm-fs-thread-head">';
			echo '<span class="jpcrm-fs-thread-author">' . esc_html( $author ) . '</span>';
			echo '<span class="jpcrm-fs-thread-date">' . esc_html( JPCRM_FS_CRM::format_date( $created ) ) . '</span>';

			if ( $type === 'note' ) {
				echo '<span class="jpcrm-fs-thread-badge">' . esc_html__( 'Internal note', 'jpcrm-freescout' ) . '</span>';
			}

			echo '</div>';
			echo '<div class="jpcrm-fs-thread-body">' . wp_kses_post( $body ) . '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Work out a display name for a thread's author.
	 *
	 * @param array $thread Thread payload.
	 * @return string
	 */
	private function describe_thread_author( $thread ) {

		foreach ( array( 'createdBy', 'customer', 'assignedTo' ) as $key ) {

			if ( empty( $thread[ $key ] ) || ! is_array( $thread[ $key ] ) ) {
				continue;
			}

			$person = $thread[ $key ];

			$name = trim(
				( isset( $person['firstName'] ) ? $person['firstName'] : '' ) . ' ' .
				( isset( $person['lastName'] ) ? $person['lastName'] : '' )
			);

			if ( $name !== '' ) {
				return $name;
			}

			if ( ! empty( $person['email'] ) ) {
				return $person['email'];
			}
		}

		return __( 'Unknown', 'jpcrm-freescout' );
	}

	/**
	 * The reply box — or an explanation of why there isn't one.
	 *
	 * @param int $conversation_id FreeScout conversation ID.
	 */
	private function render_reply_box( $conversation_id ) {

		$can_reply = jpcrm_fs()->agents->can_reply();

		if ( is_wp_error( $can_reply ) ) {
			echo '<div class="notice notice-warning inline jpcrm-fs-no-reply"><p>';
			echo esc_html( $can_reply->get_error_message() );
			echo '</p></div>';
			return;
		}

		echo '<div class="jpcrm-fs-reply" data-conversation="' . esc_attr( $conversation_id ) . '">';
		echo '<h2>' . esc_html__( 'Reply', 'jpcrm-freescout' ) . '</h2>';

		wp_nonce_field( 'jpcrm_fs_reply', 'jpcrm_fs_reply_nonce' );

		echo '<textarea id="jpcrm-fs-reply-text" rows="6" class="large-text" placeholder="'
			. esc_attr__( 'Write your reply…', 'jpcrm-freescout' ) . '"></textarea>';

		echo '<p class="jpcrm-fs-reply-actions">';
		echo '<button type="button" class="button button-primary" data-jpcrm-fs-action="reply">'
			. esc_html__( 'Send Reply', 'jpcrm-freescout' ) . '</button> ';
		echo '<button type="button" class="button" data-jpcrm-fs-action="note">'
			. esc_html__( 'Add Internal Note', 'jpcrm-freescout' ) . '</button>';
		echo '<label class="jpcrm-fs-close-after"><input type="checkbox" id="jpcrm-fs-close-after" /> '
			. esc_html__( 'Close ticket after sending', 'jpcrm-freescout' ) . '</label>';
		echo '</p>';

		echo '<div class="jpcrm-fs-reply-feedback" role="status" aria-live="polite"></div>';
		echo '</div>';
	}

	/**
	 * Form for opening a new ticket on a contact's behalf.
	 */
	private function render_new_ticket_view() {

		$contact_id = isset( $_GET['contact'] ) ? absint( wp_unslash( $_GET['contact'] ) ) : 0;
		$email      = '';

		if ( $contact_id > 0 && function_exists( 'zeroBS_customerEmail' ) ) {
			$email = sanitize_email( (string) zeroBS_customerEmail( $contact_id ) );
		}

		$back_url = add_query_arg( 'page', jpcrm_fs()->slugs['inbox'], admin_url( 'admin.php' ) );

		echo '<p><a href="' . esc_url( $back_url ) . '">' . esc_html__( '← Back to inbox', 'jpcrm-freescout' ) . '</a></p>';
		echo '<h1>' . esc_html__( 'New Support Ticket', 'jpcrm-freescout' ) . '</h1>';

		$can_reply = jpcrm_fs()->agents->can_reply();

		if ( is_wp_error( $can_reply ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $can_reply->get_error_message() ) . '</p></div>';
			return;
		}

		$mailbox_id = jpcrm_fs()->get_setting( 'mailbox_id' );

		if ( $mailbox_id === '' ) {
			echo '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'Choose a default mailbox in the FreeScout settings before opening tickets from the CRM.', 'jpcrm-freescout' )
				. '</p></div>';
			return;
		}

		echo '<div class="jpcrm-fs-new-ticket">';
		wp_nonce_field( 'jpcrm_fs_new_ticket', 'jpcrm_fs_new_ticket_nonce' );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="jpcrm-fs-new-email">' . esc_html__( 'Customer email', 'jpcrm-freescout' ) . '</label></th>';
		echo '<td><input type="email" id="jpcrm-fs-new-email" class="regular-text" value="' . esc_attr( $email ) . '" required /></td></tr>';

		echo '<tr><th scope="row"><label for="jpcrm-fs-new-subject">' . esc_html__( 'Subject', 'jpcrm-freescout' ) . '</label></th>';
		echo '<td><input type="text" id="jpcrm-fs-new-subject" class="regular-text" required /></td></tr>';

		echo '<tr><th scope="row"><label for="jpcrm-fs-new-body">' . esc_html__( 'Message', 'jpcrm-freescout' ) . '</label></th>';
		echo '<td><textarea id="jpcrm-fs-new-body" rows="8" class="large-text" required></textarea></td></tr>';

		echo '</tbody></table>';

		echo '<p class="submit">';
		echo '<button type="button" class="button button-primary" data-jpcrm-fs-action="create">'
			. esc_html__( 'Create Ticket', 'jpcrm-freescout' ) . '</button>';
		echo '</p>';

		echo '<div class="jpcrm-fs-reply-feedback" role="status" aria-live="polite"></div>';
		echo '</div>';
	}
}

/**
 * Menu callback: render the inbox.
 */
function jpcrm_fs_render_inbox_page() {

	$inbox = new JPCRM_FS_Inbox();
	$inbox->render();
}

/**
 * Menu style callback: load the inbox CSS/JS.
 */
function jpcrm_fs_enqueue_inbox_assets() {

	wp_enqueue_style(
		'jpcrm-fs-inbox',
		JPCRM_FS_URL . 'assets/inbox.css',
		array(),
		JPCRM_FS_VERSION
	);

	wp_enqueue_script(
		'jpcrm-fs-inbox',
		JPCRM_FS_URL . 'assets/inbox.js',
		array(),
		JPCRM_FS_VERSION,
		true
	);

	wp_localize_script(
		'jpcrm-fs-inbox',
		'jpcrmFsInbox',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'inboxUrl' => add_query_arg( 'page', jpcrm_fs()->slugs['inbox'], admin_url( 'admin.php' ) ),
			'strings'  => array(
				'empty'    => __( 'Write something before sending.', 'jpcrm-freescout' ),
				'sending'  => __( 'Sending…', 'jpcrm-freescout' ),
				'sent'     => __( 'Sent.', 'jpcrm-freescout' ),
				'created'  => __( 'Ticket created.', 'jpcrm-freescout' ),
				'failed'   => __( 'Something went wrong.', 'jpcrm-freescout' ),
				'required' => __( 'Fill in the email, subject and message.', 'jpcrm-freescout' ),
			),
		)
	);
}
