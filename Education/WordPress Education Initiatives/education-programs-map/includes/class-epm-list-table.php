<?php
/**
 * List table for browsing institutions in the Dashboard.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class EPM_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'institution',
				'plural'   => 'institutions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'name'        => __( 'Institution', 'education-programs-map' ),
			'city'        => __( 'City', 'education-programs-map' ),
			'country'     => __( 'Country', 'education-programs-map' ),
			'program'     => __( 'Program', 'education-programs-map' ),
			'event_count' => __( 'Events', 'education-programs-map' ),
			'coordinates' => __( 'Coordinates', 'education-programs-map' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'    => array( 'name', false ),
			'city'    => array( 'city', false ),
			'program' => array( 'program', false ),
		);
	}

	public function no_items() {
		esc_html_e( 'No institutions have been added yet.', 'education-programs-map' );
	}

	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, no state change.
		$program = isset( $_REQUEST['program'] ) ? sanitize_key( wp_unslash( $_REQUEST['program'] ) ) : '';

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = EPM_DB::get_all( $program );
	}

	protected function column_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page' => 'epm-add-new',
				'id'   => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'epm-institutions',
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'epm_delete_institution_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'education-programs-map' ) ),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this institution? This cannot be undone.', 'education-programs-map' ) ),
				esc_html__( 'Delete', 'education-programs-map' )
			),
		);

		$hidden_badge = '';
		if ( ! empty( $item->hidden ) ) {
			$hidden_badge = ' <span class="epm-hidden-badge" title="' . esc_attr__( 'Hidden from the public map — its Airtable record is no longer Confirmed.', 'education-programs-map' ) . '">' . esc_html__( 'Hidden', 'education-programs-map' ) . '</span>';
		}

		return sprintf( '<strong><a href="%s">%s</a></strong>%s%s', esc_url( $edit_url ), esc_html( $item->name ), $hidden_badge, $this->row_actions( $actions ) );
	}

	protected function column_program( $item ) {
		$all_programs = EPM_DB::get_programs();
		$keys         = EPM_DB::parse_programs( $item->programs );

		if ( empty( $keys ) ) {
			return '&#8212;';
		}

		$labels = array_map(
			function ( $key ) use ( $all_programs ) {
				return isset( $all_programs[ $key ] ) ? $all_programs[ $key ] : $key;
			},
			$keys
		);

		return esc_html( implode( ', ', $labels ) );
	}

	protected function column_coordinates( $item ) {
		return esc_html( $item->latitude . ', ' . $item->longitude );
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}
}
