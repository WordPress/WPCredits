<?php
/**
 * CRM contact resolution and activity logging.
 *
 * @package JPCRM_FreeScout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bridge between FreeScout customers and Jetpack CRM contacts.
 */
class JPCRM_FS_Contacts {

	/**
	 * External source key registered with the CRM.
	 */
	const SOURCE = 'freescout';

	/**
	 * Find a CRM contact ID from an email address.
	 *
	 * @param string $email Email address.
	 * @return int Contact ID, or 0 when not found.
	 */
	public function get_contact_id_by_email( $email ) {

		global $zbs;

		$email = sanitize_email( $email );

		if ( $email === '' || ! isset( $zbs->DAL ) ) {
			return 0;
		}

		$contact_id = $zbs->DAL->contacts->getContact( // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			-1,
			array(
				'email'       => $email,
				'onlyID'      => true,
				'ignoreowner' => true,
			)
		);

		return absint( $contact_id );
	}

	/**
	 * Get the primary email address off a FreeScout customer payload.
	 *
	 * @param array $customer Customer array from the API.
	 * @return string
	 */
	public function get_customer_email( $customer ) {

		if ( ! is_array( $customer ) ) {
			return '';
		}

		if ( ! empty( $customer['email'] ) ) {
			return sanitize_email( $customer['email'] );
		}

		// Some payloads carry a list instead of a single address.
		if ( ! empty( $customer['emails'] ) && is_array( $customer['emails'] ) ) {
			foreach ( $customer['emails'] as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['value'] ) ) {
					return sanitize_email( $entry['value'] );
				}
				if ( is_string( $entry ) && $entry !== '' ) {
					return sanitize_email( $entry );
				}
			}
		}

		return '';
	}

	/**
	 * Create or update a CRM contact from a FreeScout customer.
	 *
	 * Existing contacts are matched on email and are never overwritten with
	 * blank values — the help desk is treated as a supplementary source, not
	 * the authority on someone's name.
	 *
	 * @param array $customer Customer array from the API.
	 * @return int Contact ID, or 0 on failure.
	 */
	public function upsert_contact_from_customer( $customer ) {

		global $zbs;

		$email = $this->get_customer_email( $customer );

		if ( $email === '' || ! isset( $zbs->DAL ) ) {
			return 0;
		}

		$existing_id = $this->get_contact_id_by_email( $email );

		// Only pass through fields FreeScout actually gave us.
		$fields = array( 'email' => $email );

		if ( ! empty( $customer['firstName'] ) ) {
			$fields['fname'] = sanitize_text_field( $customer['firstName'] );
		}

		if ( ! empty( $customer['lastName'] ) ) {
			$fields['lname'] = sanitize_text_field( $customer['lastName'] );
		}

		if ( ! empty( $customer['company'] ) ) {
			$fields['company'] = sanitize_text_field( $customer['company'] );
		}

		$args = array(
			'id'                   => $existing_id > 0 ? $existing_id : -1,
			'data'                 => $fields,
			// Never blank out CRM data the help desk simply doesn't know about.
			'do_not_update_blanks' => true,
		);

		// Attribute new contacts to FreeScout, and keep the customer ID so the
		// link survives an email change on either side.
		if ( $existing_id < 1 && ! empty( $customer['id'] ) ) {
			$args['data']['externalSources'] = array(
				array(
					'source' => self::SOURCE,
					'uid'    => (string) absint( $customer['id'] ),
				),
			);
		}

		$contact_id = $zbs->DAL->contacts->addUpdateContact( $args ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return absint( $contact_id );
	}

	/**
	 * Write a FreeScout event into a contact's CRM activity timeline.
	 *
	 * Idempotent: a repeat webhook delivery carrying the same thread ID will
	 * not produce a second log entry.
	 *
	 * @param int    $contact_id CRM contact ID.
	 * @param string $type       Log type slug (see JPCRM_FS_CRM::log_types()).
	 * @param string $short_desc Short description.
	 * @param string $long_desc  Long description.
	 * @param array  $meta       Meta to store against the log.
	 * @param string $dedupe_key Meta key to dedupe on, e.g. 'fs_thread_id'.
	 * @return int|false Log ID, or false when skipped/failed.
	 */
	public function log_activity( $contact_id, $type, $short_desc, $long_desc = '', $meta = array(), $dedupe_key = '' ) {

		global $zbs;

		$contact_id = absint( $contact_id );

		if ( $contact_id < 1 || ! isset( $zbs->DAL ) ) {
			return false;
		}

		$args = array(
			'data' => array(
				'objtype'   => ZBS_TYPE_CONTACT,
				'objid'     => $contact_id,
				'type'      => $type,
				'shortdesc' => $short_desc,
				'longdesc'  => $long_desc,
				'meta'      => $meta,
			),
		);

		if ( $dedupe_key !== '' && isset( $meta[ $dedupe_key ] ) ) {
			$args['ignore_if_meta_matching'] = array(
				'key'   => $dedupe_key,
				'value' => $meta[ $dedupe_key ],
			);
		}

		return $zbs->DAL->logs->addUpdateLog( $args ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Link to a contact's CRM record.
	 *
	 * @param int $contact_id CRM contact ID.
	 * @return string
	 */
	public function contact_url( $contact_id ) {

		$contact_id = absint( $contact_id );

		if ( $contact_id < 1 ) {
			return '';
		}

		return add_query_arg(
			array(
				'page'   => 'zbs-add-edit',
				'action' => 'view',
				'zbsid'  => $contact_id,
			),
			admin_url( 'admin.php' )
		);
	}
}
