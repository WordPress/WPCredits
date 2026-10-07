<?php
/**
 * Mentor Status Checker - the Slack message after a promotion.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the program's administrators on Slack who was just moved to Active, so they add them to the
 * mentors' channel.
 *
 * Asked for by the owner on 7 October 2026: every mentor the checker promotes has to be added to a
 * private channel by hand, and nobody saw a promotion unless they opened the tool. The message goes
 * to a Slack incoming webhook, which a Slack app installed in the workspace ties to one channel, so
 * the channel it lands in is chosen in Slack and never here.
 *
 * **One message per action, not per mentor.** `WPCPM_Mentor_Checker_Runner::promote()` records each
 * mentor it actually moves, and whatever started the promotions - a run, Promote all, a single
 * Promote - sends what was recorded once it has finished. A run of three batches therefore sends one
 * message, not three; a list longer than `PER_MESSAGE` goes in several.
 *
 * **Each waiting mentor is a row of its own** (`PENDING_PREFIX` and the record ID), added with
 * `add_option()` and deleted once sent. Nothing is ever read, changed and written back as a list, so
 * a mentor recorded by one request while another is sending cannot be overwritten and lost (the code
 * review of 7 October 2026 proved that a shared list could lose one).
 *
 * **A message Slack refuses is kept.** Its mentors wait and go out with the next message: every run
 * sends what waits, even a run that promoted nobody, so with the daily check on a mentor waits a day
 * at most. A promotion is never undone or held back by Slack; Airtable is written first. The other
 * side of that: a message Slack posted but whose answer never arrived is sent again, so a mentor can
 * now and then be named twice.
 *
 * Each line carries the mentor's Slack name when their WordPress.org profile shows one
 * (`WPCPM_WPorg_Profile`), because that is the name an administrator types to add them to the
 * channel. It is read before the lock is taken and never stored: the profile reader keeps a read for
 * twelve hours, so a message sent again reads it for free, and a read that failed is tried again.
 */
final class WPCPM_Mentor_Checker_Slack {

	/** One option per mentor promoted and not yet announced, named for their Airtable record. */
	const PENDING_PREFIX = 'wpcpm_checker_slack_pending_';

	/** The last attempt to send: when, whether it went, how many it named, and why not. */
	const LAST = 'wpcpm_checker_slack_last';

	/** Held while one request sends, so two that finish together do not post the same names twice. */
	const LOCK = 'wpcpm_checker_slack_lock';

	/**
	 * How long a lock may be held before it counts as left behind by a request that died: far longer
	 * than the posts it covers, which are all it covers.
	 */
	const LOCK_TIMEOUT = 5 * MINUTE_IN_SECONDS;

	/** Mentors per message, so a long list never meets Slack's limit on a message's length. */
	const PER_MESSAGE = 25;

	/**
	 * Whether an address is a Slack incoming webhook.
	 *
	 * Only `https://hooks.slack.com/services/...`, to the end of the string: the message carries
	 * mentors' names, so it must not be sent wherever a typo points, and a Workflow Builder trigger
	 * (`/triggers/`) takes a different body.
	 *
	 * @param mixed $url The address.
	 * @return bool
	 */
	public static function is_webhook( $url ) {
		return is_string( $url ) && 1 === preg_match( '#^https://hooks\.slack\.com/services/[A-Za-z0-9_/-]+\z#', $url );
	}

	/**
	 * The webhook saved in the tool's settings, checked again on every read, or '' for none.
	 *
	 * @return string
	 */
	private static function webhook() {
		$settings = WPCPM_Settings::get();
		$url      = isset( $settings['checker_slack_webhook'] ) ? $settings['checker_slack_webhook'] : '';

		return self::is_webhook( $url ) ? $url : '';
	}

