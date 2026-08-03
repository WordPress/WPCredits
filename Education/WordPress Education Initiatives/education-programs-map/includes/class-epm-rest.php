<?php
/**
 * REST API endpoint that feeds the public-facing map.
 *
 * @package Education_Programs_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPM_REST {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'education-programs-map/v1',
			'/institutions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_institutions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'program' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'education-programs-map/v1',
			'/programs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_programs' ),
				'permission_callback' => array( $this, 'edit_permission_check' ),
			)
		);
	}

	/**
	 * Restrict the /programs route to users who can edit posts (i.e. block editor users).
	 *
	 * @return bool
	 */
	public function edit_permission_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Return the program key => label map, used to populate the block editor's Program control.
	 *
	 * @return WP_REST_Response
	 */
	public function get_programs() {
		return new WP_REST_Response( EPM_DB::get_programs(), 200 );
	}

	/**
	 * Return institutions as a JSON-friendly array for the frontend map.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_institutions( WP_REST_Request $request ) {
		$program      = $request->get_param( 'program' );
		$institutions = EPM_DB::get_all( is_string( $program ) ? $program : '', true );
		$programs     = EPM_DB::get_programs();

		$data = array_map(
			function ( $institution ) use ( $programs ) {
				$program_keys = EPM_DB::parse_programs( $institution->programs );

				return array(
					'id'             => (int) $institution->id,
					'name'           => $institution->name,
					'city'           => $institution->city,
					'country'        => $institution->country,
					'latitude'       => (float) $institution->latitude,
					'longitude'      => (float) $institution->longitude,
					'programs'       => $program_keys,
					'programLabels'  => array_map(
						function ( $key ) use ( $programs ) {
							return $programs[ $key ] ?? $key;
						},
						$program_keys
					),
					'eventCount'     => (int) $institution->event_count,
					'website'        => esc_url_raw( $institution->website ),
					'wpccUrl'        => esc_url_raw( $institution->wpcc_url ),
					'studentClubUrl' => esc_url_raw( $institution->student_club_url ),
				);
			},
			$institutions
		);

		return new WP_REST_Response( $data, 200 );
	}
}
