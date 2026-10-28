<?php
/**
 * A read-only REST route for the headline totals: GET /wp-json/wpcct/v1/totals.
 *
 * It exists so the job that copies these figures onto the dotorg P2 page can run
 * somewhere other than the owner's Mac. Until now that job read wpcct_data over
 * SSH, which needs a shell key, and the dashboard page cannot be fetched instead
 * because Coming soon mode hides it from anyone signed out.
 *
 * Two ways in, and nothing else:
 *   - a signed-in user who can read this site, i.e. someone who can already see
 *     the same figures on the dashboard page; or
 *   - a token in the X-WPCCT-Token header. Only its sha256 is stored, it opens
 *     this one route and no other, and it is made and revoked with
 *     `wp wpcct api-token`. Application passwords are not available on this
 *     host, which is why the route carries its own credential.
 *
 * The token goes in a header, never the query string, so it stays out of access
 * logs and caches. The response is marked no-store for the same reason: the
 * planning figure counts applications Central does not publish.
 *
 * @package WPCC_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCCT_Rest {

	const NAMESPACE_V1 = 'wpcct/v1';
	const OPT_TOKEN    = 'wpcct_api_token_hash';
	const HEADER       = 'X-WPCCT-Token';

	/** @var WPCCT_Rest|null */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return WPCCT_Rest
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register the route.
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/totals',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'totals' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * Signed-in readers, or a request carrying the current token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_read( $request ) {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}

		if ( self::token_matches( (string) $request->get_header( self::HEADER ), get_option( self::OPT_TOKEN, '' ) ) ) {
			return true;
		}

		return new WP_Error(
			'wpcct_forbidden',
			__( 'Sign in, or send a valid X-WPCCT-Token header.', 'wpcc-tracker' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * The totals, or a 503 when there is no synced reading to give.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function totals() {
		$payload = self::payload( get_option( WPCCT_OPT_DATA, array() ), time() );

		if ( null === $payload ) {
			return new WP_Error(
				'wpcct_no_data',
				__( 'No synced figures yet.', 'wpcc-tracker' ),
				array( 'status' => 503 )
			);
		}

		$response = new WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/**
	 * Build the response from the stored blob.
	 *
	 * The keys are the ones the SSH reading has always printed, so a caller can
	 * switch from one to the other without changing anything downstream. A blob
	 * with no totals gives null rather than a row of zeros: a reader must be able
	 * to tell "nothing synced" from "nothing happened".
	 *
	 * @param mixed $data The wpcct_data option.
	 * @param int   $now  Current timestamp; passed in so tests can pin the year.
	 * @return array|null
	 */
	public static function payload( $data, $now ) {
		if ( ! is_array( $data ) || empty( $data['totals'] ) || ! is_array( $data['totals'] ) ) {
			return null;
		}

		$t      = $data['totals'];
		$synced = isset( $data['source_synced'] ) ? (string) $data['source_synced'] : '';
		$ts     = '' === $synced ? false : strtotime( $synced );

		$out = array();
		foreach ( array( 'completed', 'completed_this_year', 'scheduled', 'planning', 'attendees', 'institutions', 'countries' ) as $key ) {
			$out[ $key ] = isset( $t[ $key ] ) ? (int) $t[ $key ] : 0;
		}

		$out['year']              = gmdate( 'Y', $now );
		$out['as_of']             = $ts ? gmdate( 'j F Y', $ts ) : '';
		$out['source_synced']     = $synced;
		$out['unmapped_statuses'] = ( isset( $data['unmapped_statuses'] ) && is_array( $data['unmapped_statuses'] ) ) ? $data['unmapped_statuses'] : array();

		return $out;
	}

	/**
	 * Constant-time check of a presented token against the stored hash.
	 *
	 * An empty stored hash means no token has been made, or it was revoked, and
	 * nothing matches it, including an empty header.
	 *
	 * @param string $given  Token from the request header.
	 * @param string $stored sha256 hex of the current token.
	 * @return bool
	 */
	public static function token_matches( $given, $stored ) {
		if ( ! is_string( $stored ) || '' === $stored || ! is_string( $given ) || '' === $given ) {
			return false;
		}
		return hash_equals( $stored, hash( 'sha256', $given ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Manage the token for GET /wp-json/wpcct/v1/totals.
	 */
	class WPCCT_Api_Token_Command {

		/**
		 * Make a new token, replacing any old one. Prints it once; only its hash is kept.
		 *
		 * @subcommand create
		 */
		public function create() {
			$token = wp_generate_password( 48, false, false );
			update_option( WPCCT_Rest::OPT_TOKEN, hash( 'sha256', $token ), false );
			WP_CLI::log( $token );
			WP_CLI::success( 'Token created. It is shown once; any earlier token no longer works.' );
		}

		/**
		 * Revoke the token. Signed-in readers keep access.
		 *
		 * @subcommand revoke
		 */
		public function revoke() {
			delete_option( WPCCT_Rest::OPT_TOKEN );
			WP_CLI::success( 'Token revoked.' );
		}

		/**
		 * Say whether a token is set, without showing anything about it.
		 *
		 * @subcommand status
		 */
		public function status() {
			WP_CLI::log( get_option( WPCCT_Rest::OPT_TOKEN, '' ) ? 'A token is set.' : 'No token is set.' );
		}
	}

	WP_CLI::add_command( 'wpcct api-token', 'WPCCT_Api_Token_Command' );
}