	/**
	 * Keep a promoted mentor for the next message.
	 *
	 * Nothing is kept while no webhook is saved, so one added later announces only the promotions
	 * that follow it, not a backlog nobody asked about. A mentor already waiting is not added twice:
	 * `add_option()` refuses a name that exists.
	 *
	 * @param array $mentor The mentor as the runner reads them: id, name and profile.
	 */
	public static function record( array $mentor ) {
		if ( '' === self::webhook() || empty( $mentor['id'] ) ) {
			return;
		}

		add_option(
			self::PENDING_PREFIX . $mentor['id'],
			array_merge(
				array(
					'record_id' => (string) $mentor['id'],
					'name'      => isset( $mentor['name'] ) ? (string) $mentor['name'] : '',
					'username'  => WPCPM_Mentor_Checker_Profile::normalize_username( isset( $mentor['profile'] ) ? $mentor['profile'] : '' ),
				),
				self::actor()
			),
			'',
			false
		);
	}

	/**
	 * Send everything waiting, in as few messages as `PER_MESSAGE` allows.
	 *
	 * @return true|WP_Error|null True when all was sent, the failure when Slack or the network refused
	 *                            a message (what it named, and what followed it, wait), null when
	 *                            there was nothing to send or another request is sending.
	 */
	public static function flush() {
		$waiting = self::waiting();

		if ( array() === $waiting ) {
			return null;
		}

		$webhook = self::webhook();

		// The webhook was taken away after these were kept: there is nowhere to send them, and
		// keeping them would send a stale list the day one is saved again.
		if ( '' === $webhook ) {
			self::drop_waiting();

			return null;
		}

		// The Slack names first, outside the lock: one WordPress.org read each, and a slow one must
		// not hold up another request's send.
		$names = array();

		foreach ( $waiting as $entry ) {
			$names[ $entry['record_id'] ] = self::slack_name( $entry['username'] );
		}

		$token = self::acquire_lock();

		if ( false === $token ) {
			return null;
		}

		// Read again under the lock: the request that held it may have sent some already. A mentor
		// recorded since the names were read waits for the next message, which reads theirs.
		$entries = array();

		foreach ( self::waiting() as $entry ) {
			if ( array_key_exists( $entry['record_id'], $names ) ) {
				$entries[] = array_merge( $entry, array( 'slack' => $names[ $entry['record_id'] ] ) );
			}
		}

		$result = null;
		$sent   = 0;

		foreach ( array_chunk( $entries, self::PER_MESSAGE ) as $chunk ) {
			$result = self::post( $webhook, self::message( $chunk ) );

			if ( true !== $result ) {
				break;
			}

			foreach ( $chunk as $entry ) {
				delete_option( self::PENDING_PREFIX . $entry['record_id'] );
			}

			$sent += count( $chunk );
		}

		if ( null !== $result ) {
			update_option(
				self::LAST,
				array(
					'time'  => time(),
					'ok'    => true === $result,
					'count' => $sent,
					'error' => true === $result ? '' : $result->get_error_message(),
				),
				false
			);
		}

		self::release_lock( $token );

		return $result;
	}

	/**
	 * Post one message.
	 *
	 * No redirect is followed: a redirect would carry the body, mentors' names included, to whatever
	 * address it named.
	 *
	 * @param string $webhook The webhook.
	 * @param string $text    The message.
	 * @return true|WP_Error
	 */
	private static function post( $webhook, $text ) {
		$response = wp_remote_post(
			$webhook,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode(
					array(
						'text'         => $text,
						// A profile link would otherwise unfurl into a card per mentor.
						'unfurl_links' => false,
						'unfurl_media' => false,
					)
				),
			)
		);

