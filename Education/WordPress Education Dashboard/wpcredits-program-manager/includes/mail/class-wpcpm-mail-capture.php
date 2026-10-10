<?php
/**
 * Every email the site sends, written to the mail log as it goes.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns one email into one log row per recipient.
 *
 * `wp_mail()` runs these hooks, in order: the `wp_mail` filter; `pre_wp_mail`, which another plugin
 * may answer to take the email over (the local copies do, to swallow their mail), and then neither
 * outcome follows; and one of `wp_mail_succeeded` and `wp_mail_failed`. This listens to all four:
 *
 * - **First on `wp_mail`** (`open()`), the email is named and put on a stack: by the plugin's
 *   context (`WPCPM_Mail::take_context()`), set just before the plugin calls `wp_mail()`; else by
 *   the WordPress or Two Factor filter it passed through just before
 *   (`WPCPM_Mail_Catalog::wordpress_filters()`); else as Other. Both are taken there, so neither
 *   can name a later email, and an email another plugin sends from its own `wp_mail` filter, after
 *   this, finds them gone.
 * - **Last on `wp_mail`** (`note()`), the email on top of the stack takes its recipients, headers
 *   and subject as the filters left them, which are what is sent.
 * - **Last on `pre_wp_mail`** (`taken_over()`), an answer other than null writes the email on top
 *   as Not confirmed.
 * - **First on each outcome** (`succeeded()`, `failed()`), the email on top is written with its
 *   status, before another plugin's listener can send one of its own. The rows are read from the
 *   email as noted, never from what the outcome hook carries: WordPress has parsed its headers by
 *   then, and Cc, Bcc, From and Reply-To are no longer among them.
 * - **On `shutdown`** (`flush()`), an email still on the stack, whose outcome never came (a
 *   replacement `wp_mail()` that fires no outcome, or a fatal error mid-send), is written as Not
 *   confirmed under its own name.
 * - **On `wp_php_error_args`** (`flush_on_fatal()`), the same, for a request that dies with a fatal
 *   error. Core's fatal error handler is a shutdown function registered before WordPress's own
 *   `shutdown` action, and it applies that filter just before it calls `wp_die()` for the
 *   "critical error" page. It asks `wp_die()` not to exit, so `shutdown` normally still follows; but
 *   a `wp_die` handler that ends the request would skip it, and with it `flush()`. The flush here is
 *   guarded: a throwable inside it is swallowed, since a request in a fatal state must not be made
 *   worse. A second flush at `shutdown`, if it runs, finds the stack empty and writes nothing.
 *
 * A stack rather than one slot, because one email can be sent while another is on its way: from
 * another plugin's `wp_mail` filter, or from its outcome listener. Each finishes on top of the one
 * it interrupted, and each is written under its own name.
 */
final class WPCPM_Mail_Capture {

	/**
	 * The email a WordPress or Two Factor filter named last, not yet taken.
	 *
	 * @var string
	 */
	private static $hint = '';

	/**
	 * The emails noted at `wp_mail` and waiting for their outcome, the one sent last on top.
	 *
	 * @var array[]
	 */
	private static $stack = array();

