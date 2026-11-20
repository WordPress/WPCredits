<?php
/**
 * The AI Mentor transcripts: the Administrator Dashboard's view of what the AI Mentor said to each account (AI Mentor R3).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the accounts that talked with the AI Mentor in the last 90 days and, one at a time, draws an account's transcript.
 *
 * **The view is the dashboard page itself**, at `?wpcpm_view=ai-mentor`, gated as the page is (its access level and
 * `CAP_MANAGE`, checked by `WPCPM_Administrators_Dashboard::render()` before this class is asked for anything); the
 * renderer checks the capability again, since it writes. The Student Report Card's manager line links here with
 * `wpcpm_tutor_record`, the student's record ID.
 *
 * **Reading a transcript is recorded**: each time one is drawn for a valid record ID that has rows, the audit log gets a
 * row holding the record ID and the reader and nothing of the conversation, and the last five such rows are shown as
 * "Read by". The list writes nothing.
 */
final class WPCPM_Tutor_Transcripts_View {

	/** The query argument the view is asked for by, and its one value. */
	const ARG  = 'wpcpm_view';
	const VIEW = 'ai-mentor';

	/** The query argument that picks one account's transcript: the student's record ID. */
	const RECORD_ARG = 'wpcpm_tutor_record';

	/** The stylesheet handle. */
	const STYLE = 'wpcpm-tutor-transcripts';

	/** How many readers "Read by" lists. */
	const READERS = 5;

