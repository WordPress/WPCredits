<?php
/**
 * Everything this plugin sends by email.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One way out for every message, so the things every message needs happen once.
 *
 * Four of those things, each of which was previously missing or wrong at every call site:
 *
 * - **The recipient's language.** Templates are built *inside* `switch_to_user_locale()`,
 *   which is why `send()` takes a builder rather than a finished subject and body. Switching
 *   the locale around a string that has already been translated does nothing at all, and
 *   that mistake is invisible until somebody with a non-English profile gets English mail.
 * - **A reply that goes somewhere.** Mail otherwise leaves as `wordpress@…`, so a mentor
 *   answering "Call booked with your mentor" is writing to a mailbox nobody reads.
 * - **A record.** `wp_mail()` returns a boolean that every caller discarded, so "the student
 *   says they got nothing" was unanswerable. The context each message carries names it in the
 *   mail log (`WPCPM_Mail_Log`, WPCredits Program > Tools > Emails), which keeps who, what, when
 *   and status for 30 days and never the message text.
 * - **One filter.** `wpcpm_mail` sees subject, body and headers together, so a site can
 *   change any of it without patching a template.
 */
class WPCPM_Mail {

	/** Option holding user IDs waiting for an invitation. */
	const QUEUE_OPTION = 'wpcpm_invite_queue';

	/** Cron hook that drains the invitation queue. */
	const CRON_QUEUE = 'wpcpm_drain_invite_queue';

	/** Invitations sent per cron run. */
	const QUEUE_BATCH = 10;

	/**
	 * How long after one invitation another may go to the same person, in seconds: fifteen minutes.
	 *
	 * Every invitation mints a new password link and cancels the one before it (WordPress keeps one
	 * reset key per account), so a second sent soon after the first leaves the person holding a link
	 * that no longer works. On 8 and 9 September 2026 one student was sent 17 in two days, and a
	 * class being walked through its first login kept landing on "Your password reset link appears
	 * to be invalid". Long enough for a first email to arrive and be opened; short enough that one
	 * that truly went missing can be sent again within the same session.
	 */
	const INVITE_GAP = 900;

	/**
	 * Prefix of the option row that holds one account while its invitation is being sent; the rest of
	 * the name is the account's ID (`send_invite_now()`, through `WPCPM_Lock`). Uninstall sweeps the
	 * rows by it.
	 */
	const INVITE_LOCK = 'wpcpm_invite_lock_';

	/**
	 * How long an invitation's lock holds at most, in seconds: ten minutes, far longer than one email
	 * takes to hand over, and shorter than `INVITE_GAP`. Only a request that dies while sending holds
	 * it that long.
	 */
	const INVITE_LOCK_TTL = 600;

	/**
	 * The invitation stamps, by the program role each belongs to: user meta holding when the account
	 * was last sent its login invitation, as a Unix time.
	 *
	 * One map for the rule every route stamps by (`stamp_invited()`) and for every reader of who was
	 * sent one (`last_invited()`, `queue_invites()`, `never_invited()`, the accounts tables' views),
	 * so the mail layer spells each key once.
	 */
	const STAMPS = array(
		WPCPM_Roles::ROLE_STUDENT     => 'wpcpm_student_invited',
		WPCPM_Roles::ROLE_MENTOR      => 'wpcpm_mentor_invited',
		WPCPM_Roles::ROLE_INSTITUTION => 'wpcpm_inst_invited',
		WPCPM_Roles::ROLE_SPONSOR     => 'wpcpm_sponsor_invited',
	);

	/**
	 * What a bulk invite is working through, so a screen can show progress.
	 *
	 * The queue only knows who is *left*. Sending 241 invitations ten at a time takes the better
	 * part of an hour, and a screen that can only say "231 waiting" leaves somebody with no idea
	 * whether anything is happening - which is how a bulk send gets pressed twice. Holding the
	 * total it started with turns that into "10 of 241 sent".
	 */
	const RUN_OPTION = 'wpcpm_invite_run';

	/** Admin-post action that cancels whatever is left of a bulk invite. */
	const ACTION_STOP = 'wpcpm_stop_invites';

	/** The field the invitations card's Stop names the flash channel of the screen it is on in. */
	const FLASH_FIELD = 'wpcpm_flash';

	/** Admin-post action that clears a finished run from the screen. */
	const ACTION_DISMISS = 'wpcpm_dismiss_invite_run';

	/**
	 * What is currently being sent, if it is ours: the email's id in `WPCPM_Mail_Catalog`.
	 *
	 * Set immediately before handing a message to `wp_mail()` (and, for an invitation, in
	 * `welcome_email()`, which WordPress's own `wp_mail()` call follows), and taken by the mail log's
	 * capture at the `wp_mail` filter (`take_context()`), so it names that email and no later one.
	 *
	 * @var string
	 */
	private static $context = '';

