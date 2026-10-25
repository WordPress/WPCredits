<?php
/**
 * Claude access: the plugin's own MCP server, its door, the password fence and the log's daily sweep.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The part of Claude access (1.123.30) that is the site's own: who may connect, what a Claude password
 * may reach, what is kept of each call, and for how long.
 *
 * **The server.** The MCP Adapter plugin carries the protocol; this class tells it what to serve. The
 * adapter's default server is turned off, because it lists every ability that any plugin on the site
 * marks public, and the plugin creates one of its own instead: id `wpcredits`, at `/wp-json/mcp/wpcredits`,
 * over HTTP, listing the plugin's four abilities and nothing else. With the switch off no server is
 * created and the address answers 404 (design spec of 24 October 2026, decisions 3 to 5).
 *
 * **The door** is what the permission callback that the adapter calls with each request to that address
 * asks (`permit()`, which hands the answer over as a strict bool). It says yes only to an account that
 * signed in with an application password named Claude, holds the Administrators' capability and is on the
 * list of accounts allowed to connect, while the switch is on. Everything else is refused: WordPress
 * answers 401 to a request with no account at all, which writes nothing to the log, and 403 to a
 * signed-in one, which writes one row (decision 6). The door never throws.
 *
 * **The fence** is the other half of that rule. An Administrator's application password otherwise opens
 * every REST route that Administrator can use, the second factor passing by design, so a password named
 * Claude is held to the one address: any other REST route, the batch route included, answers 403, and a
 * sign-in with it anywhere but a REST request fails, XML-RPC's and that of any request a plugin opens to
 * application passwords (since 1.123.32). The fence does not look at the switch, and it needs no adapter:
 * it holds with the switch off, with the adapter deactivated and with the server not created (decision 7).
 *
 * **The log.** Every call to an ability writes one private row of the audit post type
 * (`WPCPM_Institution_Audit::record_mcp()`), and so does every refusal of a signed-in account
 * (`WPCPM_Abilities::refusal()`, the one writer the door and the fence share). The Security tab lists the
 * latest hundred and `wp wpcredits mcp_log` lists more; the sweep is what stops the rest growing for
 * ever. A daily event deletes the rows older than the **Keep the log for** setting, in batches, and touches
 * no other row of the audit post type: the institutions', the sponsors' and the program's histories have
 * rules of their own.
 *
 * A run clears the log in batches, because one account can write far more rows a day than one batch
 * deletes (600 answers and 1,440 capped refusals at the defaults), and a sweep that stopped at one batch
 * would fall behind for good.
 *
 * The job does not ask whether Claude access is on. The switch closes the abilities and the address; it
 * does not make the log that already exists any less old, and a log that outlives the switch is pruned all
 * the same.
 *
 * The adapter's classes are named here as strings and never loaded, so the file loads, and the fence
 * holds, with the adapter plugin absent. Nothing here reaches another address: the server is told what to
 * serve, the fence compares a route, and the sweep reads one setting and deletes posts of this site.
 */
class WPCPM_MCP {

	/** The id of the plugin's own MCP server. */
	const SERVER_ID = 'wpcredits';

	/** The namespace of its REST route; the adapter's default server uses the same one. */
	const ROUTE_NAMESPACE = 'mcp';

	/** Its route, so the address is `/wp-json/mcp/wpcredits`. */
	const ROUTE = 'wpcredits';

	/** The daily event that deletes the calls older than the retention setting. */
	const CRON_SWEEP = 'wpcpm_mcp_log_sweep';

	/** How many rows one batch of a run deletes, oldest first: each is one short query and its deletions. */
	const SWEEP_BATCH = 500;

	/**
	 * The most batches one run makes, so a run is long at worst 10,000 deletions: a log with more to go
	 * (a lowered setting, a site whose cron was late for weeks) is cleared over a few days' runs.
	 */
	const SWEEP_BATCHES = 20;

	/** The fewest days a run keeps, the least the setting itself allows. */
	const KEEP_FLOOR = 7;