	/**
	 * Register (but do not enqueue) the stylesheet, over the Administrator Dashboard's.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/tutor-transcripts.css',
				array( WPCPM_Administrators_Dashboard::STYLE ),
				WPCPM_VERSION
			);
		}
	}

	/**
	 * Whether this request asks the dashboard page for this view.
	 *
	 * @return bool
	 */
	public static function is_requested() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read, which changes nothing.
		return isset( $_GET[ self::ARG ] ) && self::VIEW === sanitize_key( wp_unslash( $_GET[ self::ARG ] ) );
	}

	/**
	 * The view's address, or one account's transcript's, or '' while the dashboard page has none.
	 *
	 * @param string $record A student's record ID for that account's transcript, or '' for the list.
	 * @return string
	 */
	public static function url( $record = '' ) {
		$page = WPCPM_Administrators_Dashboard::page_url();

		if ( '' === $page ) {
			return '';
		}

		$url = add_query_arg( self::ARG, self::VIEW, $page );

		return '' === (string) $record ? $url : add_query_arg( self::RECORD_ARG, (string) $record, $url );
	}

	/**
	 * Whether the menu shows the view: while the setting lists a login or the store keeps a conversation.
	 *
	 * @return bool
	 */
	public static function shown() {
		if ( class_exists( 'WPCPM_Tutor' ) && array() !== WPCPM_Tutor::accounts() ) {
			return true;
		}

		return class_exists( 'WPCPM_Tutor_Store' ) && array() !== WPCPM_Tutor_Store::accounts();
	}

	/**
	 * Draw the view: one account's transcript when the request names a record with rows, else the list.
	 *
	 * @param string $eyebrow The page's name, as markup the caller escaped (`WPCPM_Administrators_Dashboard::page_name()`).
	 */
	public static function render_view( $eyebrow = '' ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view to read; what it records is the opening itself.
		$record = isset( $_GET[ self::RECORD_ARG ] ) ? trim( sanitize_text_field( wp_unslash( $_GET[ self::RECORD_ARG ] ) ) ) : '';
		$rows   = WPCPM_Mentors_Sync::is_record_id( $record ) ? WPCPM_Tutor_Store::by_record( $record ) : array();

		echo '<div class="wpcpm-tutor-transcripts-view">';

		if ( array() === $rows ) {
			self::render_list( $eyebrow );
		} else {
			// The opening is recorded before the transcript is drawn, and a transcript whose opening could not be recorded
			// is not drawn: the list is, with a notice.
			$logged = WPCPM_Institution_Audit::record_tutor_read( $record, get_current_user_id() );

			if ( is_wp_error( $logged ) || ! is_int( $logged ) || $logged < 1 ) {
				self::render_list( $eyebrow, __( 'This transcript could not be opened just now. Try again in a moment.', 'wpcredits-program-manager' ) );
			} else {
				self::render_transcript( $eyebrow, $record, $rows );
			}
		}

		echo '</div>';
	}

	/**
	 * The head of a view: the page's name, the title and the line under it.
	 *
	 * @param string $eyebrow The page's name, escaped by its builder.
	 * @param string $title   The title.
	 * @param string $facts   The line under it as markup the caller escaped, or ''.
	 */
	private static function render_head( $eyebrow, $title, $facts ) {
		echo '<header class="wpcpm-head wpcpm-timeline-view__header"><div class="wpcpm-head__copy">';
		echo $eyebrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it is built, WPCPM_Administrators_Dashboard::page_name().
		printf( '<h2 class="wpcpm-head__title">%s</h2>', esc_html( $title ) );

		if ( '' !== $facts ) {
			printf( '<p class="wpcpm-head__facts">%s</p>', $facts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
		}

		echo '</div></header>';
	}

	/**
	 * The accounts with turns in the last 90 days, newest first.
	 *
	 * @param string $eyebrow The page's name, escaped by its builder.
	 * @param string $notice  A notice to show above the list, or ''.
	 */
	private static function render_list( $eyebrow, $notice = '' ) {
		self::render_head( $eyebrow, __( 'AI Mentor transcripts', 'wpcredits-program-manager' ), '' );

		if ( '' !== $notice ) {
			echo WPCPM_UI::intro( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
		}

		$accounts = WPCPM_Tutor_Store::accounts();

		if ( array() === $accounts ) {
			echo WPCPM_UI::intro( __( 'No AI Mentor conversations in the last 90 days.', 'wpcredits-program-manager' ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

			return;
		}

		$record   = array(
			'key'    => 'record',
			'label'  => __( 'Record', 'wpcredits-program-manager' ),
			'kind'   => 'text',
			'header' => 'row',
		);
		$messages = array(
			'key'   => 'messages',
			'label' => __( 'Messages', 'wpcredits-program-manager' ),
			'kind'  => 'num',
		);
		$last     = array(
			'key'   => 'last',
			'label' => __( 'Last message', 'wpcredits-program-manager' ),
			'kind'  => 'date',
		);
		$out      = WPCPM_UI::table_open(
			array( $record, $messages, $last ),
			array( 'caption' => __( 'AI Mentor transcripts', 'wpcredits-program-manager' ) )
		);

		foreach ( $accounts as $account ) {
			$id   = (string) $account['student_record'];
			$name = esc_html( $id );
			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $record, WPCPM_Mentors_Sync::is_record_id( $id ) ? '<a href="' . esc_url( self::url( $id ) ) . '">' . $name . '</a>' : $name )
				. WPCPM_UI::cell( $messages, esc_html( number_format_i18n( (int) $account['turns'] ) ) )
				. WPCPM_UI::cell( $last, esc_html( self::when( (string) $account['last'] ) ) )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above, the link through esc_url().
	}

	/**
	 * One account's transcript, then who read it.
	 *
	 * @param string $eyebrow The page's name, escaped by its builder.
	 * @param string $record  The student's record ID.
	 * @param array  $rows    What `WPCPM_Tutor_Store::by_record()` returned.
	 */
	private static function render_transcript( $eyebrow, $record, array $rows ) {
		self::render_head(
			$eyebrow,
			sprintf(
				/* translators: %s: a student's record ID. */
				__( 'Transcript of %s', 'wpcredits-program-manager' ),
				$record
			),
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'All AI Mentor transcripts', 'wpcredits-program-manager' ) . '</a>'
		);

		$when    = array(
			'key'    => 'when',
			'label'  => __( 'Time', 'wpcredits-program-manager' ),
			'kind'   => 'date',
			'header' => 'row',
		);
		$who     = array(
			'key'   => 'who',
			'label' => __( 'From', 'wpcredits-program-manager' ),
			'kind'  => 'short',
		);
		$message = array(
			'key'   => 'message',
			'label' => __( 'Message', 'wpcredits-program-manager' ),
			'kind'  => 'text',
		);
		$out     = WPCPM_UI::table_open(
			array( $when, $who, $message ),
			array( 'caption' => __( 'Transcript', 'wpcredits-program-manager' ) )
		);

		foreach ( $rows as $row ) {
			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $when, esc_html( self::when( (string) $row['created_gmt'] ) ) )
				. WPCPM_UI::cell( $who, esc_html( self::role_label( (string) $row['role'] ) ) )
				. WPCPM_UI::cell( $message, self::message_html( $row ) )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each cell through esc_html() or message_html().

		echo WPCPM_UI::heading( __( 'Read by', 'wpcredits-program-manager' ), 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().

		$lines = array();

		foreach ( WPCPM_Institution_Audit::tutor_reads( $record, self::READERS ) as $read ) {
			$user    = get_userdata( (int) $read['actor'] );
			$lines[] = sprintf(
				/* translators: 1: an Administrator's name, 2: a date and time. */
				__( '%1$s, %2$s', 'wpcredits-program-manager' ),
				$user ? (string) $user->display_name : __( 'A former Administrator', 'wpcredits-program-manager' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $read['time'] )
			);
		}

		echo WPCPM_UI::checklist( $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::checklist().
	}

	/**
	 * What a row says, as markup: a student's or the AI Mentor's text with its line breaks (and the sources of a reply,
	 * numbered as its markers are), a tool call's name over its arguments and result as indented JSON.
	 *
	 * @param array $row One row of the store.
	 * @return string Escaped markup.
	 */
	private static function message_html( array $row ) {
		if ( WPCPM_Tutor_Store::ROLE_TOOL === $row['role'] ) {
			$decoded = json_decode( (string) $row['body'], true );
			$json    = is_array( $decoded ) ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $row['body'];

			return '<strong>' . esc_html( (string) $row['tool'] ) . '</strong><pre>' . esc_html( (string) $json ) . '</pre>';
		}

		// A pre, which core's `wptexturize()` skips, so a `--` or a `...` is shown as it was typed.
		$html  = '<pre class="wpcpm-tutor__text">' . esc_html( (string) $row['body'] ) . '</pre>';
		$list  = '';
		$shown = 0;

		// Source i is the reply's marker [i]: one left out keeps the next at its own number.
		foreach ( array_values( (array) $row['sources'] ) as $index => $source ) {
			if ( ! is_array( $source ) || empty( $source['link'] ) || ! preg_match( '#^https?://#i', (string) $source['link'] ) ) {
				continue;
			}

			$number = $index + 1;
			$title  = ! empty( $source['title'] ) ? (string) $source['title'] : (string) $source['link'];
			$list  .= '<li' . ( ++$shown === $number ? '' : ' value="' . (int) $number . '"' ) . '><a href="' . esc_url( (string) $source['link'], array( 'http', 'https' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>' . ( ! empty( $source['extract'] ) && is_string( $source['extract'] ) ? '<pre class="wpcpm-tutor__extract">' . esc_html( trim( $source['extract'] ) ) . '</pre>' : '' ) . '</li>';
		}

		return '' === $list ? $html : $html . '<ol>' . $list . '</ol>';
	}

	/**
	 * A role's label.
	 *
	 * @param string $role `student`, `mentor_ai` or `tool`.
	 * @return string
	 */
	private static function role_label( $role ) {
		if ( WPCPM_Tutor_Store::ROLE_MENTOR === $role ) {
			return __( 'AI Mentor', 'wpcredits-program-manager' );
		}

		return WPCPM_Tutor_Store::ROLE_TOOL === $role ? __( 'Tool call', 'wpcredits-program-manager' ) : __( 'Student', 'wpcredits-program-manager' );
	}

	/**
	 * A stored UTC time in the site's time zone and formats.
	 *
	 * @param string $gmt `Y-m-d H:i:s`, in UTC.
	 * @return string
	 */
	private static function when( $gmt ) {
		$time = strtotime( $gmt . ' UTC' );

		return false === $time ? '' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
	}
}
