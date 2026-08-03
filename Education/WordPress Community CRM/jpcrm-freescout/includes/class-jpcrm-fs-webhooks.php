<?php
/**
 * Inbound FreeScout webhooks.
 *
 * FreeScout signs each delivery with an `X-FreeScout-Signature` header holding
 * base64( HMAC-SHA1( raw_body, secret ) ). Requests that don't verify are
 * rejected before any payload is read.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Webhook receiver.
 */
class JPCRM_FS_Webhooks {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'jpcrm-freescout/v1';

	/**
	 * REST route.
	 */
	const REST_ROUTE = '/webhook';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register the webhook route.
	 */
	public function register_route() {

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * The URL to paste into FreeScout.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/**
	 * Verify the HMAC signature on the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function verify_signature( $request ) {

		$secret = jpcrm_fs()->get_setting( 'webhook_secret' );

		if ( $secret === '' ) {
			return new WP_Error(
				'jpcrm_fs_webhook_disabled',
				__( 'Webhooks are not enabled for this site.', 'jpcrm-freescout' ),
				array( 'status' => 403 )
			);
		}

		$provided = $request->get_header( 'x_freescout_signature' );

		if ( empty( $provided ) ) {
			return new WP_Error(
				'jpcrm_fs_webhook_unsigned',
				__( 'Missing webhook signature.', 'jpcrm-freescout' ),
				array( 'status' => 401 )
			);
		}

		$expected = base64_encode( hash_hmac( 'sha1', $request->get_body(), $secret, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- signature format required by FreeScout.

		if ( ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'jpcrm_fs_webhook_bad_signature',
				__( 'Webhook signature did not verify.', 'jpcrm-freescout' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle a verified webhook delivery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {

		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'handled' => false ), 202 );
		}

		$event = $this->resolve_event( $request, $payload );

		// Anything arriving from the help desk invalidates our cached reads.
		jpcrm_fs()->api->flush_cache();

		switch ( $event ) {

			case 'convo.created':
				$this->handle_conversation_created( $payload );
				break;

			case 'convo.customer.reply.created':
				$this->handle_thread_event( $payload, 'freescout_customer_reply' );
				break;

			case 'convo.agent.reply.created':
				$this->handle_thread_event( $payload, 'freescout_agent_reply' );
				break;

			case 'convo.note.created':
				$this->handle_thread_event( $payload, 'freescout_note_added' );
				break;

			case 'convo.status':
				$this->handle_status_change( $payload );
				break;

			case 'customer.created':
			case 'customer.updated':
				$this->handle_customer_event( $payload );
				break;

			default:
				/**
				 * Fires for webhook events this plugin doesn't handle itself.
				 *
				 * @param string $event   Event name.
				 * @param array  $payload Decoded payload.
				 */
				do_action( 'jpcrm_fs_unhandled_webhook', $event, $payload );
				break;
		}