		return self::outcome( $response );
	}

	/**
	 * Whether Slack took the message: an incoming webhook answers 200 with the body `ok`, and
	 * anything else with a status and a word saying why (`no_service`, `invalid_payload`, ...).
	 *
	 * @param array|WP_Error $response What `wp_remote_post()` handed back.
	 * @return true|WP_Error
	 */
	private static function outcome( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wpcpm_slack_unreachable', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( 200 === $code && 'ok' === $body ) {
			return true;
		}

		return new WP_Error( 'wpcpm_slack_refused', sprintf( 'HTTP %d: %s', $code, substr( $body, 0, 100 ) ) );
	}

	/**
	 * The message, in Slack's markup, in the site's language whatever the language of the request
	 * that sends it: a manager reading wp-admin in their own language shares the channel with others.
	 *
	 * @param array $entries Waiting entries: record_id, name, username, via, by, and slack once read.
	 * @return string
	 */
	public static function message( array $entries ) {
		$switched = switch_to_locale( get_locale() );
		$settings = WPCPM_Settings::get();
		$channel  = ltrim( trim( isset( $settings['checker_slack_channel'] ) ? (string) $settings['checker_slack_channel'] : '' ), '#' );
		$count    = count( $entries );

		$lines = array(
			'<!channel> ' . sprintf(
				/* translators: 1: number of mentors, 2: the status they were moved to, e.g. Active, 3: a Slack channel such as #mentors. */
				_n( '%1$s mentor was moved to %2$s. Please add them to %3$s:', '%1$s mentors were moved to %2$s. Please add them to %3$s:', $count, 'wpcredits-program-manager' ),
				number_format_i18n( $count ),
				self::escape( $settings['checker_target_status'] ),
				'' === $channel ? __( 'the mentors channel', 'wpcredits-program-manager' ) : '#' . self::escape( $channel )
			),
		);

		$actors = array();

		foreach ( $entries as $entry ) {
			$name = self::one_line( $entry['name'] );
			$line = '• ' . ( '' !== $name ? self::escape( $name ) : __( '(unnamed)', 'wpcredits-program-manager' ) ) . ' - ';

			if ( '' === (string) $entry['username'] ) {
				$line .= __( 'no WordPress.org profile', 'wpcredits-program-manager' );
			} else {
				$user  = self::escape( $entry['username'] );
				$line .= '<https://profiles.wordpress.org/' . $user . '/|@' . $user . '>';
			}

			// As code: the name is whatever the mentor typed on their profile, and Slack makes a
			// link of an address anywhere but in code. A backtick would end the code early.
			$slack = self::one_line( str_replace( '`', '', isset( $entry['slack'] ) ? (string) $entry['slack'] : '' ) );

			if ( '' !== $slack ) {
				/* translators: %s: a person's Slack name, as code. */
				$line .= ' - ' . sprintf( __( 'Slack: %s', 'wpcredits-program-manager' ), '`' . self::escape( $slack ) . '`' );
			}

			$lines[] = $line;
			$actor   = self::actor_label( $entry );

			if ( ! in_array( $actor, $actors, true ) ) {
				$actors[] = $actor;
			}
		}

		if ( array() !== $actors ) {
			/* translators: %s: who promoted the mentors, e.g. "the daily check and Ada Admin". */
			$lines[] = sprintf( __( 'Promoted by %s.', 'wpcredits-program-manager' ), self::escape( wp_sprintf( '%l', $actors ) ) );
		}

		if ( $switched ) {
			restore_previous_locale();
		}

		return implode( "\n", $lines );
	}

	/**
	 * Who is promoting, as it is kept: the daily check, WP-CLI or a manager by their display name.
	 * Named in words only when the message is written, in the site's language.
	 *
	 * @return array{via: string, by: string}
	 */
	public static function actor() {
		if ( wp_doing_cron() ) {
			return array(
				'via' => 'cron',
				'by'  => '',
			);
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return array(
				'via' => 'cli',
				'by'  => '',
			);
		}

		$user = wp_get_current_user();

		return array(
			'via' => 'user',
			'by'  => $user->exists() ? (string) $user->display_name : '',
		);
	}

	/**
	 * The name of who promoted a mentor, for the message.
	 *
	 * @param array $entry A waiting entry.
	 * @return string
	 */
	private static function actor_label( array $entry ) {
		$via = isset( $entry['via'] ) ? $entry['via'] : 'user';

		if ( 'cron' === $via ) {
			return __( 'the daily check', 'wpcredits-program-manager' );
		}

		if ( 'cli' === $via ) {
			return 'WP-CLI';
		}

		$by = self::one_line( isset( $entry['by'] ) ? $entry['by'] : '' );

		return '' !== $by ? $by : __( 'an administrator', 'wpcredits-program-manager' );
	}

	/**
	 * A sentence for the tool's screen and WP-CLI about the last attempt to send, or '' when there is
	 * none to make: no webhook saved, or nothing ever sent.
	 *
	 * @return string
	 */
	public static function status_sentence() {
		$last = get_option( self::LAST, array() );

		if ( '' === self::webhook() || ! is_array( $last ) || empty( $last['time'] ) ) {
			return '';
		}

		$ago = human_time_diff( (int) $last['time'], time() );

		if ( ! empty( $last['ok'] ) ) {
			return sprintf(
				/* translators: 1: how long ago, e.g. "5 mins", 2: number of mentors named. */
				_n( 'Last Slack message sent %1$s ago, naming %2$s mentor.', 'Last Slack message sent %1$s ago, naming %2$s mentors.', (int) $last['count'], 'wpcredits-program-manager' ),
				$ago,
				number_format_i18n( (int) $last['count'] )
			);
		}

		$waiting = count( self::waiting() );

		return sprintf(
			/* translators: 1: how long ago, e.g. "5 mins", 2: why, e.g. "HTTP 404: no_service". */
			__( 'The last Slack message could not be sent %1$s ago (%2$s).', 'wpcredits-program-manager' ),
			$ago,
			(string) $last['error']
		) . ( $waiting > 0 ? ' ' . sprintf(
			/* translators: %s: number of mentors waiting. */
			_n( '%s mentor waits for the next one.', '%s mentors wait for the next one.', $waiting, 'wpcredits-program-manager' ),
			number_format_i18n( $waiting )
		) : '' );
	}

	/**
	 * The mentors waiting, oldest first.
	 *
	 * @return array[]
	 */
	public static function waiting() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Each mentor is a row addressable only by exact name, so the rows are found by their prefix; read only when a promotion was recorded or a run ends.
		$names   = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC", $wpdb->esc_like( self::PENDING_PREFIX ) . '%' ) );
		$entries = array();

		foreach ( (array) $names as $name ) {
			$entry = get_option( (string) $name );

			// A row another request sent and deleted since the names were read is gone.
			if ( is_array( $entry ) && ! empty( $entry['record_id'] ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Forget everything kept here, on uninstall.
	 */
	public static function forget() {
		self::drop_waiting();
		delete_option( self::LAST );
		delete_option( self::LOCK );
	}

	/**
	 * Forget every mentor waiting.
	 */
	private static function drop_waiting() {
		foreach ( self::waiting() as $entry ) {
			delete_option( self::PENDING_PREFIX . $entry['record_id'] );
		}
	}

	/**
	 * A mentor's Slack name from their WordPress.org profile, or ''.
	 *
	 * @param string $username WordPress.org username.
	 * @return string
	 */
	private static function slack_name( $username ) {
		if ( '' === (string) $username ) {
			return '';
		}

		$profile = WPCPM_WPorg_Profile::get( $username );

		return is_array( $profile ) && isset( $profile['slack'] ) ? (string) $profile['slack'] : '';
	}

	/**
	 * Text on one line: a line break in a name from Airtable or a profile would otherwise start a
	 * line of the message that reads as a mentor of its own.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function one_line( $text ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
	}

	/**
	 * Slack's escaping for text: `&`, `<` and `>` and nothing else.
	 *
	 * @param string $text Text to put in a message.
	 * @return string
	 */
	private static function escape( $text ) {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $text );
	}

	/**
	 * Take the lock, as `WPCPM_Mentors_Sync` takes its own, under a token of this request's, so only
	 * this request lets it go.
	 *
	 * `add_option()` refuses a name it finds, so a lock already held is seen. It is not a strict
	 * mutex: core reads before it upserts, so two requests in the same instant can both take it, and
	 * the cost of that is one message posted twice.
	 *
	 * @return string|false The token, or false while another request holds the lock.
	 */
	private static function acquire_lock() {
		$token = time() . '|' . wp_generate_password( 12, false, false );

		if ( add_option( self::LOCK, $token, '', false ) ) {
			return $token;
		}

		$held = (int) explode( '|', (string) get_option( self::LOCK ) )[0];

		if ( $held && ( time() - $held ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::LOCK, $token, false );

		return $token;
	}

	/**
	 * Let the lock go, if it is still this request's: a request that took over a lock it thought
	 * dead holds it now.
	 *
	 * @param string $token This request's token.
	 */
	private static function release_lock( $token ) {
		if ( get_option( self::LOCK ) === $token ) {
			delete_option( self::LOCK );
		}
	}
}