	/**
	 * Whether what is currently being sent is a test, which the mail log marks Test: set and taken
	 * with the context (`take_test()`).
	 *
	 * @var bool
	 */
	private static $test = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'welcome_email' ), 10, 3 );
		add_action( self::CRON_QUEUE, array( __CLASS__, 'drain_queue' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule_drain' ) );
		add_action( 'admin_post_' . self::ACTION_STOP, array( __CLASS__, 'handle_stop' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'handle_dismiss' ) );

		// Before wpcomsh's own `login_init` callback, which runs at -1. See the method.
		add_action( 'login_init', array( __CLASS__, 'keep_password_links_working' ), -2 );
	}

	/**
	 * The id of the email being sent, if the plugin set one, cleared as it is read: the mail log's
	 * capture takes it at the `wp_mail` filter (`WPCPM_Mail_Capture::open()`).
	 *
	 * @return string
	 */
	public static function take_context() {
		$context       = self::$context;
		self::$context = '';

		return $context;
	}

	/**
	 * Whether the email being sent is a test, cleared as it is read: the mail log's capture takes it
	 * with the context (`WPCPM_Mail_Capture::open()`).
	 *
	 * @return bool
	 */
	public static function take_test() {
		$test       = self::$test;
		self::$test = false;

		return $test;
	}

	/*
	 * Sending
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send one message to one person.
	 *
	 * @param int|WP_User $recipient Who it is for. Their locale is used to build it.
	 * @param string      $context   The email's id in `WPCPM_Mail_Catalog`, e.g. `call-booked-student`.
	 * @param callable    $build     Receives the recipient as a `WP_User` and returns an
	 *                               array with `subject`, `body`, and optionally `headers`,
	 *                               `attachments`, `cleanup`, `plain_subject` (see
	 *                               `subject()`), `test` (the mail log marks it Test) and
	 *                               `footer_values` (the signature and footer's values in
	 *                               place of the recipient's, see
	 *                               `WPCPM_Mail_Templates::add_footer()`).
	 * @return bool Whether the message was handed off successfully.
	 */
	public static function send( $recipient, $context, $build ) {
		$user = $recipient instanceof WP_User ? $recipient : get_user_by( 'id', (int) $recipient );

		if ( ! $user instanceof WP_User || ! $user->exists() || '' === (string) $user->user_email ) {
			return false;
		}

		if ( ! is_callable( $build ) ) {
			return false;
		}

		// Guarded because `switch_to_user_locale()` arrived in WordPress 6.2 and this plugin
		// supports 6.5 - but a site can be on a version where the function is absent for
		// other reasons, and mail in the wrong language beats no mail.
		$switched = function_exists( 'switch_to_user_locale' ) ? switch_to_user_locale( $user->ID ) : false;

		$sent = self::hand_off( $user->user_email, $context, call_user_func( $build, $user ), $user );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $sent;
	}

	/**
	 * Send one message to an address that has no account yet.
	 *
	 * An applicant is an email address and nothing more: no user to look up, no profile
	 * language to switch into. `send()` needs a `WP_User`, so mail to somebody who has not
	 * been provisioned went straight to `wp_mail()` before this - past the filter, the log
	 * and the subject sanitising - which made the one message sent to a stranger the one
	 * message nobody could trace.
	 *
	 * The language comes from the caller, because an applicant's form is the only place it
	 * is known. Left empty, the message is built in whatever locale the request is in.
	 *
	 * @param string   $email   Where it goes. Refused unless `is_email()` accepts it.
	 * @param string   $context The email's id in `WPCPM_Mail_Catalog`, e.g. `institution-applied`.
	 * @param callable $build   Receives the address as a string and returns the same array
	 *                          `send()`'s builder does.
	 * @param string   $locale  Locale to build the message in, e.g. `es_ES`. Empty for the
	 *                          current one.
	 * @return bool Whether the message was handed off successfully.
	 */
	public static function send_to( $email, $context, $build, $locale = '' ) {
		$email = trim( (string) $email );

		if ( ! is_email( $email ) ) {
			return false;
		}

		if ( ! is_callable( $build ) ) {
			return false;
		}

		$locale = trim( (string) $locale );

		// Guarded the way `send()` guards its own switch: a site missing the function gets
		// mail in the wrong language rather than no mail. `switch_to_locale()` also answers
		// false when asked for the locale already in force, and then there is nothing to
		// restore.
		$switched = '' !== $locale && function_exists( 'switch_to_locale' ) ? switch_to_locale( $locale ) : false;

		$sent = self::hand_off( $email, $context, call_user_func( $build, $email ), null );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $sent;
	}

	/**
	 * The part of a send that is the same whoever it is for.
	 *
	 * Defaults, the filter, the subject, `wp_mail()` and the cleanup, once - so `send()` and
	 * `send_to()` cannot drift apart in what they let through, and a message to a bare
	 * address is logged and filtered exactly like one to a user. Runs inside whatever locale
	 * the caller has switched to; the switching is the only thing the two callers do
	 * differently, so it stays with them.
	 *
	 * @param string       $to        Address the message goes to.
	 * @param string       $context   The email's id in `WPCPM_Mail_Catalog`.
	 * @param mixed        $mail      What the builder returned.
	 * @param WP_User|null $recipient The recipient's account, or null when there is none.
	 * @return bool Whether the message was handed off successfully.
	 */
	private static function hand_off( $to, $context, $mail, $recipient ) {
		$mail = wp_parse_args(
			(array) $mail,
			array(
				'subject'       => '',
				'body'          => '',
				'headers'       => array(),
				'attachments'   => array(),
				'cleanup'       => array(),
				'plain_subject' => false,
				'test'          => false,
				'footer_values' => null,
			)
		);

		// Every email the template registry fills goes out with a plain-text subject, a "<" or an "&"
		// in it a character, as its preview and its test show it: its words are the shipped text's or
		// an Administrator's, and its values are read back as a person reads them
		// (`WPCPM_Mail_Templates::fill()`), so the cleaner would otherwise write a "<" in a name as
		// `&lt;`, with every "&" and quote after it, and take out a run it reads as a tag. Decided
		// here, once for every sender (since 1.122.23; an email sent as edited, since 1.122.22).
		if ( empty( $mail['plain_subject'] ) && class_exists( 'WPCPM_Mail_Templates' ) && null !== WPCPM_Mail_Templates::get( $context ) ) {
			$mail['plain_subject'] = true;
		}

		// Every email the plugin sends ends with the signature and footer, filled for its recipient,
		// and before the filter, so the filter sees the email as it goes out. A builder can hand over
		// the footer's values itself, as a test hands over examples (`footer_values`).
		if ( class_exists( 'WPCPM_Mail_Templates' ) ) {
			$mail['body'] = WPCPM_Mail_Templates::add_footer( (string) $mail['body'], $recipient, $context, is_array( $mail['footer_values'] ) ? $mail['footer_values'] : null );
		}

		/**
		 * Filter a message before it is sent.
		 *
		 * Runs inside the locale the message is being built in - the recipient's profile
		 * language, or the one a caller named for an address with no account - so anything
		 * added here is translated the same way the template was.
		 *
		 * @param array        $mail      Subject, body, headers, attachments, and whether the
		 *                                subject is plain text (`plain_subject`, see `subject()`).
		 * @param string       $context   Message context.
		 * @param WP_User|null $recipient Recipient. Null when the message goes to an address
		 *                                that has no account yet, as an applicant's does, so
		 *                                a site keying on the person has to allow for having
		 *                                nobody to key on.
		 */
		$mail = (array) apply_filters( 'wpcpm_mail', $mail, $context, $recipient );

		$sent = false;

		if ( '' !== trim( (string) $mail['subject'] ) ) {
			self::$context = sanitize_key( $context );
			self::$test    = ! empty( $mail['test'] );

			$sent = wp_mail(
				$to,
				self::subject( $mail ),
				(string) $mail['body'],
				(array) $mail['headers'],
				array_filter( (array) $mail['attachments'] )
			);

			self::$context = '';
			self::$test    = false;
		}

		foreach ( (array) $mail['cleanup'] as $path ) {
			WPCPM_ICS::cleanup( $path );
		}

		return (bool) $sent;
	}

	/**
	 * A message's subject as it goes out, cleaned by one rule for every message.
	 *
	 * A subject is a header: the CR and LF that would turn one into several are stripped here
	 * rather than at each template, because the names that reach a subject come from Airtable
	 * columns and WordPress profiles and are not constants this code controls. Core's text cleaner
	 * does it, and folds every other run of white space into one space.
	 *
	 * The cleaner also takes out whatever it reads as a tag: a "<" followed by anything but white
	 * space, with the words up to the next ">", or, when a quote mark opens inside it and does not
	 * close before a ">", to the end of the subject. A "<" with no ">" after it, before the next
	 * "<" or the end, it writes as `&lt;` and keeps, with the quote marks and the ampersands after
	 * it as entities. A builder whose subject carries what a person typed, its entities read back
	 * (`WPCPM_Typed_Text::mail_text()`), says the subject is plain text (`plain_subject`): "We <3
	 * our team > all" would otherwise go out as "We all". So is the subject of every email the
	 * template registry fills, which `hand_off()` marks itself. Such a subject goes through the
	 * cleaner with each "&" and "<" held as an entity, so it finds no tag, and the two are read
	 * back after, once and no deeper: a typed-out `&lt;` goes out as typed. The cleaner reads no
	 * markup without a "<", so the line is cleaned as every other subject is, and the "<" is kept
	 * as text. The read-back writes no CR or LF.
	 *
	 * @param array $mail The message: its `subject`, and `plain_subject`.
	 * @return string
	 */
	private static function subject( array $mail ) {
		$subject = (string) $mail['subject'];

		if ( empty( $mail['plain_subject'] ) ) {
			return sanitize_text_field( $subject );
		}

		return strtr(
			sanitize_text_field(
				strtr(
					$subject,
					array(
						'&' => '&amp;',
						'<' => '&lt;',
					)
				)
			),
			array(
				'&lt;'  => '<',
				'&amp;' => '&',
			)
		);
	}

	/**
	 * A `Reply-To` header pointing at the other person in a conversation.
	 *
	 * The name is the account's as a person reads it: WordPress stores a display name with "&",
	 * "<" and ">" written as entities, which the header would otherwise carry as `&amp;`. A name is
	 * user data and this is a header, so each run of white space in it, a line break among them,
	 * is one space, and a control character in it is dropped. The name goes as it is, in no quotes:
	 * `wp_mail()` hands the mailer what stands before the address as the name, quote marks
	 * included, and the mailer quotes, escapes and encodes a name itself, so a name the plugin put
	 * in quotes arrived with them, as `"Ida Admin"` (until 1.122.23). Only a comma, which
	 * `wp_mail()` reads as the end of one address in a Reply-To, and a "=?", which a mail program
	 * would read as the start of an encoded word the name never held, make the name go as encoded
	 * words (RFC 2047), which keep every character and which `wp_mail()` and the mailer pass as
	 * they are (`encoded_words()`); those words end where the line would pass the 998 characters a
	 * header line holds. Until 1.122.23 a comma, a double quote, a backslash and a semicolon were
	 * each written as a space, and an "&" a name was stored with lost its ";".
	 *
	 * @param WP_User|null $person Who the recipient would be replying to.
	 * @return array Headers array, empty when there is nobody to reply to.
	 */
	public static function reply_to( $person ) {
		if ( ! $person instanceof WP_User || '' === (string) $person->user_email ) {
			return array();
		}

		$name = wp_specialchars_decode( (string) $person->display_name, ENT_QUOTES );
		$name = trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/[\x00-\x08\x0E-\x1F\x7F]+/', '', $name ) ) );

		if ( '' === $name ) {
			return array( sprintf( 'Reply-To: %s', $person->user_email ) );
		}

		if ( false === strpos( $name, ',' ) && false === strpos( $name, '=?' ) ) {
			return array( sprintf( 'Reply-To: %1$s <%2$s>', $name, $person->user_email ) );
		}

		// What the line holds beside the words: "Reply-To: ", and " <", the address and ">".
		$words = self::encoded_words( $name, 998 - strlen( 'Reply-To:  <>' ) - strlen( (string) $person->user_email ) );

		if ( '' === $words ) {
			return array( sprintf( 'Reply-To: %s', $person->user_email ) );
		}

		return array( sprintf( 'Reply-To: %1$s <%2$s>', $words, $person->user_email ) );
	}

	/**
	 * A name as encoded words (RFC 2047): its UTF-8 bytes in base64, as many words as it takes for
	 * none to be longer than the 75 characters the standard allows, each word ending on a whole
	 * character, a space between two words, which a mail program reading them leaves out. The words
	 * stop before the first that would take them past `$room` characters, so a name too long for
	 * one header line is cut there, on a whole character.
	 *
	 * @param string $name The name, one line.
	 * @param int    $room How many characters the words may take, the spaces between them included.
	 * @return string
	 */
	private static function encoded_words( $name, $room ) {
		$characters = preg_split( '//u', (string) $name, -1, PREG_SPLIT_NO_EMPTY );

		// A name that is not UTF-8 is cut by bytes: its words read back to the same bytes.
		if ( false === $characters ) {
			$characters = str_split( (string) $name );
		}

		$words = array( '' );

		foreach ( $characters as $character ) {
			// 45 bytes are 60 characters of base64, and with "=?UTF-8?B?" and "?=" a word of 72.
			if ( strlen( end( $words ) . $character ) > 45 ) {
				$words[] = '';
			}

			$words[ count( $words ) - 1 ] .= $character;
		}

		$line = '';

		foreach ( $words as $word ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 2047's B encoding of a name in a header, which a mail program reads back; no code.
			$encoded = '=?UTF-8?B?' . base64_encode( $word ) . '?=';
			$longer  = '' === $line ? $encoded : $line . ' ' . $encoded;

			if ( strlen( $longer ) > $room ) {
				break;
			}

			$line = $longer;
		}

		return $line;
	}

	/**
	 * The site's name as WordPress stores it, its "&", "<" and quote marks written as entities: the
	 * value an email's `{site_name}` is handed, which `WPCPM_Mail_Templates::fill()` reads back to
	 * the name as typed, as it reads every value, once. Text the plugin writes itself, outside a
	 * template, reads it back with `wp_specialchars_decode()`. Until 1.122.23 it gave the name read
	 * back; since `fill()` reads every value back, that would read it twice, and a name typed with
	 * an entity in it would lose it.
	 *
	 * @return string
	 */
	public static function site_name() {
		return (string) get_bloginfo( 'name' );
	}

	/*
	 * An address, masked
	 * --------------------------------------------------------------------
	 */

	/**
	 * An address reduced to what identifies it without disclosing it: `a***@example.org`.
	 *
	 * The first character of the mailbox and the whole domain: what the Administrator Dashboard's
	 * health card shows of the latest email's recipient, since that card is a page of the site and
	 * the full address is the mail log's, in wp-admin. A `Name <address>` form is masked by its
	 * address and the name dropped; anything that is not an address at all becomes `***`.
	 *
	 * @param string $address One recipient, as handed to `wp_mail()`.
	 * @return string The masked form, or an empty string for a blank.
	 */
	public static function mask_address( $address ) {
		$address = trim( (string) $address );

		if ( preg_match( '/<([^>]*)>/', $address, $m ) ) {
			$address = trim( $m[1] );
		}

		if ( '' === $address ) {
			return '';
		}

		$at = strrpos( $address, '@' );

		if ( false === $at || 0 === $at ) {
			return '***';
		}

		return sanitize_text_field( mb_substr( $address, 0, 1 ) . '***' . substr( $address, $at ) );
	}

	/*
	 * The invitation queue
	 * --------------------------------------------------------------------
	 */

	/**
	 * Put somebody in line for a login invitation.
	 *
	 * A first sync provisions around ninety accounts in one request. Sending ninety messages
	 * from inside that request means a timeout or a host's hourly mail limit somewhere in the
	 * middle, and no way to know how far it got - so the sync records who needs one and cron
	 * sends them a batch at a time.
	 *
	 * @param int $user_id User ID.
	 */
	public static function queue_invite( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! $user_id ) {
			return;
		}

		$queue = self::queue();

		if ( in_array( $user_id, $queue, true ) ) {
			return;
		}

		$queue[] = $user_id;

		update_option( self::QUEUE_OPTION, $queue, false );

		// Cron only runs on a request, and the next one may be a while off on a quiet site.
		// Asking for a run now means the first batch goes out promptly.
		self::schedule_drain();
	}

	/**
	 * Queue invitations for people who have never had one, and start a run the screen can report on.
	 *
	 * Not a loop over `queue_invite()`: that would write the option once per person, and 241 writes
	 * of a growing array is a lot of work to do inside one admin request.
	 *
	 * **Anyone already invited is dropped here, not only by the caller.** The screen builds its
	 * list from `never_invited()`, so in practice the two agree - but the list is built when the
	 * page renders and acted on when the button is pressed, and a batch can go out in between. The
	 * caller being careful is not the same as the send being safe, and this is the send.
	 *
	 * That makes this the wrong function for a deliberate re-invitation. There is one of those
	 * already: `send_invite()`, behind Resend invite on a person's row, which is one message to one
	 * named person rather than a few hundred at once.
	 *
	 * **An invitation an activation held is dropped too** (`is_held()`, 1.122.33): it carries the
	 * mentor's welcome, with its private links, to an account nobody has confirmed is the person's,
	 * so only the account's own row sends it.
	 *
	 * @param int[] $user_ids Users to invite.
	 * @return int How many were added, after dropping duplicates, anyone queued, anyone sent to, and
	 *             anyone whose invitation is held.
	 */
	public static function queue_invites( array $user_ids ) {
		$queue = self::queue();
		$ids   = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		$fresh = array();

		foreach ( array_diff( $ids, $queue ) as $id ) {
			// Any of the stamps, there whatever it holds, as the lists' views and `never_invited()`
			// read them: this does not need to know which kind of person it is looking at, an
			// account given a role after its invitation carries no stamp for that one, though its
			// one password was sent a link, and a stamp of 0 is somebody having written one.
			foreach ( self::STAMPS as $meta ) {
				if ( metadata_exists( 'user', $id, $meta ) ) {
					continue 2;
				}
			}

			if ( self::is_held( $id ) ) {
				continue;
			}

			$fresh[] = $id;
		}

		if ( empty( $fresh ) ) {
			return 0;
		}

		$queue = array_merge( $queue, $fresh );

		update_option( self::QUEUE_OPTION, $queue, false );

		// The run counts everybody now waiting, not just this batch: if a sync had already queued
		// somebody, the bar has to reach the end of the actual work rather than of this request's
		// share of it.
		update_option(
			self::RUN_OPTION,
			array(
				'total'    => count( $queue ),
				'started'  => time(),
				'finished' => 0,
				'unsent'   => 0,
			),
			false
		);

		self::schedule_drain();

		return count( $fresh );
	}

	/**
	 * The bulk invite currently running, or the last one to finish.
	 *
	 * `unsent` is how many of its accounts the queue passed over without sending: one sent an
	 * invitation inside the gap or being sent one at that moment, one whose email the mail host
	 * refused, one whose account was gone, one whose invitation an activation held.
	 *
	 * @return array{total:int,started:int,finished:int,unsent:int}|array{} Empty when there has been none.
	 */
	public static function run() {
		$run = get_option( self::RUN_OPTION, array() );

		if ( ! is_array( $run ) || empty( $run['total'] ) ) {
			return array();
		}

		return array(
			'total'    => (int) $run['total'],
			'started'  => (int) ( isset( $run['started'] ) ? $run['started'] : 0 ),
			'finished' => (int) ( isset( $run['finished'] ) ? $run['finished'] : 0 ),
			'unsent'   => (int) ( isset( $run['unsent'] ) ? $run['unsent'] : 0 ),
		);
	}

	/**
	 * Stop reporting on the last run.
	 */
	public static function dismiss_run() {
		delete_option( self::RUN_OPTION );
	}

	/**
	 * Everybody currently waiting.
	 *
	 * @return int[]
	 */
	public static function queue() {
		$queue = get_option( self::QUEUE_OPTION, array() );

		return is_array( $queue ) ? array_values( array_map( 'intval', $queue ) ) : array();
	}

	/**
	 * How many invitations are waiting to go out.
	 *
	 * @return int
	 */
	public static function queued() {
		return count( self::queue() );
	}

	/**
	 * Send the next batch of invitations.
	 *
	 * Each account goes through the rule every route that sends one person an invitation follows
	 * (`send_invite_now()`): under the account's lock, against the fifteen minutes, and stamped once
	 * the email has gone. An account that rule refuses, sent one inside the gap, being sent one by
	 * another request at that moment, or whose email the mail host refused, is passed over: it has
	 * left the queue with its batch, as an account inside the gap always has, and is not queued again.
	 * So is an account whose invitation an activation held (`is_held()`), however it came to be
	 * queued: only its own row sends it. With anybody left, the next run is booked before the first
	 * message.
	 */
	public static function drain_queue() {
		$queue = self::queue();

		if ( empty( $queue ) ) {
			delete_option( self::QUEUE_OPTION );
			self::finish_run();

			return;
		}

		$batch = array_splice( $queue, 0, self::QUEUE_BATCH );

		// Written back, and the next run booked, *before* sending. A batch that dies halfway - a fatal,
		// a timeout, a mail host refusing the connection - has its names out of the queue and the next
		// run on the clock, so that run moves on instead of retrying the same ten for ever.
		if ( empty( $queue ) ) {
			delete_option( self::QUEUE_OPTION );
		} else {
			update_option( self::QUEUE_OPTION, $queue, false );

			// With an event on the clock for the whole send, `schedule_drain()` books nothing, so no
			// second drain starts beside a slow one. The runner took the running event off the clock,
			// but a request, or a sync queueing somebody, may have booked one a minute out since;
			// core drops a second single event of the hook within ten minutes of it, so the hook is
			// cleared first and the pace stays ten every two minutes.
			wp_clear_scheduled_hook( self::CRON_QUEUE );
			wp_schedule_single_event( time() + 120, self::CRON_QUEUE );
		}

		$unsent = 0;

		foreach ( $batch as $user_id ) {
			$user = get_user_by( 'id', $user_id );

			if ( ! $user instanceof WP_User || self::is_held( $user->ID ) ) {
				++$unsent;
				continue;
			}

			// Somebody sent an invitation inside the gap, or being sent one right now - by Resend
			// invite while they waited here, or by another run of this queue that reached them
			// first - keeps the link they have, and is passed over, as is one whose email the mail
			// host refuses. The run counts each, so the card does not call them sent.
			if ( is_wp_error( self::send_invite_now( $user ) ) ) {
				++$unsent;
			}
		}

		self::count_unsent( $unsent );

		// The last batch books nothing. The run is stamped finished only once that batch has been sent,
		// not when the queue option was emptied above, which is *before* sending: a screen reading
		// that moment would say "finished" mid-send.
		if ( empty( $queue ) ) {
			self::finish_run();
		}
	}

	/**
	 * Stamp an account as sent its login invitation, by the one rule every route that sends one
	 * follows, once the email has gone (`send_invite_now()`): the queue (`drain_queue()`), and for
	 * one person the Students, Mentors and Institutions syncs' `send_invite()` and the sponsor
	 * members' own (`WPCPM_Sponsor_Members::send_invite()`), which hand the account to it.
	 *
	 * Each program role the account holds is asked on its own and stamped under its key (`STAMPS`),
	 * all at one moment, the one given or now; an account holding none of them is stamped as a
	 * student. A key is its audience's own record of the invitation, the one that audience's code
	 * names (`WPCPM_Accounts_Table::invite_meta()`, the members classes' `META_INVITED`), so an
	 * account in two audiences is stamped for both, whichever route sent its message. An account
	 * holding no program role carries a stamp all the same: `may_invite()` reads it to keep a second
	 * message from canceling the link in the first. Who was sent one is read from any key
	 * (`last_invited()`, `queue_invites()`, `never_invited()`, the lists' views), because an account
	 * has one password.
	 *
	 * The message is one template all the same, the one `welcome_email()` chooses in its own order,
	 * because the template is what the person reads and the stamps are what the lists read.
	 *
	 * @param WP_User $user The account the invitation went to.
	 * @param int     $time When it went, as a Unix time; 0 for now.
	 * @return string[] The keys written, in the order of `STAMPS`.
	 */
	public static function stamp_invited( WP_User $user, $time = 0 ) {
		$time   = $time ? (int) $time : time();
		$stamps = array();

		foreach ( self::STAMPS as $role => $meta ) {
			if ( WPCPM_Roles::user_has_role( $user, $role ) ) {
				$stamps[] = $meta;
			}
		}

		if ( empty( $stamps ) ) {
			$stamps[] = self::STAMPS[ WPCPM_Roles::ROLE_STUDENT ];
		}

		foreach ( $stamps as $meta ) {
			update_user_meta( $user->ID, $meta, $time );
		}

		return $stamps;
	}

	/**
	 * When somebody was last sent an invitation, by any route: a Unix time, or 0 for never.
	 *
	 * All four stamps count, because an account holding two roles has one password: a mentor
	 * who also studies, invited as a mentor a minute ago, holds a link that a student invitation
	 * would cancel just the same.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function last_invited( $user_id ) {
		$last = 0;

		foreach ( self::STAMPS as $meta ) {
			$last = max( $last, (int) get_user_meta( (int) $user_id, $meta, true ) );
		}

		return $last;
	}

	/**
	 * Whether an invitation may go to somebody now: true, or why not.
	 *
	 * The one check every route asks before it sends, through `send_invite_now()`: a row's Resend
	 * invite, the Institution Dashboard's press and the queue, so no route cancels a link another
	 * sent inside `INVITE_GAP`. It is asked under the account's lock, which closes the moment between
	 * this check and the stamp.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error `wpcpm_invite_too_soon`, carrying the seconds still to wait as `wait`.
	 */
	public static function may_invite( $user_id ) {
		$last = self::last_invited( $user_id );
		$wait = $last ? $last + self::INVITE_GAP - time() : 0;

		if ( $wait <= 0 ) {
			return true;
		}

		return self::too_soon( $wait );
	}

	/**
	 * Send one account its login invitation now, by the one rule every route follows: the Students,
	 * Mentors and Institutions syncs' `send_invite()` and the sponsor members'
	 * (`WPCPM_Sponsor_Members::send_invite()`), which a row's Resend invite in wp-admin and the
	 * Institution Dashboard's press reach, and the queue (`drain_queue()`). Each `send_invite()`
	 * refuses an account that is not its audience's before it gets here.
	 *
	 * **The lock comes first.** The account's lock (`WPCPM_Lock`, `INVITE_LOCK` and the ID, for
	 * `INVITE_LOCK_TTL`) is taken before the fifteen minutes are asked and let go in `finally`, once
	 * the invitation is stamped or the send has ended any other way, an exception included. So two
	 * requests for one account at the same moment send one email: the one that finds the lock taken
	 * is refused as the fifteen minutes refuse it, in the same words, carrying the seconds until the
	 * lock runs out, and the one that takes it once the other has let it go finds the other's stamp.
	 * With the lock taken, the account's user meta is dropped from the object cache (a persistent
	 * one's shared copy too), so the fifteen minutes read the stamp as the database holds it, however
	 * long ago this request read it.
	 *
	 * **A refused email is no send.** The invitation goes as core's new-user notification to the
	 * account alone, and WordPress drops what `wp_mail()` answered, so a refusal is listened for on
	 * `wp_mail_failed` around this one send and counted only when it is addressed to the account
	 * (`refused_for()`): another plugin can send an email of its own from a filter while the invitation
	 * is on its way, and that one failing says nothing of the invitation. A refused invitation is not
	 * stamped, so the account's last invitation stays the last one that went, or none, and the
	 * fifteen minutes do not start: whoever pressed can send again at once. That last invitation's
	 * link no longer works all the same, because core made the new password-reset key before it
	 * handed the email over, which the refusal's sentence says (`invite_refused_message()`). A sent
	 * one is stamped by the mail layer's one rule (`stamp_invited()`), and lets go of an invitation an
	 * activation held (`is_held()`): the queue passes such an account over before it gets here, so a
	 * send that reaches it is a person's press on that one account.
	 *
	 * @param WP_User $user The account.
	 * @return true|WP_Error `wpcpm_invite_too_soon` while another request is sending the account an
	 *                       invitation, or inside `INVITE_GAP` of the last one; `wpcpm_invite_refused`
	 *                       when the mail host refused the email.
	 */
	public static function send_invite_now( WP_User $user ) {
		$user_id = (int) $user->ID;
		$lock    = self::INVITE_LOCK . $user_id;
		$token   = WPCPM_Lock::take( $lock, self::INVITE_LOCK_TTL );

		if ( '' === $token ) {
			$until = WPCPM_Lock::until( $lock );

			return self::too_soon( $until > time() ? $until - time() : self::INVITE_GAP );
		}

		try {
			wp_cache_delete( $user_id, 'user_meta' );

			$allowed = self::may_invite( $user_id );

			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}

			$refused = false;
			$to      = (string) $user->user_email;
			$listen  = static function ( $error = null ) use ( &$refused, $to ) {
				if ( self::refused_for( $error, $to ) ) {
					$refused = true;
				}
			};

			add_action( 'wp_mail_failed', $listen );

			try {
				wp_new_user_notification( $user_id, null, 'user' );
			} finally {
				remove_action( 'wp_mail_failed', $listen );
			}

			if ( $refused ) {
				return new WP_Error( 'wpcpm_invite_refused', self::invite_refused_message() );
			}

			self::stamp_invited( $user );

			// Sent to this one account, which a person chose: whatever held it is past.
			if ( '' !== self::held_key() ) {
				delete_user_meta( $user_id, self::held_key() );
			}

			return true;
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * The account key an activation of a mentor application holds an invitation by
	 * (`WPCPM_Mentor_Provision::USER_INVITE_HELD`), or '' where that class is not loaded, when no
	 * account can carry it.
	 *
	 * @return string
	 */
	private static function held_key() {
		return class_exists( 'WPCPM_Mentor_Provision' ) ? WPCPM_Mentor_Provision::USER_INVITE_HELD : '';
	}

	/**
	 * Whether an activation of a mentor application held an account's invitation (1.122.33): the
	 * account was linked by its WordPress.org username alone, or another account also answered for
	 * the applicant, and the invitation, which carries the mentor's welcome with its private links,
	 * waits for an Administrator to confirm the account is the person's. The bulk invite, its card and
	 * the queue pass it over; the account's own row sends it (`send_invite_now()`), which lets go of
	 * the hold.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_held( $user_id ) {
		$key = self::held_key();

		return '' !== $key && metadata_exists( 'user', (int) $user_id, $key );
	}

	/**
	 * Whether a failure WordPress reported on `wp_mail_failed` was an email to this address.
	 *
	 * Core hands the hook a `WP_Error` carrying the email's recipients as `to`, a list or a
	 * comma-separated string, each an address or a name with one in angle brackets; they are read
	 * as the mail log reads them (`WPCPM_Mail_Capture::recipients()`) and compared without regard to
	 * case. A failure that names no recipient at all, which core's `wp_mail()` never reports, cannot
	 * be told apart from the invitation's own, and counts as it.
	 *
	 * @param mixed  $error   What the hook was handed.
	 * @param string $address The account's address.
	 * @return bool
	 */
	private static function refused_for( $error, $address ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : null;
		$to   = is_array( $data ) && isset( $data['to'] ) ? $data['to'] : '';

		if ( '' === $to || array() === $to ) {
			return true;
		}

		foreach ( WPCPM_Mail_Capture::recipients( $to, '' ) as $recipient ) {
			if ( 0 === strcasecmp( $recipient, (string) $address ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The refusal `may_invite()` and a lock another request holds both answer.
	 *
	 * @param int $wait The seconds still to wait: to the end of the fifteen minutes, or to the time a
	 *                  held lock runs out.
	 * @return WP_Error `wpcpm_invite_too_soon`, carrying them as `wait`.
	 */
	private static function too_soon( $wait ) {
		return new WP_Error( 'wpcpm_invite_too_soon', self::too_soon_message(), array( 'wait' => (int) $wait ) );
	}

	/**
	 * The sentence `may_invite()` refuses with: the last invitation went less than `INVITE_GAP`
	 * ago, so nothing was sent. A send refused because another request holds the account's lock is
	 * refused in it too, since that other request's invitation is going out as it is read.
	 *
	 * Worded once, for those refusals and for the notice the Institution Dashboard prints after a
	 * press it refused, so they cannot drift apart.
	 *
	 * @return string
	 */
	public static function too_soon_message() {
		return sprintf(
			/* translators: %d: a number of minutes. */
			__( 'This person was sent an invitation less than %d minutes ago. Another one now would cancel the link in it, so nothing was sent.', 'wpcredits-program-manager' ),
			(int) ( self::INVITE_GAP / MINUTE_IN_SECONDS )
		);
	}

	/**
	 * The sentence for an invitation that could not be sent: the account cannot be sent one, or the
	 * send answered an error. An email the mail host refused has its own
	 * (`invite_refused_message()`).
	 *
	 * Worded once, for the `error` a Resend invite press leaves on every accounts screen
	 * (`invite_outcome()`) and for the Institution Dashboard's press, so the two say the same.
	 *
	 * @return string
	 */
	public static function invite_failed_message() {
		return __( 'The invitation could not be sent.', 'wpcredits-program-manager' );
	}

	/**
	 * The sentence for an invitation the mail host refused, for the one who pressed: what happened,
	 * that the earlier link is gone too, and what to do next.
	 *
	 * Core makes the new password-reset key before it hands the email over, so a refused invitation
	 * has already canceled the link in the one before it. Worded once for each reader: the
	 * Administrators, on the accounts screens (`invite_notices()`), and an institution's members, on
	 * the Institution Dashboard, who cannot see the site's email and are sent to their program contact.
	 *
	 * @param string $reader `administrator`, or `member` for an institution's member.
	 * @return string
	 */
	public static function invite_refused_message( $reader = 'administrator' ) {
		if ( 'member' === $reader ) {
			return __( 'The invitation could not be sent, and the link in any earlier invitation no longer works. Try again in a few minutes; if it fails again, write to your program contact.', 'wpcredits-program-manager' );
		}

		return __( 'The site\'s mail host refused the invitation, so nothing was sent, and the link in any earlier invitation no longer works. Send it again once the site\'s email is working.', 'wpcredits-program-manager' );
	}

	/**
	 * The outcome a Resend invite press flashes, from what `send_invite()` answered.
	 *
	 * @param true|WP_Error $result What the send answered.
	 * @return string `invited`, `invite-too-soon`, `invite-mail-refused`, or the shared `error`.
	 */
	public static function invite_outcome( $result ) {
		if ( ! is_wp_error( $result ) ) {
			return 'invited';
		}

		switch ( $result->get_error_code() ) {
			case 'wpcpm_invite_too_soon':
				return 'invite-too-soon';
			case 'wpcpm_invite_refused':
				return 'invite-mail-refused';
			default:
				return 'error';
		}
	}

	/**
	 * The three notices a Resend invite press can leave, worded once for the Students, Mentors,
	 * Institutions and Sponsors screens: sent, too soon, and refused by the mail host.
	 *
	 * The sent notice says the new email replaces the earlier link, because whoever pressed is
	 * usually helping somebody whose link failed, and the old email is still in that inbox.
	 *
	 * @return array<string, array{0: string, 1: string}> Status => notice type and sentence.
	 */
	public static function invite_notices() {
		return array(
			'invited'             => array( 'success', __( 'Invitation email sent. It replaces the link in any earlier invitation, so ask them to use this newest email.', 'wpcredits-program-manager' ) ),
			'invite-too-soon'     => array(
				'warning',
				sprintf(
					/* translators: %d: a number of minutes. */
					__( 'Nothing was sent: this person was sent an invitation less than %d minutes ago, and another one now would cancel the link in it. Ask them to use their newest email, or try again later.', 'wpcredits-program-manager' ),
					(int) ( self::INVITE_GAP / MINUTE_IN_SECONDS )
				),
			),
			'invite-mail-refused' => array( 'error', self::invite_refused_message() ),
		);
	}

	/**
	 * Keep password links out of WordPress.com's support-session detector.
	 *
	 * **The problem this solves.** Every invitation and every "lost your password" mail sends
	 * somebody to a plain `/wp-login.php?action=rp&key=…` address. On a WordPress.com Atomic host
	 * with Jetpack SSO switched off, `WPCOMSH_Support_Session_Detect` redirects logged-out login
	 * requests to `/_wpcomsh_detect_support_session?redirect=…&nonce=…` - and that path is only
	 * served while two things are true at once: the request is proxied, and no detection cookie has
	 * been set yet. Outside that window nothing handles the path, so WordPress 404s and the person
	 * resetting their password gets "That page could not be found" instead.
	 *
	 * Which is a trap, because a password link is exactly the kind of address that gets re-opened:
	 * out of a mailbox a day later, off the back button, out of a message where somebody pasted it
	 * to ask for help. Reported for several mentors on 28 August 2026.
	 *
	 * **Why here and not in the email.** The invitation body could carry the parameter, but that
	 * would only help mail sent afterwards. Opting out at the login screen repairs the links
	 * already sitting in people's inboxes, and covers the reset mail WordPress sends itself, which
	 * this plugin does not build.
	 *
	 * Setting a query parameter to steer another plugin is not pretty. It is done this way because
	 * `QUERY_PARAM_TO_SHORT_CIRCUIT` is the opt-out wpcomsh defines for the purpose, so it survives
	 * them renaming or re-prioritising the callback - which `remove_action()` on their class name
	 * would not.
	 *
	 * Only the password screens are exempted. Detection exists to stop a support session acting on
	 * somebody's behalf, and a plain login is the case it was built for.
	 */
	public static function keep_password_links_working() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which login screen this is, and changing nothing.
		$action = isset( $_GET['action'] ) && is_scalar( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( ! in_array( $action, array( 'rp', 'resetpass', 'lostpassword' ), true ) ) {
			return;
		}

		$_GET['disable-support-session-detection'] = '';
	}

	/**
	 * Cancel whatever is left of a bulk invite.
	 *
	 * The outcome waits on the flash channel of the screen the card was on, which its form names,
	 * and that screen prints it on the page the press comes back to, once, as it prints the outcome
	 * of every other press made there.
	 */
	public static function handle_stop() {
		self::verify( self::ACTION_STOP );

		self::clear_queue();
		self::dismiss_run();

		$channel = WPCPM_Request::posted_key( self::FLASH_FIELD );

		if ( '' !== $channel ) {
			WPCPM_Flash::set( $channel, 'invites-stopped' );
		}

		self::back();
	}

	/**
	 * Clear a finished run from the screen.
	 */
	public static function handle_dismiss() {
		self::verify( self::ACTION_DISMISS );

		self::dismiss_run();

		self::back();
	}

	/**
	 * Capability and nonce check for the shared invite actions.
	 *
	 * @param string $action Nonce action.
	 */
	private static function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Back to whichever module screen the button was on.
	 *
	 * The referer rather than a fixed page, because one pair of handlers serves both screens and
	 * sending a mentor manager to the students list would be its own small bug. No outcome rides in
	 * the address, where no screen reads one, and one a page from before still carries is dropped:
	 * the screens take their outcomes from the flash.
	 */
	private static function back() {
		$url = wp_get_referer();

		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=wpcpm' );
		}

		wp_safe_redirect( remove_query_arg( 'wpcpm_status', $url ) );
		exit;
	}

	/**
	 * Narrow a list of users to one institution.
	 *
	 * The institution is inside the `wpcpm_program` user meta rather than a meta key of its own,
	 * so it cannot be reached with a `meta_query` and is filtered here instead. That is a read per
	 * user, which is fine for a few hundred on an admin screen and would not be for a cron sweep.
	 *
	 * @param int[]  $user_ids    Users to narrow.
	 * @param string $institution Resolved institution name; an empty string means no narrowing.
	 * @return int[]
	 */
	public static function only_institution( array $user_ids, $institution ) {
		$institution = trim( (string) $institution );

		if ( '' === $institution ) {
			return $user_ids;
		}

		$out = array();

		foreach ( $user_ids as $id ) {
			$program = WPCPM_Students_Sync::get_program( (int) $id );

			if ( isset( $program['institution'] ) && trim( (string) $program['institution'] ) === $institution ) {
				$out[] = (int) $id;
			}
		}

		return $out;
	}

	/**
	 * The bulk-invite card: what would be sent, what is being sent, what was sent.
	 *
	 * One renderer for every audience's screen, because a students-shaped copy and a mentors-shaped
	 * copy of a control that emails hundreds of people is two places for the confirmation to be
	 * wrong in.
	 *
	 * @param array $args {
	 *     What to invite, and who.
	 *
	 *     @type string $action  Admin-post action for sending. The module owns this one, because
	 *                           only the module knows which role it is inviting.
	 *     @type int[]  $pending Users who have never been invited.
	 *     @type string $noun    Plural noun for the people, already translated.
	 *     @type array  $button  Optional: the button's words, as `_n_noop()` holds them, with the
	 *                           count first (`%s`, or `%1$s` beside more), each audience's own, so its
	 *                           noun and verb agree with the count. By default the button says
	 *                           "Invite %s person who has never been invited", or "people who have",
	 *                           since the plural noun the card's other sentences take would not agree
	 *                           with a count of one.
	 *     @type array  $button_args Optional: the values the button's words take after the count, in
	 *                           order, such as the institution a list is narrowed to.
	 *     @type array  $hidden  Fields the sending form posts besides its action, name => value.
	 *     @type string $flash   The flash channel the screen reads its outcomes from, which the
	 *                           Stop form names, so the screen says sending stopped.
	 *     @type int    $held    Optional: how many never invited are left out of `pending` because
	 *                           an activation held their invitation (`held_back()`), which the card
	 *                           says, in place of saying every one has been invited when nobody else
	 *                           is left.
	 * }
	 */
	public static function render_invite_card( array $args ) {
		$pending = isset( $args['pending'] ) ? (array) $args['pending'] : array();
		$noun    = isset( $args['noun'] ) ? (string) $args['noun'] : __( 'people', 'wpcredits-program-manager' );
		$held    = isset( $args['held'] ) ? max( 0, (int) $args['held'] ) : 0;
		$run     = self::run();
		$waiting = self::queued();
		$post    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<div class="wpcpm-card wpcpm-invites">';
		echo '<h2>' . esc_html__( 'Invitations', 'wpcredits-program-manager' ) . '</h2>';

		// A run in progress owns the card. Offering "send" while a send is under way is how the
		// same people get two emails.
		if ( $waiting > 0 && ! empty( $run ) ) {
			$done    = max( 0, $run['total'] - $waiting );
			$sent    = max( 0, $done - $run['unsent'] );
			$percent = $run['total'] > 0 ? (int) round( ( $done / $run['total'] ) * 100 ) : 0;
			$next    = wp_next_scheduled( self::CRON_QUEUE );

			printf(
				'<p class="wpcpm-invites__count"><strong>%s</strong></p>',
				esc_html(
					sprintf(
						/* translators: 1: sent so far, 2: total in this run. */
						__( 'Sending: %1$d of %2$d sent.', 'wpcredits-program-manager' ),
						$sent,
						$run['total']
					)
				)
			);

			printf(
				'<div class="wpcpm-invites__bar" role="progressbar" aria-valuenow="%1$d" aria-valuemin="0" aria-valuemax="100" aria-label="%3$s"><span style="width:%1$d%%"></span></div><p class="description">%2$s</p>',
				(int) $percent,
				esc_html(
					sprintf(
						/* translators: 1: how many are left, 2: batch size, 3: human time until the next batch. */
						__( '%1$d still to go. They go out %2$d at a time, so this finishes on its own - you can leave this page. Next batch %3$s.', 'wpcredits-program-manager' ),
						$waiting,
						self::QUEUE_BATCH,
						$next ? sprintf( /* translators: %s: human time difference. */ __( 'in about %s', 'wpcredits-program-manager' ), human_time_diff( time(), $next ) ) : __( 'shortly', 'wpcredits-program-manager' )
					)
				),
				esc_attr__( 'Invitations sent', 'wpcredits-program-manager' )
			);

			// The abort. The batches already sent cannot be recalled, but the rest can - which is
			// worth more than the confirmation dialog, because it is the only remedy that exists
			// after the mistake rather than before it.
			printf(
				'<form method="post" action="%1$s" data-wpcpm-confirm="%2$s">',
				$post, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				esc_attr__( 'Stop sending? Invitations already sent cannot be recalled.', 'wpcredits-program-manager' )
			);
			wp_nonce_field( self::ACTION_STOP );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_STOP ) );

			if ( ! empty( $args['flash'] ) ) {
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FLASH_FIELD ), esc_attr( (string) $args['flash'] ) );
			}

			printf(
				'<button type="submit" class="button">%s</button>',
				esc_html(
					sprintf(
						/* translators: %d: how many are still queued. */
						__( 'Stop and cancel the remaining %d', 'wpcredits-program-manager' ),
						$waiting
					)
				)
			);
			echo '</form>';

			// Cron runs on requests, so the page has to come back for the count to move. Only
			// while something is actually in flight - a page that reloads for ever is its own bug.
			echo '<script>window.setTimeout(function(){window.location.reload();},30000);</script>';
			echo '</div>';

			return;
		}

		if ( ! empty( $run ) && $run['finished'] ) {
			$sent = max( 0, $run['total'] - $run['unsent'] );

			if ( $run['unsent'] > 0 ) {
				$said = sprintf(
					/* translators: 1: how many were sent, 2: the people, e.g. "students", 3: how many were not sent: refused by the mail host, held by another request sending them one, invited in the last fifteen minutes, their account gone, or their invitation held for an Administrator to confirm the account. */
					_n( 'Finished: %1$d invitation sent to %2$s, %3$d not sent.', 'Finished: %1$d invitations sent to %2$s, %3$d not sent.', $sent, 'wpcredits-program-manager' ),
					$sent,
					$noun,
					$run['unsent']
				);
			} else {
				$said = sprintf(
					/* translators: 1: how many were sent, 2: the people, e.g. "students". */
					_n( 'Finished: %1$d invitation sent to %2$s.', 'Finished: %1$d invitations sent to %2$s.', $sent, 'wpcredits-program-manager' ),
					$sent,
					$noun
				);
			}

			printf( '<p class="wpcpm-invites__count"><strong>%s</strong></p>', esc_html( $said ) );

			printf( '<form method="post" action="%s">', $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			wp_nonce_field( self::ACTION_DISMISS );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_DISMISS ) );
			printf( '<button type="submit" class="button-link">%s</button>', esc_html__( 'Dismiss', 'wpcredits-program-manager' ) );
			echo '</form>';
		}

		$held_line = $held > 0 ? sprintf(
			/* translators: 1: how many accounts never invited are left out, 2: the people, e.g. "mentors". */
			_n( '%1$d of the %2$s is left out: their invitation is held until an Administrator confirms the account is theirs, then sends it from its row.', '%1$d of the %2$s are left out: their invitations are held until an Administrator confirms each account is the person\'s, then sends each from its row.', $held, 'wpcredits-program-manager' ),
			$held,
			$noun
		) : '';

		if ( empty( $pending ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					'' !== $held_line ? $held_line : sprintf(
						/* translators: %s: the people, e.g. "students". */
						__( 'Every one of the %s has been invited. To send somebody a fresh link, use Resend invite on their row.', 'wpcredits-program-manager' ),
						$noun
					)
				)
			);
			echo '</div>';

			return;
		}

		$count = count( $pending );

		// **The count is in the button, not only in the prose above it.** What makes a bulk send
		// safe is that nobody can press it without having read how many people it reaches.
		printf(
			'<form method="post" action="%1$s" data-wpcpm-confirm="%2$s">',
			$post, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr(
				sprintf(
					/* translators: 1: how many people, 2: the people, e.g. "students". */
					_n( 'Send an invitation to %1$d of the %2$s? It cannot be recalled once sent.', 'Send an invitation to %1$d of the %2$s? They cannot be recalled once sent.', $count, 'wpcredits-program-manager' ),
					$count,
					$noun
				)
			)
		);
		wp_nonce_field( $args['action'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );

		foreach ( (array) ( isset( $args['hidden'] ) ? $args['hidden'] : array() ) as $name => $value ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
		}
		if ( isset( $args['button'] ) && is_array( $args['button'] ) ) {
			$extra = isset( $args['button_args'] ) ? array_map( 'strval', array_values( (array) $args['button_args'] ) ) : array();
			$label = vsprintf( translate_nooped_plural( $args['button'], $count, 'wpcredits-program-manager' ), array_merge( array( number_format_i18n( $count ) ), $extra ) );
		} else {
			$label = sprintf(
				/* translators: %s: how many people. */
				_n( 'Invite %s person who has never been invited', 'Invite %s people who have never been invited', $count, 'wpcredits-program-manager' ),
				number_format_i18n( $count )
			);
		}

		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html( $label ) );
		echo '</form>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: batch size. */
					__( 'Anybody already invited is skipped, so this is safe to run twice. Messages go out %d at a time in the background.', 'wpcredits-program-manager' ),
					self::QUEUE_BATCH
				)
			)
		);

		if ( '' !== $held_line ) {
			printf( '<p class="description">%s</p>', esc_html( $held_line ) );
		}

		echo '</div>';
	}

	/**
	 * Everybody holding a role who has never been sent an invitation: none of the stamps there.
	 *
	 * Every stamp, not the role's own alone: an account has one password, so one sent an invitation
	 * under another of its roles was sent one, as the queue (`queue_invites()`) and the gap
	 * (`may_invite()`) read it and as each accounts list's views count. `NOT EXISTS` rather than an
	 * empty-value test: every route stamps as it sends (`stamp_invited()`), so absence is the only
	 * reliable "never invited". A stamp of 0 would mean somebody wrote one. A clause a stamp, not one
	 * clause of the list: WordPress binds one key in the join a `NOT EXISTS` clause makes, and each
	 * such join holds at most one row an account.
	 *
	 * An account whose invitation an activation held (`is_held()`, 1.122.33) is left out, so the
	 * invitations card's button never sends it: only the account's own row does. The card names how
	 * many were left out so (`held_back()`).
	 *
	 * @param string $role Role slug.
	 * @param string $meta The role's own stamp, as its accounts table names it
	 *                     (`WPCPM_Accounts_Table::invite_meta()`), which says whether the plugin
	 *                     invites the role at all: '' for one it never invites, whose accounts are
	 *                     all never invited, whatever stamps they carry. For any other role every
	 *                     stamp is read.
	 * @return int[] User IDs.
	 */
	public static function never_invited( $role, $meta ) {
		return self::unstamped( $role, $meta, false );
	}

	/**
	 * Everybody holding a role who has never been sent an invitation and whose invitation an
	 * activation held (`is_held()`, 1.122.33): the accounts `never_invited()` leaves out for that,
	 * whom the invitations card names. None where no account can carry the hold.
	 *
	 * @param string $role Role slug.
	 * @param string $meta The role's own stamp, as `never_invited()` takes it.
	 * @return int[] User IDs.
	 */
	public static function held_back( $role, $meta ) {
		return '' === self::held_key() ? array() : self::unstamped( $role, $meta, true );
	}

	/**
	 * The accounts of a role that carry none of the stamps, with an invitation an activation held or
	 * without one, as `never_invited()` and `held_back()` read them.
	 *
	 * @param string $role Role slug.
	 * @param string $meta The role's own stamp, as `never_invited()` takes it.
	 * @param bool   $held Whether to answer the held ones, rather than the others.
	 * @return int[] User IDs.
	 */
	private static function unstamped( $role, $meta, $held ) {
		// The empty key, which WordPress finds on no account, for a role the plugin never invites.
		$keys  = '' === (string) $meta ? array( '' ) : array_values( self::STAMPS );
		$query = array( 'relation' => 'AND' );

		foreach ( $keys as $key ) {
			$query[] = array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			);
		}

		if ( '' !== self::held_key() ) {
			$query[] = array(
				'key'     => self::held_key(),
				'compare' => $held ? 'EXISTS' : 'NOT EXISTS',
			);
		}

		// Each ID through `WPCPM_Roles::id_of()`, because WordPress can answer a request for IDs with
		// rows: cast to an int, every row is account 1, and the queue, which keeps an account once,
		// would hold account 1 alone.
		return array_values(
			array_filter(
				array_map(
					array( 'WPCPM_Roles', 'id_of' ),
					get_users(
						array(
							'role'        => $role,
							'fields'      => 'ID',
							'number'      => -1,
							'orderby'     => 'ID',
							'count_total' => false,
							'meta_query'  => $query,
						)
					)
				)
			)
		);
	}

	/**
	 * Add to the running bulk invite's count of accounts passed over without an email.
	 *
	 * @param int $unsent How many this batch passed over.
	 */
	private static function count_unsent( $unsent ) {
		$run = self::run();

		if ( (int) $unsent < 1 || empty( $run ) || $run['finished'] ) {
			return;
		}

		$run['unsent'] += (int) $unsent;

		update_option( self::RUN_OPTION, $run, false );
	}

	/**
	 * Record that a run has nothing left to send.
	 *
	 * Kept rather than deleted, so the screen can say "241 invitations sent" instead of the card
	 * simply vanishing and leaving somebody wondering whether it ran at all.
	 */
	private static function finish_run() {
		$run = self::run();

		if ( empty( $run ) || $run['finished'] ) {
			return;
		}

		$run['finished'] = time();

		update_option( self::RUN_OPTION, $run, false );
	}

	/**
	 * Drop everybody from the queue.
	 */
	public static function clear_queue() {
		delete_option( self::QUEUE_OPTION );
		wp_clear_scheduled_hook( self::CRON_QUEUE );
	}

	/**
	 * Put the drain on the clock when invitations are waiting and nothing will send them.
	 *
	 * The drain is a single event booked only when somebody is queued, so an event lost any other
	 * way (a deactivation, a cron array rewritten under it) leaves a queue that no request sends.
	 * Called by activation, by the functions that queue somebody, and on `admin_init` through
	 * `maybe_schedule_drain()`. The event is looked for before the queue is read: the queue option
	 * is not autoloaded, so with an event booked, which is the common state of a busy queue, the
	 * option is not read at all.
	 */
	public static function schedule_drain() {
		if ( wp_next_scheduled( self::CRON_QUEUE ) || array() === self::queue() ) {
			return;
		}

		wp_schedule_single_event( time() + 60, self::CRON_QUEUE );
	}

	/**
	 * `admin_init`: book the drain again when a wp-admin visit finds a queue with nothing to send it.
	 *
	 * `admin_init` fires on every wp-admin screen for every signed-in user, and on `admin-ajax.php`
	 * and `admin-post.php` for anonymous requests too, which include the public AJAX calls many
	 * themes and plugins make. Only a request that is not AJAX, made by somebody who manages the
	 * program, gets past the first line: an AJAX request (Heartbeat among them) and everybody else
	 * return before the queue option is read. Not on `init`, which every front-end request fires.
	 */
	public static function maybe_schedule_drain() {
		if ( wp_doing_ajax() || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		self::schedule_drain();
	}

	/**
	 * Deactivation: the drain off the clock, the queue kept.
	 *
	 * The queue is program data and the event is scheduled work, the line the plugin's main file
	 * draws for roles and users. Activation, or the next wp-admin visit of somebody who manages the
	 * program, books the drain again (`maybe_schedule_drain()`).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_QUEUE );
	}

	/*
	 * The invitation itself
	 * --------------------------------------------------------------------
	 */

	/**
	 * Give the login invitation some context.
	 *
	 * WordPress sends a username, a reset link and a login URL. Arriving cold that reads as
	 * a phishing attempt: a site the reader may not recognize telling them to set a password,
	 * with no mention of the program, who they are to it, or what to do next. A mentor, a
	 * student and an institution's contact are also arriving for entirely different reasons,
	 * and get different copy.
	 *
	 * The reset link's one-day expiry is left exactly as WordPress sets it. Extending it here
	 * would lengthen the window on every password reset on the site, not just these - so
	 * instead the mail says what to do when the link has gone stale, which is the case that
	 * actually bites when ninety invitations go out and some are opened on Thursday.
	 *
	 * The wording is the template registry's (`WPCPM_Mail_Templates`, the Invitations area), which
	 * an Administrator can edit in WPCredits Program > Tools > Emails; this works out which of the
	 * five invitations the account is sent and the values its placeholders take. The mentor's
	 * carries the welcome a new mentor is sent, which asks for a reply, and so the program's
	 * Reply-To (1.122.33).
	 *
	 * @param array   $email    `to`, `subject`, `message`, `headers`.
	 * @param WP_User $user     The new user.
	 * @param string  $blogname Site name.
	 * @return array
	 */
	public static function welcome_email( $email, $user, $blogname ) {
		if ( ! $user instanceof WP_User ) {
			return $email;
		}

		$is_mentor      = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR );
		$is_student     = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT );
		$is_institution = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION );
		$is_sponsor     = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR );

		// Not one of ours. Somebody else's plugin, or a hand-made account.
		if ( ! $is_mentor && ! $is_student && ! $is_institution && ! $is_sponsor ) {
			return $email;
		}

		// One audience per message, in this order, whatever the account is stamped for: the
		// template is what the person reads, while `stamp_invited()` stamps an account for each of
		// the four program roles it holds, because the stamps are what the lists read. A mentor can
		// also be an institution's member (membership is added to an existing account, never the
		// other way round), and that account was a mentor's before it was anything else. The same
		// holds for a sponsor's representative who is also a mentor - sponsorship is likewise added
		// to an existing account, never the reverse - so mentor still wins there too.
		if ( $is_mentor ) {
			$kind = 'mentor';
		} elseif ( $is_institution ) {
			$kind = 'institution';
		} elseif ( $is_sponsor ) {
			$kind = 'sponsor';
		} else {
			$kind = 'student';
		}

		$id = 'invite-' . $kind;

		if ( 'mentor' === $kind ) {
			$page = WPCPM_Mentors_Dashboard::page_url();
		} elseif ( 'institution' === $kind ) {
			// Whether the institution's Collaboration Agreement is already on file, asked of the
			// agreement module at send time, because the two next steps are different in kind: a
			// new institution has to produce and sign the agreement before anything else opens,
			// while one whose agreement is on file walks straight in. So the two are two templates.
			// Array callables rather than static calls, because `bin/check-references.php` would
			// flag a call to a method of a class a site may not have loaded.
			if ( class_exists( 'WPCPM_Institution_Agreement' ) && class_exists( 'WPCPM_Institution_Members' ) ) {
				$record = (string) call_user_func( array( 'WPCPM_Institution_Members', 'institution_of' ), $user );

				if ( '' !== $record && (bool) call_user_func( array( 'WPCPM_Institution_Agreement', 'is_settled' ), $record ) ) {
					$id = 'invite-institution-settled';
				}
			}

			// No line pointing at the dashboard while there is no address to print.
			$page = method_exists( 'WPCPM_Institutions_Dashboard', 'page_url' )
				? (string) call_user_func( array( 'WPCPM_Institutions_Dashboard', 'page_url' ) )
				: '';
		} elseif ( 'sponsor' === $kind ) {
			// An array callable so `bin/check-references.php` never sees a call to a method that is
			// not declared.
			$page = class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' )
				? (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) )
				: '';
		} else {
			$page = WPCPM_Students_Dashboard::page_url();
		}

		// Core's own body, kept whole in the middle rather than rebuilt, so a change to how
		// WordPress words or forms the reset link arrives here on its own: it is the template's
		// `{set_password_link}`. What core prints around the link has changed once already: until
		// WordPress 7.1 the body opened with the username and closed with the plain login page on a
		// line of its own, and 7.1 prints the keyed link alone, which left a sentence here naming
		// "the two addresses above" pointing at one (a mentor's invitation, 14 September 2026;
		// 1.106.1). So the facts a person needs are stated from what the body carries: the username
		// when core left it out, and the login page always, labeled, with core's own bare copy of
		// it taken off the end first so no address appears twice.
		$body  = rtrim( (string) $email['message'] );
		$login = wp_login_url();
		$tail  = substr( $body, -strlen( $login ) );

		if ( $tail === $login ) {
			$body = rtrim( substr( $body, 0, -strlen( $login ) ) );
		}

		$mail = WPCPM_Mail_Templates::render(
			$id,
			array(
				// As core hands it over, read back from what WordPress stores, and read as every value
				// is by `fill()`; until 1.122.23 the plugin read it back here, as `fill()` did not.
				'site_name'         => (string) $blogname,
				'set_password_link' => $body,
				// The site's language is English, and so is the line core prints, "Username: ...".
				'username'          => preg_match( '/^Username: /m', $body ) ? '' : $user->user_login,
				'login_url'         => $login,
				'dashboard_link'    => $page,
			)
		);

		if ( null === $mail ) {
			return $email;
		}

		// WordPress passes the subject through `sprintf()` with the site's name once more before it
		// sends it, so each "%" is written twice to come out once: a "%" in a site's name, or in a
		// subject an Administrator wrote, would otherwise be read as a format and fail the send.
		$email['subject'] = str_replace( '%', '%%', $mail['subject'] );
		$email['message'] = WPCPM_Mail_Templates::add_footer( $mail['body'], $user, $id );

		// The mentor's invitation carries the welcome, which asks the mentor to reply, so a reply
		// reaches the program and not the site's own sender: the program's Reply-To, while Contact
		// address in emails holds an address. The other invitations ask for no reply.
		if ( 'mentor' === $kind ) {
			$email['headers'] = self::with_reply_to( isset( $email['headers'] ) ? $email['headers'] : '', WPCPM_Mail_Templates::program_contact() );
		}

		// WordPress is about to call `wp_mail()` itself, so this is the only moment at which
		// the log can be told that what follows is an invitation and whose it is.
		self::$context = $id;

		return $email;
	}

	/**
	 * The headers of an email with the program's Reply-To added after the ones it has.
	 *
	 * Core hands the new-account filter `headers` as '' and `wp_mail()` reads a string or an array,
	 * so the answer is an array of lines whatever came in: '' becomes the Reply-To alone, a string
	 * of one or more lines (a line break ends each, as `wp_mail()` reads it) is kept, line by line,
	 * as the first lines before it, and an array keeps its lines as they are. With no address,
	 * nothing changes and what came in goes out as it came.
	 *
	 * @param string|array $headers The email's headers as they stand.
	 * @param string       $contact The program's contact address, or '' for none
	 *                              (`WPCPM_Mail_Templates::program_contact()`).
	 * @return string|array
	 */
	private static function with_reply_to( $headers, $contact ) {
		if ( '' === (string) $contact ) {
			return $headers;
		}

		if ( ! is_array( $headers ) ) {
			$headers = preg_split( '/\R/', trim( (string) $headers ), -1, PREG_SPLIT_NO_EMPTY );
		}

		$headers[] = 'Reply-To: ' . $contact;

		return $headers;
	}
}