		return new WP_REST_Response( array( 'handled' => true ), 200 );
	}

	/**
	 * Work out which event fired.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $payload Decoded payload.
	 * @return string
	 */
	private function resolve_event( $request, $payload ) {

		$header = $request->get_header( 'x_freescout_event' );

		if ( ! empty( $header ) ) {
			return sanitize_text_field( $header );
		}

		if ( ! empty( $payload['event'] ) && is_string( $payload['event'] ) ) {
			return sanitize_text_field( $payload['event'] );
		}

		// No event name — infer from the payload shape so we at least log something.
		if ( isset( $payload['mailboxId'] ) || isset( $payload['subject'] ) ) {
			return 'convo.created';
		}

		if ( isset( $payload['email'] ) || isset( $payload['emails'] ) ) {
			return 'customer.created';
		}

		return 'unknown';
	}

	/**
	 * A new conversation arrived.
	 *
	 * @param array $conversation Conversation payload.
	 */
	private function handle_conversation_created( $conversation ) {

		$contact_id = $this->resolve_contact( $conversation );

		if ( $contact_id < 1 ) {
			return;
		}

		$subject         = isset( $conversation['subject'] ) ? $conversation['subject'] : '';
		$conversation_id = isset( $conversation['id'] ) ? absint( $conversation['id'] ) : 0;

		jpcrm_fs()->contacts->log_activity(
			$contact_id,
			'freescout_ticket_created',
			sprintf(
				/* translators: %s: ticket subject. */
				__( 'Support ticket opened: %s', 'jpcrm-freescout' ),
				$subject
			),
			$this->first_thread_body( $conversation ),
			array( 'fs_conversation_id' => $conversation_id ),
			'fs_conversation_id'
		);
	}

	/**
	 * A thread (customer reply, agent reply, or note) was added.
	 *
	 * @param array  $conversation Conversation payload.
	 * @param string $log_type     Log type slug.
	 */
	private function handle_thread_event( $conversation, $log_type ) {

		$contact_id = $this->resolve_contact( $conversation );

		if ( $contact_id < 1 ) {
			return;
		}

		$conversation_id = isset( $conversation['id'] ) ? absint( $conversation['id'] ) : 0;
		$subject         = isset( $conversation['subject'] ) ? $conversation['subject'] : '';
		$thread          = $this->latest_thread( $conversation );

		$thread_id = isset( $thread['id'] ) ? absint( $thread['id'] ) : 0;
		$body      = isset( $thread['body'] ) ? wp_strip_all_tags( $thread['body'] ) : '';
		$author    = $this->thread_author_name( $thread );

		$descriptions = array(
			'freescout_customer_reply' => __( '%1$s replied on "%2$s"', 'jpcrm-freescout' ),
			'freescout_agent_reply'    => __( '%1$s replied on "%2$s"', 'jpcrm-freescout' ),
			'freescout_note_added'     => __( '%1$s added an internal note on "%2$s"', 'jpcrm-freescout' ),
		);

		$template = isset( $descriptions[ $log_type ] ) ? $descriptions[ $log_type ] : '%1$s — %2$s';

		jpcrm_fs()->contacts->log_activity(
			$contact_id,
			$log_type,
			sprintf( $template, $author, $subject ),
			$body,
			array(
				'fs_conversation_id' => $conversation_id,
				'fs_thread_id'       => $thread_id,
			),
			'fs_thread_id'
		);
	}

	/**
	 * A conversation's status changed.
	 *
	 * @param array $conversation Conversation payload.
	 */
	private function handle_status_change( $conversation ) {

		$contact_id = $this->resolve_contact( $conversation );

		if ( $contact_id < 1 ) {
			return;
		}

		$conversation_id = isset( $conversation['id'] ) ? absint( $conversation['id'] ) : 0;
		$subject         = isset( $conversation['subject'] ) ? $conversation['subject'] : '';
		$status          = isset( $conversation['status'] ) ? $conversation['status'] : '';

		jpcrm_fs()->contacts->log_activity(
			$contact_id,
			'freescout_status_changed',
			sprintf(
				/* translators: 1: ticket subject, 2: new status. */
				__( 'Ticket "%1$s" is now %2$s', 'jpcrm-freescout' ),
				$subject,
				$status
			),
			'',
			array(
				'fs_conversation_id' => $conversation_id,
				'fs_status'          => $status,
			)
		);
	}

	/**
	 * A customer was created or updated in FreeScout.
	 *
	 * @param array $customer Customer payload.
	 */
	private function handle_customer_event( $customer ) {

		if ( ! jpcrm_fs()->get_setting( 'sync_customers' ) ) {
			return;
		}

		jpcrm_fs()->contacts->upsert_contact_from_customer( $customer );
	}

	/**
	 * Find the CRM contact for a conversation, creating one if configured to.
	 *
	 * @param array $conversation Conversation payload.
	 * @return int Contact ID, or 0.
	 */
	private function resolve_contact( $conversation ) {

		$customer = isset( $conversation['customer'] ) && is_array( $conversation['customer'] )
			? $conversation['customer']
			: array();

		$email = jpcrm_fs()->contacts->get_customer_email( $customer );

		if ( $email === '' ) {
			return 0;
		}

		$contact_id = jpcrm_fs()->contacts->get_contact_id_by_email( $email );

		if ( $contact_id > 0 ) {
			return $contact_id;
		}

		if ( ! jpcrm_fs()->get_setting( 'sync_customers' ) ) {
			return 0;
		}

		return jpcrm_fs()->contacts->upsert_contact_from_customer( $customer );
	}

	/**
	 * Pull the most recent thread out of a conversation payload.
	 *
	 * @param array $conversation Conversation payload.
	 * @return array
	 */
	private function latest_thread( $conversation ) {

		$threads = jpcrm_fs()->api->get_threads_from_conversation( $conversation );

		foreach ( $threads as $thread ) {

			if ( ! is_array( $thread ) ) {
				continue;
			}

			if ( isset( $thread['type'] ) && $thread['type'] === 'lineitem' ) {
				continue;
			}

			// FreeScout orders threads newest-first.
			return $thread;
		}

		return array();
	}

	/**
	 * Body of the first (oldest) message in a conversation.
	 *
	 * @param array $conversation Conversation payload.
	 * @return string
	 */
	private function first_thread_body( $conversation ) {

		$threads = jpcrm_fs()->api->get_threads_from_conversation( $conversation );

		if ( empty( $threads ) ) {
			return '';
		}

		$threads = array_reverse( $threads );

		foreach ( $threads as $thread ) {
			if ( is_array( $thread ) && ! empty( $thread['body'] ) ) {
				return wp_strip_all_tags( $thread['body'] );
			}
		}

		return '';
	}

	/**
	 * Display name for whoever wrote a thread.
	 *
	 * @param array $thread Thread payload.
	 * @return string
	 */
	private function thread_author_name( $thread ) {

		if ( ! is_array( $thread ) ) {
			return __( 'Someone', 'jpcrm-freescout' );
		}

		foreach ( array( 'createdBy', 'customer' ) as $key ) {

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

		return __( 'Someone', 'jpcrm-freescout' );
	}
}
