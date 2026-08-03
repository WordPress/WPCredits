<?php
/**
 * AJAX handlers for replying and opening tickets.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoints.
 */
class JPCRM_FS_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_jpcrm_fs_reply', array( $this, 'handle_reply' ) );
		add_action( 'wp_ajax_jpcrm_fs_create_ticket', array( $this, 'handle_create_ticket' ) );
	}

	/**
	 * Send a reply or add an internal note.
	 */
	public function handle_reply() {

		check_ajax_referer( 'jpcrm_fs_reply', 'nonce' );

		$agent_id = $this->require_agent();

		$conversation_id = isset( $_POST['conversation'] ) ? absint( wp_unslash( $_POST['conversation'] ) ) : 0;
		$text            = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$type            = isset( $_POST['type'] ) && wp_unslash( $_POST['type'] ) === 'note' ? 'note' : 'message';
		$close_after     = isset( $_POST['close'] ) && wp_unslash( $_POST['close'] ) === '1';

		if ( $conversation_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'No conversation specified.', 'jpcrm-freescout' ) ) );
		}

		if ( $text === '' ) {
			wp_send_json_error( array( 'message' => __( 'The message is empty.', 'jpcrm-freescout' ) ) );
		}

		$result = jpcrm_fs()->api->create_thread(
			$conversation_id,
			array(
				'type' => $type,
				'text' => $text,
				'user' => $agent_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( $close_after ) {
			// A failure here shouldn't read as "the reply didn't send" — it did.
			$status_result = jpcrm_fs()->api->update_conversation(
				$conversation_id,
				array(
					'status'  => 'closed',
					'byUser'  => $agent_id,
				)
			);

			if ( is_wp_error( $status_result ) ) {
				wp_send_json_success(
					array(
						'message' => sprintf(
							/* translators: %s: error message. */
							__( 'Reply sent, but the ticket could not be closed: %s', 'jpcrm-freescout' ),
							$status_result->get_error_message()
						),
					)
				);
			}
		}

		$this->log_outbound( $conversation_id, $type, $text );

		wp_send_json_success(
			array(
				'message' => $type === 'note'
					? __( 'Note added.', 'jpcrm-freescout' )
					: __( 'Reply sent.', 'jpcrm-freescout' ),
			)
		);
	}

	/**
	 * Open a new ticket.
	 */
	public function handle_create_ticket() {

		check_ajax_referer( 'jpcrm_fs_new_ticket', 'nonce' );

		$agent_id = $this->require_agent();

		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

		if ( $email === '' || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A valid customer email address is required.', 'jpcrm-freescout' ) ) );
		}

		if ( $subject === '' || $body === '' ) {
			wp_send_json_error( array( 'message' => __( 'Both a subject and a message are required.', 'jpcrm-freescout' ) ) );
		}

		$mailbox_id = absint( jpcrm_fs()->get_setting( 'mailbox_id' ) );

		if ( $mailbox_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'No default mailbox is configured.', 'jpcrm-freescout' ) ) );
		}

		$result = jpcrm_fs()->api->create_conversation(
			array(
				'type'      => 'email',
				'mailboxId' => $mailbox_id,
				'subject'   => $subject,
				'status'    => 'active',
				'customer'  => array( 'email' => $email ),
				'threads'   => array(
					array(
						'type' => 'message',
						'text' => $body,
						'user' => $agent_id,
					),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$conversation_id = isset( $result['id'] ) ? absint( $result['id'] ) : 0;

		$contact_id = jpcrm_fs()->contacts->get_contact_id_by_email( $email );

		if ( $contact_id > 0 ) {
			jpcrm_fs()->contacts->log_activity(
				$contact_id,
				'freescout_ticket_created',
				sprintf(
					/* translators: %s: ticket subject. */
					__( 'Support ticket opened: %s', 'jpcrm-freescout' ),
					$subject
				),
				$body,
				array(
					'fs_conversation_id' => $conversation_id,
					'fs_opened_by_wp'    => get_current_user_id(),
				),
				'fs_conversation_id'
			);
		}

		$redirect = $conversation_id > 0
			? add_query_arg(
				array(
					'page'         => jpcrm_fs()->slugs['inbox'],
					'conversation' => $conversation_id,
				),
				admin_url( 'admin.php' )
			)
			: add_query_arg( 'page', jpcrm_fs()->slugs['inbox'], admin_url( 'admin.php' ) );

		wp_send_json_success(
			array(
				'message'  => __( 'Ticket created.', 'jpcrm-freescout' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Resolve the current user's FreeScout agent ID, or bail out.
	 *
	 * Deliberately strict: without a real agent ID the reply would be
	 * misattributed to the API key owner, so refuse rather than guess.
	 *
	 * @return int
	 */
	private function require_agent() {

		$can_reply = jpcrm_fs()->agents->can_reply();

		if ( is_wp_error( $can_reply ) ) {
			wp_send_json_error( array( 'message' => $can_reply->get_error_message() ), 403 );
		}

		$agent_id = jpcrm_fs()->agents->get_agent_id();

		if ( is_wp_error( $agent_id ) ) {
			wp_send_json_error( array( 'message' => $agent_id->get_error_message() ), 403 );
		}

		return absint( $agent_id );
	}

	/**
	 * Log an outbound reply/note against the CRM contact.
	 *
	 * @param int    $conversation_id FreeScout conversation ID.
	 * @param string $type            'message' or 'note'.
	 * @param string $text            Message body.
	 */
	private function log_outbound( $conversation_id, $type, $text ) {

		// When webhooks are wired up they are the single source of truth for
		// thread activity — logging here too would double up every reply.
		if ( jpcrm_fs()->get_setting( 'webhook_secret' ) !== '' ) {
			return;
		}

		$conversation = jpcrm_fs()->api->get_conversation( $conversation_id, '' );

		if ( is_wp_error( $conversation ) ) {
			return;
		}

		$customer = isset( $conversation['customer'] ) && is_array( $conversation['customer'] )
			? $conversation['customer']
			: array();

		$email = jpcrm_fs()->contacts->get_customer_email( $customer );

		if ( $email === '' ) {
			return;
		}

		$contact_id = jpcrm_fs()->contacts->get_contact_id_by_email( $email );

		if ( $contact_id < 1 ) {
			return;
		}

		$user    = wp_get_current_user();
		$subject = isset( $conversation['subject'] ) ? $conversation['subject'] : '';

		if ( $type === 'note' ) {
			$short_desc = sprintf(
				/* translators: 1: WP user display name, 2: ticket subject. */
				__( '%1$s added an internal note on "%2$s"', 'jpcrm-freescout' ),
				$user->display_name,
				$subject
			);
			$log_type = 'freescout_note_added';
		} else {
			$short_desc = sprintf(
				/* translators: 1: WP user display name, 2: ticket subject. */
				__( '%1$s replied to "%2$s"', 'jpcrm-freescout' ),
				$user->display_name,
				$subject
			);
			$log_type = 'freescout_agent_reply';
		}

		jpcrm_fs()->contacts->log_activity(
			$contact_id,
			$log_type,
			$short_desc,
			$text,
			array(
				'fs_conversation_id' => $conversation_id,
				'fs_sent_by_wp'      => get_current_user_id(),
			)
		);
	}
}
