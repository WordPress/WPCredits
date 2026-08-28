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
 *   answering "Call booked with Moldir" is writing to a mailbox nobody reads.
 * - **A record.** `wp_mail()` returns a boolean that every caller discarded, so "the student
 *   says they got nothing" was unanswerable.
 * - **One filter.** `wpcpm_mail` sees subject, body and headers together, so a site can
 *   change any of it without patching a template.
 */
class WPCPM_Mail {

	/** Option holding the recent-mail log. */
	const LOG_OPTION = 'wpcpm_mail_log';

	/** How many sends to remember. Enough to answer a question, not enough to bloat an option. */
	const LOG_MAX = 100;

	/** Option holding user IDs waiting for an invitation. */
	const QUEUE_OPTION = 'wpcpm_invite_queue';

	/** Cron hook that drains the invitation queue. */
	const CRON_QUEUE = 'wpcpm_drain_invite_queue';

	/** Invitations sent per cron run. */
	const QUEUE_BATCH = 10;

	/**
	 * What a bulk invite is working through, so a screen can show progress.
	 *
	 * The queue only knows who is *left*. Sending 241 invitations ten at a time takes the better
	 * part of an hour, and a screen that can only say "231 waiting" leaves somebody with no idea
	 * whether anything is happening — which is how a bulk send gets pressed twice. Holding the
	 * total it started with turns that into "10 of 241 sent".
	 */
	const RUN_OPTION = 'wpcpm_invite_run';

	/** Admin-post action for the sample send. */
	const ACTION_TEST = 'wpcpm_send_test_mail';

	/** Admin-post action that cancels whatever is left of a bulk invite. */
	const ACTION_STOP = 'wpcpm_stop_invites';

	/** Admin-post action that clears a finished run from the screen. */
	const ACTION_DISMISS = 'wpcpm_dismiss_invite_run';