	/**
	 * Hooks: the server, the fence, the sweep on its event and the event on the clock.
	 *
	 * The adapter's default server is answered false whatever the switch says, and at the last priority
	 * there is, so no ordinary filter that other code adds can turn it back on. The REST fence runs before
	 * the other filters of `rest_pre_dispatch` (priority 1, ahead of the privacy guard's and core's), so no
	 * route is dispatched until the fence has had its say.
	 *
	 * The event is put on the clock at `init`, as the email log's cleanup is, so a site that updates the
	 * plugin without deactivating it has the job on its next request, and one that reactivates it has it
	 * back.
	 */
	public static function init() {
		add_filter( 'mcp_adapter_create_default_server', array( __CLASS__, 'default_server' ), PHP_INT_MAX );
		add_action( 'mcp_adapter_init', array( __CLASS__, 'create_server' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'fence_rest' ), 1, 3 );
		add_action( 'wp_authenticate_application_password_errors', array( __CLASS__, 'fence_sign_in' ), 10, 4 );

		add_action( self::CRON_SWEEP, array( __CLASS__, 'sweep' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * The answer to the adapter's question whether it should create its default server: no.
	 *
	 * That server lists every ability that core or any plugin marks public, and the site has no use for
	 * it. Its address, `/wp-json/mcp/mcp-adapter-default-server`, then answers 404, and the adapter's
	 * three discovery tools do not exist on the site.
	 *
	 * @return bool Always false.
	 */
	public static function default_server() {
		return false;
	}

	/**
	 * Create the plugin's own MCP server, when the adapter starts (`mcp_adapter_init`).
	 *
	 * With the switch off nothing is created, so the address answers 404. The tools are the plugin's four
	 * abilities, named one by one, and no resource and no prompt: a server built this way serves exactly
	 * what it is given, so no core ability and no ability of another plugin can appear on it, whatever
	 * they declare. The transport and the error handler are the adapter's own, named as strings so this
	 * file loads without the adapter; no observability handler is given. The transport's permission
	 * callback is `permit()`, the door in the one form that cannot be misread.
	 *
	 * The call runs on every REST request, so an adapter that throws (a registry that is broken, a version
	 * that changed the method) is caught here, and so is a read of the switch that throws: the server is not
	 * created, and the site's REST API goes on. A server that could not be created, because the adapter
	 * answered with an error or anything here threw, leaves one line in PHP's error log with the error's code
	 * or the exception's class, at most one an hour (`WPCPM_Abilities::trace()`).
	 *
	 * @param object $adapter The MCP Adapter, which takes `create_server()`.
	 */
	public static function create_server( $adapter ) {
		try {
			if ( ! WPCPM_Abilities::enabled() || ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
				return;
			}

			$created = $adapter->create_server(
				self::SERVER_ID,
				self::ROUTE_NAMESPACE,
				self::ROUTE,
				__( 'WPCredits Program', 'wpcredits-program-manager' ),
				__( 'Read-only answers from the WPCredits Program Manager\'s own records.', 'wpcredits-program-manager' ),
				WPCPM_VERSION,
				array( 'WP\MCP\Transport\HttpTransport' ),
				'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
				null,
				WPCPM_Abilities::NAMES,
				array(),
				array(),
				array( __CLASS__, 'permit' )
			);
		} catch ( Throwable $e ) {
			// No server is a closed address; it is not a reason to fail the request that asked for one.
			WPCPM_Abilities::trace( 'server', $e );

			return;
		}

		if ( is_wp_error( $created ) ) {
			WPCPM_Abilities::trace( 'server', (string) $created->get_error_code() );
		}
	}

	/**
	 * The transport's permission callback: the door as a strict bool, true to let the request in and false
	 * for anything else.
	 *
	 * `door()` answers a refusal with a `WP_Error`, which the tests and the log read by its code, and which
	 * PHP counts as true. What the adapter does with an error from a permission callback is the adapter's
	 * business and has changed between versions (0.7.1 denies it), so the plugin does not hand it one: the
	 * adapter is given this, which can only be `true` or `false`, and WordPress then answers 401 to a
	 * request with no account and 403 to a signed-in one. The door's refusal rows are written by `door()`
	 * all the same. The door cannot throw, so neither can this.
	 *
	 * @param WP_REST_Request|mixed $request The request, passed on to the door.
	 * @return bool
	 */
	public static function permit( $request = null ) {
		return true === self::door( $request );
	}

	/**
	 * The door: who may use the MCP address, asked with each request to it by `permit()`, the transport's
	 * permission callback.
	 *
	 * The grounds are read in this order, and the first that applies is the answer: no account at all;
	 * the switch off; a sign-in that is not an application password named Claude (a browser session, a
	 * password with another name, a password whose name cannot be read); an account without the
	 * Administrators' capability; an account that is not on the list of those allowed to connect. WordPress
	 * turns an error from here into 401 for a request with no account and 403 for a signed-in one.
	 *
	 * A request with no account writes no row, so that a stranger cannot fill the log, and the allowed case
	 * writes none because the answer's own row is the ability's. Every other refusal writes one, through
	 * `WPCPM_Abilities::refusal()`, which holds an account to 60 rows an hour; and a request is one row
	 * however many times core asks the door about it (`refused()`).
	 *
	 * Nothing that goes wrong in here can let a request through or end it with an exception. The adapter's
	 * guide says an exception in this callback lets any signed-in account in, and although its 0.7.1 code
	 * denies instead, the plugin does not lean on either: a check that throws is a refusal with the code
	 * `error`, and a row that cannot be written changes neither the ground nor the answer.
	 *
	 * @param WP_REST_Request|mixed $request The request. Not needed: who is asking is the signed-in
	 *                                       account, not anything in the request.
	 * @return true|WP_Error True to let the request in, else why not; the code is the ground.
	 */
	public static function door( $request = null ) {
		unset( $request );

		try {
			if ( get_current_user_id() < 1 ) {
				return new WP_Error( 'wpcpm_mcp_signed_out', __( 'Sign in to use this address.', 'wpcredits-program-manager' ) );
			}

			$reason = self::door_refusal();

			return '' === $reason ? true : self::refused( $reason );
		} catch ( Throwable $e ) {
			return self::refused( 'error' );
		}
	}

	/**
	 * The ground the door refuses a signed-in account on, or '' to let it in.
	 *
	 * @return string `switch_off`, `wrong_password`, `not_manager`, `not_allowed` or ''.
	 */
	private static function door_refusal() {
		if ( ! (bool) WPCPM_Settings::get_value( 'mcp_enabled' ) ) {
			return 'switch_off';
		}

		$password = self::current_password();

		if ( ! self::is_claude_password( isset( $password['name'] ) ? $password['name'] : '' ) ) {
			return 'wrong_password';
		}

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return 'not_manager';
		}

		$allowed = array_map( 'intval', (array) WPCPM_Settings::get_value( 'mcp_users', array() ) );

		return in_array( get_current_user_id(), $allowed, true ) ? '' : 'not_allowed';
	}

	/**
	 * A refusal of the door: its row written, if it can be, and the error to answer with.
	 *
	 * One refused request is one row. Once the route's handler has refused a request, core's
	 * `rest_send_allow_header()` runs on `rest_post_dispatch`, for every matched route and an error
	 * response included, and asks the route's permission callback again for each method the route allows;
	 * the adapter registers one handler for POST, GET and DELETE, so the door answers up to four times for
	 * one request. The refusal is the same each time, but the row is written only by the first, the one in
	 * dispatch, and none while `rest_post_dispatch` is running.
	 *
	 * @param string $reason The ground; also the error's code.
	 * @return WP_Error
	 */
	private static function refused( $reason ) {
		if ( ! ( function_exists( 'doing_filter' ) && doing_filter( 'rest_post_dispatch' ) ) ) {
			self::note( $reason );
		}

		// The sentence is never shown to a person (the adapter turns the error into false), and a filter on
		// translations is other code: if it throws, the refusal stands without its sentence.
		try {
			$message = __( 'This sign-in cannot use this address.', 'wpcredits-program-manager' );
		} catch ( Throwable $e ) {
			$message = '';
		}

		return new WP_Error( $reason, $message );
	}

	/**
	 * Write a refusal's row, and let nothing that goes wrong in the writing out of it.
	 *
	 * @param string $reason The ground.
	 * @param string $route  The route a fenced request asked for, or ''.
	 */
	private static function note( $reason, $route = '' ) {
		try {
			WPCPM_Abilities::refusal( $reason, $route );
		} catch ( Throwable $e ) {
			unset( $e ); // A log that cannot be written is not a reason to answer differently.
		}
	}

	/**
	 * Whether an application password's name makes it a Claude password: it begins with Claude.
	 *
	 * Case does not matter and white space before the name does not, so " claude - laptop" and "CLAUDE" are
	 * Claude passwords; the word must end where it ends, so "Claudette" and "My Claude" are not, and
	 * neither is no name at all. The name is a label that its holder types, not a secret, and the rule only
	 * narrows what a password may do: a password named otherwise simply does not open the MCP address, so a
	 * mistake fails closed. The door and both fences ask this one question, so a password is a Claude
	 * password for all of them or for none.
	 *
	 * @param mixed $name The application password's name.
	 * @return bool
	 */
	public static function is_claude_password( $name ) {
		return is_string( $name ) && 1 === preg_match( '/^\s*claude(?![a-z])/i', $name );
	}

	/**
	 * The application password that signed the current request: its `name` and `uuid`, or an empty array
	 * when none did, or when the account no longer holds it. The abilities' own lookup, so a call and a
	 * refusal read the same password the same way.
	 *
	 * @return array
	 */
	public static function current_password() {
		return WPCPM_Abilities::password();
	}

	/**
	 * Whether an application password signed the current request, whether or not its name can be read.
	 *
	 * @return bool
	 */
	private static function signed_with_application_password() {
		if ( ! function_exists( 'rest_get_authenticated_app_password' ) ) {
			return false;
		}

		$uuid = rest_get_authenticated_app_password();

		return is_string( $uuid ) && '' !== $uuid;
	}

	/**
	 * The REST fence, on `rest_pre_dispatch` before any other filter: a Claude password opens the MCP
	 * address and no other route.
	 *
	 * What it refuses is the password, not the route, and the route is an allowlist of one: `/mcp/wpcredits`
	 * and `/mcp/wpcredits/` pass, and everything else answers 403, the batch route (a Claude password
	 * could otherwise ask for any route through it) and the REST index included. The route is compared as
	 * it is, case and all, so a spelling that only resembles the address is not the address: a trailing
	 * segment, another case, a longer name. WordPress would route some of those to the MCP handler
	 * all the same, and those requests get a 403 for it, which is the safe side of the doubt.
	 *
	 * A request signed with an application password whose item cannot be found has no name to read, and is
	 * fenced as a Claude password would be: it cannot be shown not to be one. The door refuses it on the
	 * address itself.
	 *
	 * The fence ignores the switch, so turning Claude access off never opens anything. A refusal writes a
	 * row with the route, up to the cap of 60 an hour per account.
	 *
	 * A result that an earlier filter already set does not change that. A filter can only run before this
	 * one at a lower priority than 1 (a REST cache, say), and what it set would be served as the answer, so
	 * on a fenced route a result that is not an error is replaced with the 403, and so is an empty one
	 * (`false`, `0`, `''`, an empty list), which does not stop the dispatch. An error set earlier is
	 * returned as it is: it refuses the request already. On the MCP address, and for any password that is
	 * not a Claude password, every result is returned as it was.
	 *
	 * @param mixed                 $result  The response so far, null to carry on.
	 * @param WP_REST_Server|mixed  $server  The server. Not needed.
	 * @param WP_REST_Request|mixed $request The request being dispatched.
	 * @return mixed The result handed in, or `wpcpm_mcp_fenced` (403).
	 */
	public static function fence_rest( $result, $server, $request ) {
		unset( $server );

		if ( ! self::signed_with_application_password() ) {
			return $result;
		}

		$password = self::current_password();

		if ( array() !== $password && ! self::is_claude_password( isset( $password['name'] ) ? $password['name'] : '' ) ) {
			return $result;
		}

		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';

		if ( in_array( $route, array( '/' . self::ROUTE_NAMESPACE . '/' . self::ROUTE, '/' . self::ROUTE_NAMESPACE . '/' . self::ROUTE . '/' ), true ) ) {
			return $result;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::note( 'fenced', $route );

		return new WP_Error( 'wpcpm_mcp_fenced', self::fenced_message(), array( 'status' => 403 ) );
	}

	/**
	 * The sign-in fence, on `wp_authenticate_application_password_errors`: a Claude password signs in to
	 * nothing but a REST request.
	 *
	 * Core checks an application password in a REST or an XML-RPC request, and in any other request that a
	 * plugin opens to application passwords through `application_password_is_api_request`: Jetpack opens its
	 * VideoPress AJAX actions in wp-admin that way, so an Administrator's password, a Claude one among them,
	 * would sign in there too. Core fires this action once the password has matched, in every one of those
	 * requests; an error added to `$error` fails the sign-in. A REST request (`REST_REQUEST`, which core
	 * defines before it serves one) is the other fence's, which holds a Claude password to the MCP address;
	 * every other sign-in with a Claude password fails here, whatever the switch says, so a plugin that opens
	 * one more door to application passwords does not open it to Claude. A request that only became a REST
	 * request after its sign-in was checked is refused too: the safe side of the doubt. Core alone never
	 * does that: it checks no application password before it defines `REST_REQUEST`, and the REST server
	 * works the account out again once it has. Its cost is a plugin that opens application passwords to a
	 * REST request before core knows it is one (by matching `/wp-json/` in the address, say): core then
	 * keeps this refusal and answers the MCP address 401, so Claude cannot connect on such a site until
	 * that plugin stops. Jetpack's door does not, as `is_admin()` is false in a REST request.
	 *
	 * It writes no row, because core fires the action while it is still working out who is signing in,
	 * before there is a current user, and the row writer reads the current user.
	 *
	 * @param WP_Error|mixed $error    The errors so far; one added here fails the sign-in.
	 * @param WP_User|mixed  $user     The account whose password matched. Not needed.
	 * @param array|mixed    $item     The password's stored item: `name`, `uuid` and so on.
	 * @param string         $password The password as given. Not needed, and never read.
	 */
	public static function fence_sign_in( $error, $user, $item, $password ) {
		unset( $user, $password );

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_wp_error( $error ) || ! is_array( $item ) ) {
			return;
		}

		if ( self::is_claude_password( isset( $item['name'] ) ? $item['name'] : '' ) ) {
			$error->add( 'wpcpm_mcp_fenced', self::fenced_message() );
		}
	}

	/**
	 * What a Claude password is told when it asks for anything but the MCP address.
	 *
	 * @return string
	 */
	private static function fenced_message() {
		return __( 'This password opens only the WPCredits MCP address.', 'wpcredits-program-manager' );
	}

	/**
	 * Put the sweep on the clock, daily, when it is not there already.
	 *
	 * The first run is an hour away rather than now, so a request that has just updated the plugin does
	 * not also run a deletion.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_SWEEP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_SWEEP );
		}
	}

	/**
	 * Delete the calls that are older than the retention setting.
	 *
	 * The setting is read on each run, so a change takes effect at the next sweep, and it is floored at
	 * `KEEP_FLOOR` days where it is used: the settings do not clamp what they read, and a stored 0, a
	 * negative number or text would otherwise make the cutoff now and empty the whole log. The cutoff is
	 * worked out once, so a long run does not chase an edge that moves while it runs. Batches of
	 * `SWEEP_BATCH` rows, the oldest first, repeat while a batch is full, up to `SWEEP_BATCHES` of them;
	 * the rest, if any, go at the next run.
	 *
	 * @return int How many rows were deleted in all.
	 */
	public static function sweep() {
		$days   = max( self::KEEP_FLOOR, (int) WPCPM_Settings::get_value( 'mcp_log_days', 90 ) );
		$before = time() - $days * DAY_IN_SECONDS;
		$total  = 0;

		for ( $batch = 0; $batch < self::SWEEP_BATCHES; $batch++ ) {
			$gone   = WPCPM_Institution_Audit::delete_mcp_before( $before, self::SWEEP_BATCH );
			$total += $gone;

			if ( self::SWEEP_BATCH !== $gone ) {
				break;
			}
		}

		return $total;
	}

	/**
	 * Deactivation: the sweep off the clock. A daily event with no listener is what deactivating the
	 * plugin would otherwise leave behind; uninstall clears it too, for a plugin deleted from a state
	 * where this never ran.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_SWEEP );
	}
}
