<?php
/**
 * The AI Mentor card's markup, its script and its style.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the AI Mentor card on the Student Report Card draws, once `WPCPM_Tutor::render_card()` has decided
 * who sees what: the panel for the student themselves, and the one line a manager reads on a student's card.
 *
 * The panel is drawn on the server from the transcript of the last 90 days, so a reload shows the
 * conversation as it stands, and `assets/js/tutor.js` adds each new turn to it. What was said is text,
 * on the server and in the script alike: escaped, its line breaks kept, never a reply's markup. The script
 * and its style load only on a page that draws the panel, so a manager's line and every other card load
 * neither.
 */
final class WPCPM_Tutor_Card {

	/** The handle of the card's script and of its style. */
	const HANDLE = 'wpcpm-tutor';

	/** How many turns the panel shows; the older ones of the 90 days wait behind "Show earlier". */
	const SHOWN = 30;

	/**
	 * The panel: the heading and the line that says what the AI Mentor is, the conversation (the newest
	 * turns shown, the older ones drawn hidden), the working, failure and limit lines the script shows,
	 * the box with **Send** and the day's messages left, and under it the note of where the messages go.
	 * Past the day's limit the box and **Send** are disabled and the limit sentence is shown. The caller
	 * has checked `may_chat()`.
	 *
	 * @param WP_User $student The student, who is the reader.
	 * @return string Escaped markup.
	 */
	public static function panel( WP_User $student ): string {
		$turns  = WPCPM_Tutor::turns_a_day();
		$left   = WPCPM_Tutor::left_today( (int) $student->ID );
		$closed = $left < 1 ? ' disabled' : '';
		$all    = self::turns( WPCPM_Tutor_Store::since( (int) $student->ID, WPCPM_Tutor_Store::KEEP_DAYS ) );
		$older  = max( 0, count( $all ) - self::SHOWN );

		self::enqueue( $turns );

		$out  = '<section class="wpcpm-student__section wpcpm-tutor" id="wpcpm-tutor" data-wpcpm-tutor>';
		$out .= '<h3 class="wpcpm-student__heading" id="wpcpm-tutor-title">' . esc_html__( 'AI Mentor', 'wpcredits-program-manager' ) . '</h3>';
		$out .= '<p class="wpcpm-student__note wpcpm-tutor__intro">' . esc_html__( 'AI Mentor answers from the program\'s guides. It can be wrong; your mentor has the final word.', 'wpcredits-program-manager' ) . '</p>';

		// A log, so what the script adds is announced, and focusable, so a keyboard can scroll it. Hidden
		// while there is nothing in it; the script shows it with the first turn.
		$out .= '<div class="wpcpm-tutor__log" data-wpcpm-tutor-log role="log" aria-labelledby="wpcpm-tutor-title" tabindex="0"' . ( array() === $all ? ' hidden' : '' ) . '>';

		if ( $older > 0 ) {
			$out .= '<p class="wpcpm-tutor__more"><button type="button" class="wpcpm-tutor__earlier" data-wpcpm-tutor-earlier>' . esc_html__( 'Show earlier', 'wpcredits-program-manager' ) . '</button></p>';
		}

		$out .= '<ol class="wpcpm-tutor__turns" data-wpcpm-tutor-turns>';

		foreach ( $all as $index => $turn ) {
			$out .= self::turn( $turn, $index < $older );
		}

		$out .= '</ol></div>';
		$out .= '<p class="wpcpm-student__note wpcpm-tutor__working" data-wpcpm-tutor-working role="status" hidden></p>';
		$out .= '<p class="wpcpm-tutor__failed" data-wpcpm-tutor-failed role="alert" hidden>' . esc_html__( 'That did not work. Try again in a moment.', 'wpcredits-program-manager' ) . '</p>';
		$out .= '<p class="wpcpm-tutor__limit" data-wpcpm-tutor-limit role="status"' . ( '' === $closed ? ' hidden' : '' ) . '>' . esc_html( WPCPM_Tutor::limit_sentence( $turns ) ) . '</p>';

		// No name on the box, so a form posted without the script carries nothing of what was typed.
		$out .= '<form class="wpcpm-tutor__form" data-wpcpm-tutor-form method="post">';
		$out .= '<label class="screen-reader-text" for="wpcpm-tutor-message">' . esc_html__( 'Your question', 'wpcredits-program-manager' ) . '</label>';
		$out .= '<textarea id="wpcpm-tutor-message" class="wpcpm-tutor__input" data-wpcpm-tutor-input rows="3" maxlength="' . (int) WPCPM_Tutor::MESSAGE_MAX . '"' . $closed . '></textarea>';
		$out .= '<p class="wpcpm-tutor__actions"><button type="submit" class="wpcpm-button" data-wpcpm-tutor-send' . $closed . '>' . esc_html__( 'Send', 'wpcredits-program-manager' ) . '</button>';
		$out .= '<span class="wpcpm-student__note wpcpm-tutor__left" data-wpcpm-tutor-left>' . esc_html( sprintf( self::left_template( $turns ), $left, $turns ) ) . '</span></p>';
		$out .= '</form>';
		$out .= '<p class="wpcpm-student__note wpcpm-tutor__footnote">' . esc_html( self::footnote() ) . '</p>';

		return $out . '</section>';
	}