	/**
	 * What is currently being sent, if it is ours.
	 *
	 * Set immediately before handing a message to `wp_mail()` and read by the outcome hooks.
	 * Empty means the message belongs to WordPress or another plugin, and is none of this
	 * log's business — the log exists to answer "did *our* mail arrive", and a site's entire
	 * mail volume would bury that.
	 *
	 * @var string
	 */
	private static $context = '';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'welcome_email' ), 10, 3 );
		add_action( self::CRON_QUEUE, array( __CLASS__, 'drain_queue' ) );
		add_action( 'admin_post_' . self::ACTION_TEST, array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_' . self::ACTION_STOP, array( __CLASS__, 'handle_stop' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'handle_dismiss' ) );

		// The outcome, rather than the attempt. `wp_mail()` returns a boolean that says
		// whether the message was accepted for delivery; these two hooks carry the same
		// answer and also fire for the invitations, which WordPress sends itself and which
		// therefore never pass through `send()` at all.
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'mail_succeeded' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'mail_failed' ) );
	}

	/**
	 * Record a message that was accepted for delivery.
	 *
	 * @param array $mail_data `to`, `subject`, `message`, `headers`, `attachments`.
	 */
	public static function mail_succeeded( $mail_data ) {
		self::record( (array) $mail_data, true );
	}

	/**
	 * Record a message that was refused.
	 *
	 * @param WP_Error $error The failure, carrying the message in its error data.
	 */
	public static function mail_failed( $error ) {
		$data = $error instanceof WP_Error ? $error->get_error_data() : array();

		self::record( is_array( $data ) ? $data : array(), false );
	}

	/*
	 * Sending
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send one message to one person.
	 *
	 * @param int|WP_User $recipient Who it is for. Their locale is used to build it.
	 * @param string      $context   Short label for the log, e.g. `call-booked`.
	 * @param callable    $build     Receives the recipient as a `WP_User` and returns an
	 *                               array with `subject`, `body`, and optionally `headers`,
	 *                               `attachments` and `cleanup`.
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
		// supports 6.5 — but a site can be on a version where the function is absent for
		// other reasons, and mail in the wrong language beats no mail.
		$switched = function_exists( 'switch_to_user_locale' ) ? switch_to_user_locale( $user->ID ) : false;

		$mail = (array) call_user_func( $build, $user );

		$mail = wp_parse_args(
			$mail,
			array(
				'subject'     => '',
				'body'        => '',
				'headers'     => array(),
				'attachments' => array(),
				'cleanup'     => array(),
			)
		);

		/**
		 * Filter a message before it is sent.
		 *
		 * Runs inside the recipient's locale, so anything added here is translated the same
		 * way the template was.
		 *
		 * @param array   $mail      Subject, body, headers, attachments.
		 * @param string  $context   Message context.
		 * @param WP_User $recipient Recipient.
		 */
		$mail = (array) apply_filters( 'wpcpm_mail', $mail, $context, $user );

		$sent = false;

		if ( '' !== trim( (string) $mail['subject'] ) ) {
			self::$context = sanitize_key( $context );

			$sent = wp_mail(
				$user->user_email,
				// A subject is a header: the CR and LF that would turn one into several are
				// stripped here rather than at each template, because the names that reach a
				// subject come from Airtable columns and WordPress profiles and are not
				// constants this code controls.
				sanitize_text_field( (string) $mail['subject'] ),
				(string) $mail['body'],
				(array) $mail['headers'],
				array_filter( (array) $mail['attachments'] )
			);

			self::$context = '';
		}

		foreach ( (array) $mail['cleanup'] as $path ) {
			WPCPM_ICS::cleanup( $path );
		}

		if ( $switched ) {
			restore_previous_locale();
		}

		return (bool) $sent;
	}

	/**
	 * A `Reply-To` header pointing at the other person in a conversation.
	 *
	 * @param WP_User|null $person Who the recipient would be replying to.
	 * @return array Headers array, empty when there is nobody to reply to.
	 */
	public static function reply_to( $person ) {
		if ( ! $person instanceof WP_User || '' === (string) $person->user_email ) {
			return array();
		}

		// The display name is quoted and stripped of the characters that would end the quoted
		// string early — a name is user data and this is a header.
		$name = str_replace( array( '"', '\\', "\r", "\n", ',', ';' ), ' ', (string) $person->display_name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );

		return array(
			'' === $name
				? sprintf( 'Reply-To: %s', $person->user_email )
				: sprintf( 'Reply-To: "%1$s" <%2$s>', $name, $person->user_email ),
		);
	}

	/**
	 * The site's name, decoded for use in a subject line.
	 *
	 * @return string
	 */
	public static function site_name() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/*
	 * The log
	 * --------------------------------------------------------------------
	 */

	/**
	 * Remember one send, if it was ours.
	 *
	 * @param array $mail_data The message, as `wp_mail()` saw it.
	 * @param bool  $sent      Outcome.
	 */
	private static function record( array $mail_data, $sent ) {
		if ( '' === self::$context ) {
			return;
		}

		$context = self::$context;

		// Cleared here rather than by the caller: whichever of the two outcome hooks fires,
		// this message is finished, and a context left standing would mislabel whatever the
		// site sends next.
		self::$context = '';

		$to = isset( $mail_data['to'] ) ? $mail_data['to'] : '';
		$to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

		$log = self::log();

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'to'      => sanitize_text_field( $to ),
				'context' => $context,
				'subject' => sanitize_text_field( isset( $mail_data['subject'] ) ? (string) $mail_data['subject'] : '' ),
				'sent'    => (bool) $sent,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/**
	 * The recent-mail log, newest first.
	 *
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Forget every recorded send.
	 */
	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * How many of the recorded sends failed.
	 *
	 * @return int
	 */
	public static function failures() {
		$failed = 0;

		foreach ( self::log() as $entry ) {
			if ( empty( $entry['sent'] ) ) {
				++$failed;
			}
		}

		return $failed;
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
	 * middle, and no way to know how far it got — so the sync records who needs one and cron
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
		if ( ! wp_next_scheduled( self::CRON_QUEUE ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_QUEUE );
		}
	}

	/**
	 * Queue invitations for people who have never had one, and start a run the screen can report on.
	 *
	 * Not a loop over `queue_invite()`: that would write the option once per person, and 241 writes
	 * of a growing array is a lot of work to do inside one admin request.
	 *
	 * **Anyone already invited is dropped here, not only by the caller.** The screen builds its
	 * list from `never_invited()`, so in practice the two agree — but the list is built when the
	 * page renders and acted on when the button is pressed, and a batch can go out in between. The
	 * caller being careful is not the same as the send being safe, and this is the send.
	 *
	 * That makes this the wrong function for a deliberate re-invitation. There is one of those
	 * already: `send_invite()`, behind Resend invite on a person's row, which is one message to one
	 * named person rather than a few hundred at once.
	 *
	 * @param int[] $user_ids Users to invite.
	 * @return int How many were added, after dropping duplicates, anyone queued, and anyone sent to.
	 */
	public static function queue_invites( array $user_ids ) {
		$queue = self::queue();
		$ids   = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		$fresh = array();

		foreach ( array_diff( $ids, $queue ) as $id ) {
			// Either stamp: this does not need to know which kind of person it is looking at, and
			// an account holding both roles has still been written to either way.
			if ( get_user_meta( $id, 'wpcpm_student_invited', true ) || get_user_meta( $id, 'wpcpm_mentor_invited', true ) ) {
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
			),
			false
		);

		if ( ! wp_next_scheduled( self::CRON_QUEUE ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_QUEUE );
		}

		return count( $fresh );
	}

	/**
	 * The bulk invite currently running, or the last one to finish.
	 *
	 * @return array{total:int,started:int,finished:int}|array{} Empty when there has been none.
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
	 */
	public static function drain_queue() {
		$queue = self::queue();

		if ( empty( $queue ) ) {
			delete_option( self::QUEUE_OPTION );
			self::finish_run();

			return;
		}

		$batch = array_splice( $queue, 0, self::QUEUE_BATCH );

		// Written back *before* sending. If the batch dies halfway — a fatal, a timeout, a
		// mail host refusing the connection — the names in it are already out of the queue,
		// so the next run moves on instead of retrying the same ten for ever.
		if ( empty( $queue ) ) {
			delete_option( self::QUEUE_OPTION );
		} else {
			update_option( self::QUEUE_OPTION, $queue, false );
		}

		foreach ( $batch as $user_id ) {
			$user = get_user_by( 'id', $user_id );

			if ( ! $user instanceof WP_User ) {
				continue;
			}

			wp_new_user_notification( $user->ID, null, 'user' );

			$meta = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR )
				? 'wpcpm_mentor_invited'
				: 'wpcpm_student_invited';

			update_user_meta( $user->ID, $meta, time() );
		}

		if ( ! empty( $queue ) ) {
			wp_schedule_single_event( time() + 120, self::CRON_QUEUE );
		} else {
			// Stamped only once the last batch has actually been sent, not when the queue option
			// was emptied above — that happens *before* sending, so the two are not the same
			// moment and a screen reading the earlier one would say "finished" mid-send.
			self::finish_run();
		}
	}

	/**
	 * Cancel whatever is left of a bulk invite.
	 */
	public static function handle_stop() {
		self::verify( self::ACTION_STOP );

		self::clear_queue();
		self::dismiss_run();

		self::back( 'invites-stopped' );
	}

	/**
	 * Clear a finished run from the screen.
	 */
	public static function handle_dismiss() {
		self::verify( self::ACTION_DISMISS );

		self::dismiss_run();

		self::back( '' );
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
	 * sending a mentor manager to the students list would be its own small bug.
	 *
	 * @param string $status Status slug, or an empty string for none.
	 */
	private static function back( $status ) {
		$url = wp_get_referer();

		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=wpcpm' );
		}

		$url = remove_query_arg( 'wpcpm_status', $url );

		if ( '' !== $status ) {
			$url = add_query_arg( 'wpcpm_status', $status, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * The bulk-invite card: what would be sent, what is being sent, what was sent.
	 *
	 * One renderer for both module screens, because a students-shaped copy and a mentors-shaped
	 * copy of a control that emails hundreds of people is two places for the confirmation to be
	 * wrong in.
	 *
	 * @param array $args {
	 *     What to invite, and who.
	 *
	 *     @type string $action  Admin-post action for sending. The module owns this one, because
	 *                           only the module knows which role it is inviting.
	 * }
	 *     @type int[]  $pending Users who have never been invited.
	 *     @type string $noun    Plural noun for the people, already translated.
	 * }
	 */
	public static function render_invite_card( array $args ) {
		$pending = isset( $args['pending'] ) ? (array) $args['pending'] : array();
		$noun    = isset( $args['noun'] ) ? (string) $args['noun'] : __( 'people', 'wpcredits-program-manager' );
		$run     = self::run();
		$waiting = self::queued();
		$post    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<div class="wpcpm-card wpcpm-invites">';
		echo '<h2>' . esc_html__( 'Invitations', 'wpcredits-program-manager' ) . '</h2>';

		// A run in progress owns the card. Offering "send" while a send is under way is how the
		// same people get two emails.
		if ( $waiting > 0 && ! empty( $run ) ) {
			$sent    = max( 0, $run['total'] - $waiting );
			$percent = $run['total'] > 0 ? (int) round( ( $sent / $run['total'] ) * 100 ) : 0;
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
						__( '%1$d still to go. They go out %2$d at a time, so this finishes on its own — you can leave this page. Next batch %3$s.', 'wpcredits-program-manager' ),
						$waiting,
						self::QUEUE_BATCH,
						$next ? sprintf( /* translators: %s: human time difference. */ __( 'in about %s', 'wpcredits-program-manager' ), human_time_diff( time(), $next ) ) : __( 'shortly', 'wpcredits-program-manager' )
					)
				),
				esc_attr__( 'Invitations sent', 'wpcredits-program-manager' )
			);

			// The abort. The batches already sent cannot be recalled, but the rest can — which is
			// worth more than the confirmation dialog, because it is the only remedy that exists
			// after the mistake rather than before it.
			printf(
				'<form method="post" action="%1$s" onsubmit="return confirm(\'%2$s\');">',
				$post, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				esc_js( __( 'Stop sending? Invitations already sent cannot be recalled.', 'wpcredits-program-manager' ) )
			);
			wp_nonce_field( self::ACTION_STOP );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_STOP ) );
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
			// while something is actually in flight — a page that reloads for ever is its own bug.
			echo '<script>window.setTimeout(function(){window.location.reload();},30000);</script>';
			echo '</div>';

			return;
		}

		if ( ! empty( $run ) && $run['finished'] ) {
			printf(
				'<p class="wpcpm-invites__count"><strong>%s</strong></p>',
				esc_html(
					sprintf(
						/* translators: 1: how many were sent, 2: the people, e.g. "students". */
						_n( 'Finished: %1$d invitation sent to %2$s.', 'Finished: %1$d invitations sent to %2$s.', $run['total'], 'wpcredits-program-manager' ),
						$run['total'],
						$noun
					)
				)
			);

			printf( '<form method="post" action="%s">', $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			wp_nonce_field( self::ACTION_DISMISS );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_DISMISS ) );
			printf( '<button type="submit" class="button-link">%s</button>', esc_html__( 'Dismiss', 'wpcredits-program-manager' ) );
			echo '</form>';
		}

		if ( empty( $pending ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
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
			'<form method="post" action="%1$s" onsubmit="return confirm(\'%2$s\');">',
			$post, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_js(
				sprintf(
					/* translators: 1: how many people, 2: the people, e.g. "students". */
					__( 'Send an invitation to %1$d %2$s? They cannot be recalled once sent.', 'wpcredits-program-manager' ),
					$count,
					$noun
				)
			)
		);
		wp_nonce_field( $args['action'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
		printf(
			'<button type="submit" class="button button-primary">%s</button>',
			esc_html(
				sprintf(
					/* translators: 1: how many people, 2: the people, e.g. "students". */
					_n( 'Invite %1$d %2$s who has never been invited', 'Invite %1$d %2$s who have never been invited', $count, 'wpcredits-program-manager' ),
					$count,
					$noun
				)
			)
		);
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

		echo '</div>';
	}

	/**
	 * Everybody holding a role who has never been sent an invitation.
	 *
	 * `NOT EXISTS` rather than an empty-value test: `drain_queue()` stamps the meta as it sends, so
	 * absence is the only reliable "never invited". A stamp of 0 would mean somebody wrote one.
	 *
	 * @param string $role Role slug.
	 * @param string $meta Meta key holding the invitation timestamp.
	 * @return int[] User IDs.
	 */
	public static function never_invited( $role, $meta ) {
		return array_map(
			'intval',
			get_users(
				array(
					'role'        => $role,
					'fields'      => 'ID',
					'number'      => -1,
					'orderby'     => 'ID',
					'count_total' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Run once, from an admin screen, over a few hundred users.
					'meta_query'  => array(
						array(
							'key'     => $meta,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);
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

	/*
	 * The invitation itself
	 * --------------------------------------------------------------------
	 */

	/**
	 * Give the login invitation some context.
	 *
	 * WordPress sends a username, a reset link and a login URL. Arriving cold that reads as
	 * a phishing attempt: a site the reader may not recognise telling them to set a password,
	 * with no mention of the program, who they are to it, or what to do next. A mentor and a
	 * student are also arriving for entirely different reasons, and get different copy.
	 *
	 * The reset link's one-day expiry is left exactly as WordPress sets it. Extending it here
	 * would lengthen the window on every password reset on the site, not just these — so
	 * instead the mail says what to do when the link has gone stale, which is the case that
	 * actually bites when ninety invitations go out and some are opened on Thursday.
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

		$is_mentor  = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR );
		$is_student = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT );

		// Not one of ours. Somebody else's plugin, or a hand-made account.
		if ( ! $is_mentor && ! $is_student ) {
			return $email;
		}

		$page = $is_mentor
			? WPCPM_Mentors_Dashboard::page_url()
			: WPCPM_Students_Dashboard::page_url();

		$opening = $is_mentor
			? __( 'You have been set up as a mentor on the WordPress Credits Program, and this is your account on the program site. Your students, their programs and the calls they book with you all live there.', 'wpcredits-program-manager' )
			: __( 'You have been enrolled on the WordPress Credits Program, and this is your account on the program site. Your program details, your mentor and your report form all live there.', 'wpcredits-program-manager' );

		$next = $is_mentor
			? __( 'Set your password using the link below, then publish the hours you are free so your students can book calls with you.', 'wpcredits-program-manager' )
			: __( 'Set your password using the link below, then check your details and book your first call with your mentor.', 'wpcredits-program-manager' );

		$lines = array(
			$opening,
			'',
			$next,
			'',
			// Core's own body: username, the reset link and the login URL. Kept whole and in
			// the middle rather than rebuilt, so a change to how WordPress words or forms
			// that link arrives here on its own.
			rtrim( (string) $email['message'] ),
			'',
			// Names both addresses rather than saying "the link above". WordPress prints two —
			// the keyed reset link and then the plain login page — and unlabelled they read as
			// the same address twice, which is what prompted this wording.
			__( 'Of the two addresses above, the long one sets your password and stops working after a day. The short one is the login page, for every time after that. If the password link has expired, open the login page, choose "Lost your password?" and enter this username or your email address to get a fresh one.', 'wpcredits-program-manager' ),
		);

		if ( '' !== $page ) {
			$lines[] = '';
			$lines[] = $is_mentor
				? __( 'Your students:', 'wpcredits-program-manager' )
				: __( 'Your report card:', 'wpcredits-program-manager' );
			$lines[] = $page;
		}

		$email['subject'] = sprintf(
			$is_mentor
				/* translators: %s: site name. */
				? __( '[%s] Your mentor account is ready', 'wpcredits-program-manager' )
				/* translators: %s: site name. */
				: __( '[%s] Welcome to the WordPress Credits Program', 'wpcredits-program-manager' ),
			wp_specialchars_decode( (string) $blogname, ENT_QUOTES )
		);

		$email['message'] = implode( "\r\n", $lines ) . "\r\n";

		// WordPress is about to call `wp_mail()` itself, so this is the only moment at which
		// the log can be told that what follows is an invitation and whose it is.
		self::$context = $is_mentor ? 'invite-mentor' : 'invite-student';

		return $email;
	}

	/**
	 * Send the current user a sample of the invitation.
	 *
	 * Ninety people is a bad audience for a first look at a template.
	 */
	public static function handle_test() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( self::ACTION_TEST, self::ACTION_TEST );

		// `posted_key()`, not `key()`: the two buttons are forms that post to `admin-post.php`,
		// so the audience arrives in `$_POST`. Reading `$_GET` returned the fallback every
		// time, which meant both buttons sent the student template.
		$kind = WPCPM_Request::posted_key( 'kind' );
		$kind = in_array( $kind, array( 'mentor', 'student' ), true ) ? $kind : 'student';

		$viewer = wp_get_current_user();

		// The real filter, against the real user, with the role it is being previewed as
		// stood in temporarily — so what arrives is what a student or mentor would get and
		// not a second template that only looks like it.
		$role = 'mentor' === $kind ? WPCPM_Roles::ROLE_MENTOR : WPCPM_Roles::ROLE_STUDENT;

		$preview = function ( $email, $user, $blogname ) use ( $role ) {
			$stand_in        = clone $user;
			$stand_in->roles = array( $role );

			return self::welcome_email( $email, $stand_in, $blogname );
		};

		$sent = self::send(
			$viewer,
			'test-' . $kind,
			function ( $user ) use ( $preview ) {
				// Core's body has *two* URLs, in this order: the one-use reset link carrying a
				// key, then the plain login page. Standing the login URL in for both made the
				// sample print the same address twice, which reads as a bug in the template
				// rather than a shortcut in the preview. The stand-in below keeps the real
				// shape — same two lines, visibly an example, and not a live reset link,
				// because generating one would invalidate the reader's own password.
				$example_reset = add_query_arg(
					array(
						'action' => 'rp',
						'key'    => 'EXAMPLE-KEY-NOT-A-REAL-LINK',
						'login'  => rawurlencode( $user->user_login ),
					),
					wp_login_url()
				);

				$email = $preview(
					array(
						'to'      => $user->user_email,
						'subject' => sprintf( '[%s] Login Details', self::site_name() ),
						'message' => sprintf(
							/* translators: %s: WordPress username. */
							__( 'Username: %s', 'wpcredits-program-manager' ),
							$user->user_login
						) . "\r\n\r\n"
							. __( 'To set your password, visit the following address:', 'wpcredits-program-manager' ) . "\r\n\r\n"
							. $example_reset . "\r\n\r\n"
							. wp_login_url() . "\r\n",
						'headers' => '',
					),
					$user,
					get_bloginfo( 'name' )
				);

				return array(
					'subject' => $email['subject'],
					'body'    => sprintf(
						/* translators: %s: the invitation email as it would be received. */
						__( "This is a sample of the invitation email, sent to you so you can read it before anybody else does.\n\nTwo differences from the real thing: the reset link below is a marked example rather than a working one, because generating a real one would invalidate your own password, and the second address is the plain login page, exactly as WordPress puts it there.\n\n---\n\n%s", 'wpcredits-program-manager' ),
						$email['message']
					),
				);
			}
		);

		WPCPM_Flash::set( 'settings', $sent ? 'test-sent' : 'test-failed' );

		wp_safe_redirect( WPCPM_Admin::settings_url() );
		exit;
	}
}
