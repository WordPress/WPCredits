<?php
/**
 * Posting to a Slack incoming webhook.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One sender for every message the plugin posts to Slack.
 *
 * The Mentor Status Checker has posted to Slack since 1.122.26, and the institution
 * announcement (WPCredits#282) posts to the same workspace. The address, the body Slack
 * expects and the answer that means "taken" are the same for both, and two copies of them is
 * how one message comes to be sent in a shape the other already learned is wrong. So they
 * live here, and each caller keeps only its own words and its own record of what it has
 * already said.
 *
 * **One webhook, one channel.** A Slack app ties an incoming webhook to a single channel when
 * it is installed, so which channel a message lands in is chosen in Slack and never here. Both
 * callers post to the same channel for that reason, which is what the program asked for.
 *
 * Extracted from `WPCPM_Mentor_Checker_Slack` in 1.123.56, where these four methods were
 * private; that class now calls them and keeps its own public `is_webhook()` as the name the
 * settings screen already uses.
 */
final class WPCPM_Slack {

	/** How long Slack is given to answer, in seconds. */
	const TIMEOUT = 10;

	/**
	 * Whether the address is a Slack incoming webhook rather than something else that would
	 * take a POST.
	 *
	 * Checked on every read and not only when it is saved: a webhook carries the program's own
	 * news, so it must not be sent wherever a typo points, and a Workflow Builder trigger
	 * (`/triggers/`) takes a different body altogether.
	 *
	 * @param mixed $url The address.
	 * @return bool
	 */
	public static function is_webhook( $url ) {
		return is_string( $url ) && 1 === preg_match( '#^https://hooks\.slack\.com/services/[A-Za-z0-9_/-]+\z#', $url );
	}

	/**
	 * The webhook saved in the settings, checked again on every read, or '' for none.
	 *
	 * @return string
	 */
	public static function webhook() {
		$settings = WPCPM_Settings::get();
		$url      = isset( $settings['checker_slack_webhook'] ) ? $settings['checker_slack_webhook'] : '';

		return self::is_webhook( $url ) ? $url : '';
	}

	/**
	 * Slack's escaping for text: `&`, `<` and `>`, and nothing else.
	 *
	 * Every value a message carries that came from somewhere else goes through this. Slack reads
	 * `<...>` as a link and `<!channel>` as a mention, so a record's own text can otherwise speak
	 * in the program's voice: an institution's name is typed into a public application form, and
	 * `College <!channel>` would have notified the whole channel (WPCredits#282, found by the
	 * 1.123.56 review). Here rather than in each caller, because one caller escaping and the other
	 * forgetting is exactly how the two messages came to disagree.
	 *
	 * The format strings themselves are never escaped: `*bold*` and `:tada:` are ours to write.
	 *
	 * @param string $text Text to put in a message.
	 * @return string
	 */
	public static function escape( $text ) {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $text );
	}

	/**
	 * Post one message.
	 *
	 * Links never unfurl: a message naming a profile, an institution or a page would otherwise
	 * become a card per link and bury the sentence it was sent for.
	 *
	 * @param string $webhook The webhook.
	 * @param string $text    The message, in Slack's markup.
	 * @return true|WP_Error
	 */
	public static function send( $webhook, $text ) {
		$response = wp_remote_post(
			$webhook,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode(
					array(
						'text'         => $text,
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
	public static function outcome( $response ) {
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
}