	/**
	 * Hooks. Booted from the plugin's bootstrap, through `WPCPM_Mail_Log::init()`.
	 */
	public static function init() {
		add_filter( 'wp_mail', array( __CLASS__, 'open' ), PHP_INT_MIN );
		add_filter( 'wp_mail', array( __CLASS__, 'note' ), PHP_INT_MAX );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'taken_over' ), PHP_INT_MAX, 2 );
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'succeeded' ), PHP_INT_MIN );
		add_action( 'wp_mail_failed', array( __CLASS__, 'failed' ), PHP_INT_MIN );
		add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		add_filter( 'wp_php_error_args', array( __CLASS__, 'flush_on_fatal' ), PHP_INT_MIN );

		foreach ( WPCPM_Mail_Catalog::wordpress_filters() as $filter => $id ) {
			add_filter(
				$filter,
				static function ( $value ) use ( $id ) {
					self::$hint = $id;

					return $value;
				},
				PHP_INT_MAX
			);
		}
	}

	/**
	 * The `wp_mail` filter, first: name the email and put it on the stack. Changes nothing.
	 *
	 * @param array $atts to, subject, message, headers, attachments, embeds.
	 * @return array The same.
	 */
	public static function open( $atts ) {
		$context = class_exists( 'WPCPM_Mail' ) ? (string) WPCPM_Mail::take_context() : '';
		$id      = '' !== $context ? $context : ( '' !== self::$hint ? self::$hint : WPCPM_Mail_Catalog::OTHER );

		self::$hint    = '';
		self::$stack[] = array_merge( array( 'template' => $id ), self::email( $atts ) );

		return $atts;
	}

	/**
	 * The `wp_mail` filter, last: the email on top takes its recipients, headers and subject as the
	 * other filters left them. Changes nothing.
	 *
	 * @param array $atts to, subject, message, headers, attachments, embeds.
	 * @return array The same.
	 */
	public static function note( $atts ) {
		$top = count( self::$stack ) - 1;

		if ( $top >= 0 ) {
			self::$stack[ $top ] = array_merge( self::$stack[ $top ], self::email( $atts ) );
		}

		return $atts;
	}

	/**
	 * The parts of an email the log keeps, as the `wp_mail` filter holds them.
	 *
	 * @param mixed $atts The filter's value.
	 * @return array to, headers, subject.
	 */
	private static function email( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();

		return array(
			'to'      => isset( $atts['to'] ) ? $atts['to'] : '',
			'headers' => isset( $atts['headers'] ) ? $atts['headers'] : array(),
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
		);
	}

	/**
	 * The `pre_wp_mail` filter, last: an answer other than null means another plugin took the email
	 * over, and WordPress will say nothing more about it.
	 *
	 * @param mixed $answer Null, or another plugin's answer.
	 * @param array $atts   The email.
	 * @return mixed The same answer.
	 */
	public static function taken_over( $answer, $atts = array() ) {
		if ( null !== $answer ) {
			self::write( WPCPM_Mail_Log::STATUS_UNCONFIRMED, '' );
		}

		return $answer;
	}

	/**
	 * The email went to the mail server.
	 *
	 * @param array $mail_data WordPress's account of the email, its headers already parsed; not read.
	 */
	public static function succeeded( $mail_data ) {
		self::write( WPCPM_Mail_Log::STATUS_SENT, '' );
	}

	/**
	 * The email failed.
	 *
	 * @param WP_Error $error WordPress's error; its message is kept.
	 */
	public static function failed( $error ) {
		self::write( WPCPM_Mail_Log::STATUS_FAILED, is_wp_error( $error ) ? (string) $error->get_error_message() : '' );
	}

	/**
	 * At the end of the request, every email whose outcome never came, as Not confirmed.
	 */
	public static function flush() {
		while ( self::$stack ) {
			self::write( WPCPM_Mail_Log::STATUS_UNCONFIRMED, '' );
		}
	}

	/**
	 * The `wp_php_error_args` filter, which core's fatal error handler applies just before it calls
	 * `wp_die()`: write every email still open, as `flush()` does at shutdown, in case the request
	 * ends before `shutdown` runs. Changes nothing, and lets nothing out: a request in a fatal state
	 * must not be made worse, so a throwable inside the flush is swallowed.
	 *
	 * @param array $args The arguments core passes to `wp_die()`.
	 * @return array The same.
	 */
	public static function flush_on_fatal( $args ) {
		try {
			self::flush();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Swallowed on purpose: the page core is about to draw matters more than the log.
			// Nothing to do.
		}

		return $args;
	}

	/**
	 * Write the email on top of the stack, one row per recipient, and take it off.
	 *
	 * @param string $status One of the log's statuses.
	 * @param string $error  WordPress's message for a failure.
	 */
	private static function write( $status, $error ) {
		$email = array_pop( self::$stack );

		if ( null === $email ) {
			return;
		}

		$entry = WPCPM_Mail_Catalog::get( $email['template'] );
		$id    = null === $entry ? WPCPM_Mail_Catalog::OTHER : $email['template'];

		foreach ( self::recipients( $email['to'], $email['headers'] ) as $address ) {
			$user = get_user_by( 'email', $address );
			$user = $user instanceof WP_User && $user->exists() ? $user : null;

			WPCPM_Mail_Log::add(
				array(
					'sent_at'        => gmdate( 'Y-m-d H:i:s' ),
					'to_email'       => $address,
					'user_id'        => $user ? (int) $user->ID : null,
					'to_name'        => $user ? (string) $user->display_name : '',
					'recipient_type' => self::type_of( $user, null === $entry ? '' : $entry['audience'] ),
					'module'         => WPCPM_Mail_Catalog::module_of( $id ),
					'template'       => $id,
					'subject'        => $email['subject'],
					'status'         => $status,
					'error'          => $error,
					'is_test'        => null !== $entry && $entry['test'],
				)
			);
		}
	}

	/**
	 * Every address an email goes to: To, then Cc, then Bcc; each once, without regard to case; a
	 * `Name <address>` reduced to its address; blanks and anything `is_email()` does not accept as
	 * written dropped (an address with a space in it, or two @, is not repaired). Read the way
	 * `wp_mail()` reads them: `to` split on commas, and each header a `Name: value` line, from one
	 * string or an array of lines.
	 *
	 * @param string|string[] $to      What `wp_mail()` was given as `to`.
	 * @param string|string[] $headers Its headers, as one string or an array of lines.
	 * @return string[]
	 */
	public static function recipients( $to, $headers ) {
		$list  = is_array( $to ) ? $to : explode( ',', (string) $to );
		$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );

		foreach ( $lines as $line ) {
			if ( is_string( $line ) && preg_match( '/^\s*(cc|bcc)\s*:(.*)$/i', $line, $m ) ) {
				$list = array_merge( $list, explode( ',', $m[2] ) );
			}
		}

		$out  = array();
		$seen = array();

		foreach ( $list as $item ) {
			$item = trim( (string) $item );

			if ( preg_match( '/<([^>]*)>/', $item, $m ) ) {
				$item = trim( $m[1] );
			}

			// As written, not as sanitize_email() would repair it: an address `wp_mail()` would not
			// accept as given is not one the site sent to.
			$address = is_email( $item );
			$address = is_string( $address ) ? $address : '';
			$key     = strtolower( $address );

			if ( '' === $address || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $address;
		}

		return $out;
	}

	/**
	 * Who a recipient is, as the Log shows it.
	 *
	 * @param WP_User|null $user     The account, or null.
	 * @param string       $audience The email's audience, or '' when the account decides.
	 * @return string One of the catalog's types.
	 */
	public static function type_of( $user, $audience ) {
		if ( ! $user instanceof WP_User ) {
			return '' !== (string) $audience ? (string) $audience : WPCPM_Mail_Catalog::TYPE_OTHER;
		}

		$roles = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => in_array( WPCPM_Roles::ROLE_STUDENT, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_MENTOR      => in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => in_array( WPCPM_Roles::ROLE_INSTITUTION, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => in_array( WPCPM_Roles::ROLE_SPONSOR, (array) $user->roles, true ),
			WPCPM_Mail_Catalog::TYPE_ADMIN       => in_array( WPCPM_Roles::ROLE_ADMIN, (array) $user->roles, true ) || user_can( $user, WPCPM_Roles::CAP_MANAGE ),
		);

		if ( '' !== (string) $audience && ! empty( $roles[ $audience ] ) ) {
			return (string) $audience;
		}

		foreach ( $roles as $type => $has ) {
			if ( $has ) {
				return $type;
			}
		}

		return WPCPM_Mail_Catalog::TYPE_OTHER;
	}

	/**
	 * Forget the emails on the stack and the hint. Test-only.
	 */
	public static function reset() {
		self::$hint  = '';
		self::$stack = array();
	}
}
