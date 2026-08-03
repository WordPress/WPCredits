<?php
/**
 * FreeScout REST API client.
 *
 * Thin wrapper over the FreeScout "API & Webhooks" module (free, bundled).
 * Docs: https://api-docs.freescout.net/
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * FreeScout API client.
 */
class JPCRM_FS_API {

	/**
	 * How long GET responses are cached, in seconds.
	 *
	 * Kept deliberately short: the inbox should feel live, and webhooks flush
	 * the relevant keys anyway.
	 */
	const CACHE_TTL = 60;

	/**
	 * Transient key prefix.
	 */
	const CACHE_PREFIX = 'jpcrm_fs_';

	/**
	 * Request timeout in seconds.
	 */
	const TIMEOUT = 15;

	/**
	 * List conversations.
	 *
	 * @param array $args Query args (mailboxId, status, customerEmail, tag, page, pageSize, ...).
	 * @return array|WP_Error List of conversation arrays.
	 */
	public function get_conversations( $args = array() ) {

		$defaults = array(
			'pageSize'  => 25,
			'sortField' => 'updatedAt',
			'sortOrder' => 'desc',
		);

		$mailbox_id = jpcrm_fs()->get_setting( 'mailbox_id' );

		if ( $mailbox_id !== '' && ! isset( $args['mailboxId'] ) ) {
			$defaults['mailboxId'] = absint( $mailbox_id );
		}

		$query = wp_parse_args( $args, $defaults );

		// FreeScout's `status` only accepts a single real status; our own "all"
		// pseudo-status means "don't filter".
		if ( isset( $query['status'] ) && $query['status'] === 'all' ) {
			unset( $query['status'] );
		}

		$response = $this->get( '/conversations', $query );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->extract_embedded( $response, 'conversations' );
	}

	/**
	 * Fetch a single conversation, with its threads.
	 *
	 * @param int    $conversation_id FreeScout conversation ID.
	 * @param string $embed           Comma-separated embeds (threads, timelogs, tags).
	 * @return array|WP_Error
	 */
	public function get_conversation( $conversation_id, $embed = 'threads' ) {

		return $this->get(
			'/conversations/' . absint( $conversation_id ),
			array( 'embed' => $embed )
		);
	}

	/**
	 * Add a thread to a conversation.
	 *
	 * Note: `imported` is intentionally never set here. FreeScout treats
	 * `imported: true` as "suppress all outgoing email and notifications",
	 * which would mean the customer never receives the reply.
	 *
	 * @param int   $conversation_id FreeScout conversation ID.
	 * @param array $args            Thread args. `type` and `user` are required for message/note.
	 * @return array|WP_Error
	 */
	public function create_thread( $conversation_id, $args ) {

		unset( $args['imported'] );

		$result = $this->post( '/conversations/' . absint( $conversation_id ) . '/threads', $args );

		if ( ! is_wp_error( $result ) ) {
			$this->flush_cache();
		}

		return $result;
	}

	/**
	 * Create a conversation.
	 *
	 * @param array $args Conversation args.
	 * @return array|WP_Error
	 */
	public function create_conversation( $args ) {

		$result = $this->post( '/conversations', $args );

		if ( ! is_wp_error( $result ) ) {
			$this->flush_cache();
		}

		return $result;
	}

	/**
	 * Update a conversation (status, assignee, mailbox, ...).
	 *
	 * @param int   $conversation_id FreeScout conversation ID.
	 * @param array $args            Fields to change.
	 * @return array|WP_Error
	 */
	public function update_conversation( $conversation_id, $args ) {

		$result = $this->request( 'PUT', '/conversations/' . absint( $conversation_id ), array(), $args );

		if ( ! is_wp_error( $result ) ) {
			$this->flush_cache();
		}

		return $result;
	}