	/**
	 * What the student is told under the box about where their messages go, as Need help? tells its
	 * readers under its own: to the provider Connectors names, or to an outside AI service while it names
	 * none; and that they are kept, and who can read them.
	 *
	 * @return string
	 */
	private static function footnote(): string {
		$name = WPCPM_Handbook_Answer::provider_label();

		return sprintf(
			/* translators: %s: the AI provider's name, or "an outside AI service". */
			__( 'Your messages are sent to %s to write the answers. They are kept for 90 days, and the program\'s Administrators can read them. Do not type anything confidential.', 'wpcredits-program-manager' ),
			'' !== $name ? $name : __( 'an outside AI service', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The one line a manager reads on a student's card: how many messages the student sent in the last
	 * 90 days, and **Read the transcript**, the link to the Administrators' view of that student's record;
	 * while the Administrator Dashboard has no page to link to, the count alone. Never a turn's text.
	 *
	 * @param WP_User $student The student whose card it is.
	 * @param int     $kept    How many of their messages the last 90 days keep.
	 * @return string Escaped markup.
	 */
	public static function count_line( WP_User $student, int $kept ): string {
		/* translators: %d: how many messages the student sent the AI Mentor in the last 90 days. */
		$count = esc_html( sprintf( _n( '%d AI Mentor message in the last 90 days.', '%d AI Mentor messages in the last 90 days.', $kept, 'wpcredits-program-manager' ), $kept ) );
		$url   = WPCPM_Administrators_Dashboard::view_url( 'ai-mentor' );

		if ( '' === $url ) {
			return '<p class="wpcpm-student__note wpcpm-tutor__count">' . $count . '</p>';
		}

		$record = WPCPM_Mentor_Calls::student_record( (int) $student->ID );

		// A student with no record yet is listed and nothing more: the link opens the list of accounts.
		if ( '' !== $record ) {
			$url = add_query_arg( 'wpcpm_tutor_record', $record, $url );
		}

		return sprintf(
			'<p class="wpcpm-student__note wpcpm-tutor__count">%1$s <a href="%2$s">%3$s</a></p>',
			$count,
			esc_url( $url ),
			esc_html__( 'Read the transcript', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The conversation as turns, oldest first: a student's message and the reply after it. A reply with
	 * no message before it (the message gone at the 90 days' edge) and a message with no reply after it
	 * (a turn cut short) are each a turn of their own.
	 *
	 * @param array<int, array> $rows `student` and `mentor_ai` rows, oldest first, as `since()` gives them.
	 * @return array<int, array{message: array|null, reply: array|null}>
	 */
	private static function turns( array $rows ): array {
		$turns = array();

		foreach ( $rows as $row ) {
			$last = count( $turns ) - 1;

			if ( WPCPM_Tutor_Store::ROLE_STUDENT === $row['role'] ) {
				$turns[] = array(
					'message' => $row,
					'reply'   => null,
				);
			} elseif ( $last >= 0 && null === $turns[ $last ]['reply'] ) {
				$turns[ $last ]['reply'] = $row;
			} else {
				$turns[] = array(
					'message' => null,
					'reply'   => $row,
				);
			}
		}

		return $turns;
	}

	/**
	 * One turn: the student's message, then the reply with its sources under it, or under a reply with
	 * none, the line that says it is not from the program's guides. Each is named for a screen reader.
	 *
	 * @param array $turn   `message` and `reply`, each a row or null.
	 * @param bool  $hidden Whether it is one of the older turns behind "Show earlier".
	 * @return string Escaped markup.
	 */
	private static function turn( array $turn, bool $hidden ): string {
		$out = '<li class="wpcpm-tutor__turn"' . ( $hidden ? ' hidden' : '' ) . '>';

		if ( null !== $turn['message'] ) {
			$out .= '<div class="wpcpm-tutor__message"><span class="screen-reader-text">' . esc_html__( 'You', 'wpcredits-program-manager' ) . '</span>' . self::text( (string) $turn['message']['body'] ) . '</div>';
		}

		if ( null !== $turn['reply'] ) {
			$sources = self::sources( (array) $turn['reply']['sources'] );

			$out .= '<div class="wpcpm-tutor__reply"><span class="screen-reader-text">' . esc_html__( 'AI Mentor', 'wpcredits-program-manager' ) . '</span>' . self::text( (string) $turn['reply']['body'] );
			$out .= '' !== $sources ? $sources : '<p class="wpcpm-student__note wpcpm-tutor__unsourced">' . esc_html__( 'Not from the program\'s guides', 'wpcredits-program-manager' ) . '</p>';
			$out .= '</div>';
		}

		return $out . '</li>';
	}

	/**
	 * What was said, as text: escaped, in a pre, which wraps and keeps each line break (the style) and is
	 * one of the elements core's `wptexturize()` skips, so a `--`, a `...` or a hyphen between spaces is
	 * shown as it was typed (the dashboards are blocks, rendered before `wptexturize()` runs on the page).
	 * The script draws a new reply as the same element.
	 *
	 * @param string $body The message or the reply.
	 * @return string Escaped markup.
	 */
	private static function text( string $body ): string {
		return '<pre class="wpcpm-tutor__text">' . esc_html( $body ) . '</pre>';
	}

	/**
	 * A reply's sources, a numbered list of links to the guides they came from, source i being the reply's
	 * marker [i]; '' when none has a title and an http or https link. A source left out keeps the next
	 * one at its own number.
	 *
	 * @param array $sources Each `title` and `link`, and a fallback's `extract`, as the store keeps them, in the order of the markers.
	 * @return string Escaped markup, or ''.
	 */
	private static function sources( array $sources ): string {
		$items = '';
		$shown = 0;

		foreach ( array_values( $sources ) as $index => $source ) {
			if ( ! is_array( $source ) || empty( $source['title'] ) || empty( $source['link'] ) || ! preg_match( '#^https?://#i', (string) $source['link'] ) ) {
				continue;
			}

			$number = $index + 1;
			$items .= '<li' . ( ++$shown === $number ? '' : ' value="' . (int) $number . '"' ) . '><a href="' . esc_url( (string) $source['link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $source['title'] ) . '</a>' . self::extract( $source ) . '</li>';
		}

		if ( '' === $items ) {
			return '';
		}

		return '<p class="wpcpm-tutor__sources-title">' . esc_html__( 'Sources', 'wpcredits-program-manager' ) . '</p><ol class="wpcpm-tutor__sources">' . $items . '</ol>';
	}

	/**
	 * A source's extract, the start of the passage a fallback's link goes to, under the link: in a pre, as
	 * what was said is, so core's texturizing leaves it as it is; '' when the source has none (a cited
	 * answer's sources keep none).
	 *
	 * @param array $source A source, as the store keeps it.
	 * @return string Escaped markup, or ''.
	 */
	private static function extract( array $source ): string {
		$extract = isset( $source['extract'] ) && is_string( $source['extract'] ) ? trim( $source['extract'] ) : '';

		return '' === $extract ? '' : '<pre class="wpcpm-tutor__extract">' . esc_html( $extract ) . '</pre>';
	}

	/**
	 * The line of the day's messages left, its numbers to fill in: the panel fills it, and the script
	 * fills it again after each reply. Singular or plural by the day's messages, which a page does not change.
	 *
	 * @param int $turns How many messages a student may send a day.
	 * @return string
	 */
	private static function left_template( int $turns ): string {
		/* translators: 1: how many messages the student has left today, 2: how many they may send a day. */
		return _n( '%1$d of %2$d message left today', '%1$d of %2$d messages left today', $turns, 'wpcredits-program-manager' );
	}

	/**
	 * Load the card's script and style, and hand the script the turn route, R2's send route with the
	 * REST nonce, the limits (the message's, and R2's note's, which the preview keeps to), and the words
	 * it draws: the preview's are R2's, as Need help?'s script is given them
	 * (`WPCPM_Handbook_Assistant::enqueue()`), so the hand-off reads and sends as R2's does. The question
	 * the preview holds is the route's (`WPCPM_Tutor::rest_turn()`), already as R2 keeps it.
	 *
	 * @param int $turns How many messages a student may send a day.
	 */
	private static function enqueue( int $turns ): void {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_register_style( self::HANDLE, WPCPM_PLUGIN_URL . 'assets/css/tutor.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script( self::HANDLE, WPCPM_PLUGIN_URL . 'assets/js/tutor.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		wp_localize_script(
			self::HANDLE,
			'wpcpmTutor',
			array(
				'endpoint'     => esc_url_raw( rest_url( 'wpcpm/v1/tutor/turn' ) ),
				'sendEndpoint' => esc_url_raw( rest_url( 'wpcpm/v1/handbook/send' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'max'          => (int) WPCPM_Tutor::MESSAGE_MAX,
				'noteMax'      => (int) WPCPM_Student_Questions::NOTE_MAX,
				'turns'        => $turns,
				'strings'      => array(
					'left'         => self::left_template( $turns ),
					'you'          => __( 'You', 'wpcredits-program-manager' ),
					'mentor'       => __( 'AI Mentor', 'wpcredits-program-manager' ),
					'sources'      => __( 'Sources', 'wpcredits-program-manager' ),
					// Drawn by the script, since the server's own copy of "..." would be texturized to an ellipsis.
					'working'      => __( 'The AI Mentor is thinking...', 'wpcredits-program-manager' ),
					'unsourced'    => __( 'Not from the program\'s guides', 'wpcredits-program-manager' ),
					'failed'       => __( 'That did not work. Try again in a moment.', 'wpcredits-program-manager' ),
					'previewTitle' => __( 'Send this to your mentor', 'wpcredits-program-manager' ),
					'noteLabel'    => __( 'Anything your mentor should know?', 'wpcredits-program-manager' ),
					'submit'       => __( 'Send', 'wpcredits-program-manager' ),
					'cancel'       => __( 'Cancel', 'wpcredits-program-manager' ),
					'sending'      => __( 'Sending', 'wpcredits-program-manager' ),
					'refused'      => WPCPM_Handbook_Assistant::send_sentence( 'refused' ),
				),
			)
		);
	}
}