	/**
	 * Look up support staff.
	 *
	 * @param array $args Query args (email, page, pageSize).
	 * @return array|WP_Error List of user arrays.
	 */
	public function get_users( $args = array() ) {

		$response = $this->get( '/users', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->extract_embedded( $response, 'users' );
	}

	/**
	 * List mailboxes.
	 *
	 * @return array|WP_Error List of mailbox arrays.
	 */
	public function get_mailboxes() {

		$response = $this->get( '/mailboxes' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->extract_embedded( $response, 'mailboxes' );
	}

	/**
	 * Pull the threads out of a conversation payload.
	 *
	 * @param array $conversation Conversation payload from get_conversation().
	 * @return array
	 */
	public function get_threads_from_conversation( $conversation ) {

		if ( ! is_array( $conversation ) ) {
			return array();
		}

		return $this->extract_embedded( $conversation, 'threads' );
	}

	/**
	 * GET request, cached.
	 *
	 * @param string $path  API path, leading slash.
	 * @param array  $query Query args.
	 * @return array|WP_Error
	 */
	public function get( $path, $query = array() ) {

		$cache_key = self::CACHE_PREFIX . md5( $path . wp_json_encode( $query ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$result = $this->request( 'GET', $path, $query );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
			$this->remember_cache_key( $cache_key );
		}

		return $result;
	}

	/**
	 * POST request.
	 *
	 * @param string $path API path, leading slash.
	 * @param array  $body Request body.
	 * @return array|WP_Error
	 */
	public function post( $path, $body ) {
		return $this->request( 'POST', $path, array(), $body );
	}

	/**
	 * Perform a request against the FreeScout API.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path, leading slash.
	 * @param array  $query  Query args.
	 * @param array  $body   Request body, JSON-encoded when present.
	 * @return array|WP_Error Decoded body (empty array for 204), or error.
	 */
	private function request( $method, $path, $query = array(), $body = null ) {

		$base    = jpcrm_fs()->get_setting( 'url' );
		$api_key = jpcrm_fs()->get_setting( 'api_key' );

		if ( $base === '' || $api_key === '' ) {
			return new WP_Error(
				'jpcrm_fs_not_configured',
				__( 'FreeScout is not configured yet — add your help desk URL and API key in the CRM settings.', 'jpcrm-freescout' )
			);
		}

		$url = trailingslashit( $base ) . 'api' . $path;

		if ( ! empty( $query ) ) {
			// add_query_arg() url-encodes values itself — don't pre-encode, or
			// an address like a@b.com arrives as a%2540b.com and matches nothing.
			$url = add_query_arg( array_map( 'strval', $query ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'X-FreeScout-API-Key' => $api_key,
				'Accept'              => 'application/json',
			),
		);

		if ( $body !== null ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'jpcrm_fs_http_error',
				sprintf(
					/* translators: %s: underlying HTTP error message. */
					__( 'Could not reach FreeScout: %s', 'jpcrm-freescout' ),
					$response->get_error_message()
				)
			);
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( $code === 204 || $raw_body === '' ) {
			return array();
		}

		$decoded = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'jpcrm_fs_api_error',
				$this->describe_error( $code, $decoded ),
				array( 'status' => $code )
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'jpcrm_fs_bad_response',
				__( 'FreeScout returned a response that could not be understood.', 'jpcrm-freescout' )
			);
		}

		return $decoded;
	}

	/**
	 * Turn an error response into something a human can act on.
	 *
	 * @param int        $code    HTTP status code.
	 * @param array|null $decoded Decoded response body.
	 * @return string
	 */
	private function describe_error( $code, $decoded ) {

		$detail = '';

		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
				$detail = $decoded['message'];
			} elseif ( ! empty( $decoded['_embedded']['errors'] ) && is_array( $decoded['_embedded']['errors'] ) ) {
				$messages = array();
				foreach ( $decoded['_embedded']['errors'] as $error ) {
					if ( ! empty( $error['message'] ) ) {
						$messages[] = $error['message'];
					}
				}
				$detail = implode( '; ', $messages );
			}
		}

		if ( $code === 401 || $code === 403 ) {
			return __( 'FreeScout rejected the API key. Check it in Manage → Settings → API & Webhooks.', 'jpcrm-freescout' );
		}

		if ( $code === 404 ) {
			return __( 'That FreeScout record no longer exists.', 'jpcrm-freescout' );
		}

		if ( $detail !== '' ) {
			return sprintf(
				/* translators: 1: HTTP status code, 2: error detail from FreeScout. */
				__( 'FreeScout returned an error (%1$d): %2$s', 'jpcrm-freescout' ),
				$code,
				$detail
			);
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'FreeScout returned an unexpected error (%d).', 'jpcrm-freescout' ),
			$code
		);
	}

	/**
	 * Read a collection out of a HAL-style payload.
	 *
	 * FreeScout follows Help Scout's `_embedded` convention, but be forgiving
	 * about a flat array too so a future API change degrades gracefully.
	 *
	 * @param array  $payload Decoded response.
	 * @param string $key     Collection name.
	 * @return array
	 */
	private function extract_embedded( $payload, $key ) {

		if ( ! is_array( $payload ) ) {
			return array();
		}

		if ( isset( $payload['_embedded'][ $key ] ) && is_array( $payload['_embedded'][ $key ] ) ) {
			return $payload['_embedded'][ $key ];
		}

		if ( isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
			return $payload[ $key ];
		}

		// A bare list (no envelope) — only treat it as one if it looks like a list.
		if ( isset( $payload[0] ) && is_array( $payload[0] ) ) {
			return $payload;
		}

		return array();
	}

	/**
	 * Track a cache key so it can be flushed later.
	 *
	 * WordPress has no transient-by-prefix delete, so keep a register.
	 *
	 * @param string $cache_key Transient key.
	 */
	private function remember_cache_key( $cache_key ) {

		$keys = get_option( 'jpcrm_fs_cache_keys', array() );

		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( in_array( $cache_key, $keys, true ) ) {
			return;
		}

		$keys[] = $cache_key;

		// Keep the register from growing without bound.
		if ( count( $keys ) > 200 ) {
			$keys = array_slice( $keys, -200 );
		}

		update_option( 'jpcrm_fs_cache_keys', $keys, false );
	}

	/**
	 * Drop all cached API reads.
	 */
	public function flush_cache() {

		$keys = get_option( 'jpcrm_fs_cache_keys', array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( $key );
			}
		}

		update_option( 'jpcrm_fs_cache_keys', array(), false );
	}
}
