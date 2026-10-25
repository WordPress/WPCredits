<?php
/**
 * Institutions module - the For Administrators section of an institution's page.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the program keeps about an institution that its own people are not shown: the answers its
 * application gave that the Airtable base has no column for, and the institution's history.
 *
 * **For Administrators, and only drawn for them.** The Institution Dashboard calls `render()` for a
 * viewer who holds `WPCPM_Roles::CAP_MANAGE` and for nobody else, and `render()` asks the same
 * question again before it reads anything, so a caller that forgot to would be handed nothing
 * rather than the section. The history holds the addresses invitations went to, who did what and
 * the notes an Administrator wrote; none of it is for a member, and none of it is part of an export
 * or an answer on the REST API: this class registers no route and writes nothing.
 *
 * **Two folded cards, in the shape every card on the page has:** a `<section>` with the card class
 * around a closed `<details>`, as the Enroll students card is drawn.
 *
 * - *From the application.* The answers `WPCPM_Institution_Record::get()` keeps, each under the
 *   label the form asked it with, in the form's order (`WPCPM_Institution_Application::site_fields()`)
 *   and not the order they were stored in. A key the form does not ask is never shown, so a field
 *   name cannot reach the page. An institution approved before 1.123.29 has no record, and the card
 *   says so.
 * - *History.* The latest 100 rows of `WPCPM_Institution_Audit::entries_for()`, newest first: when,
 *   who and what happened. The audit reads by the institution's record ID alone, so a sponsor's row,
 *   which reuses kind names such as `member_added`, never appears here.
 *
 * **A row says what happened in a plain sentence.** The audit's stored message is that sentence for
 * most kinds, and `sentence()` returns it when it is one. It is not one when it is empty
 * (`student_linked` stores no message), when it is built round a slug, a record ID or a key, or when
 * it still speaks in a word the program has retired ("program manager", before the Administrators were
 * named), and then the words come from `words()`, one map keyed by kind, as
 * `WPCPM_Institution_Agreement::summary_label()` keeps the agreement states. No kind slug, record ID
 * or meta key is ever shown. A kind the map does not know yet reads as its stored message when that
 * is plain and as a sentence that names nothing otherwise, so a new kind is a line in the history
 * before it has its own words.
 *
 * **Three kinds of row are read from their facts and not from the message, so they read in today's
 * words whatever the day they were written** (Institutions release 2). A member row
 * (`member_added`, `member_removed`) names the account as it is called now ("A former member" when the
 * account is gone), then how it joined or left in the People card's own words
 * (`WPCPM_Institution_People::how_label()` and `reason_label()`): the stored message ends in the code,
 * "(provisioned)", which is not a thing to read. A closed request (`request_resolved`) is worded from the
 * state it was closed in, and for a withdrawal from the ground it was made on, so it keeps its outcome
 * when the Administrator's note cannot be shown; the note follows the outcome when it can. The raise of
 * a school's request to correct its details (`request_raised` of kind `details`) has a sentence of its
 * own. When the facts are not there, as an older row's may not be, a closed request is read as any other
 * row is, from its message, and a member row reads the words for its kind (its message names the code).
 *
 * **The stopped import is the one kind whose message is never used.** `WPCPM_Institution_Create::park()`
 * stores "The import stopped: <reason>. N students had been created." with the reason as a slug
 * (`agreement`, `no-actor` or `not-allowed`), which no test of a message can tell from a sentence, and
 * says "1 students" for one. Its row also stores the reason and the count as facts, and
 * `stopped_import()` words the row from them.
 *
 * Every function returns text or markup that escapes what it interpolates; `who()` and `sentence()`
 * return text, which `render()` escapes where it prints it.
 *
 * All static, no state, in the shape of `WPCPM_Institution_Request`.
 */
class WPCPM_Institution_History {

	/** How many rows the History card draws. One more is read, to know whether there were more. */
	const LIMIT = 100;

	/** The kind of the row a stopped import leaves, which is worded from its facts and not from its message. */
	const KIND_IMPORT_STOPPED = 'import_stopped';

	/**
	 * The For Administrators section for one institution.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return string Escaped markup, or '' for a viewer who cannot manage the program and for an ID
	 *                that is no record.
	 */
	public static function render( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return '';
		}

		$out  = '<section class="wpcpm-institution__admin" id="wpcpm-for-administrators">';
		$out .= sprintf(
			'<h2 class="wpcpm-student__heading wpcpm-institution__module-title">%s</h2>',
			esc_html__( 'For Administrators', 'wpcredits-program-manager' )
		);
		$out .= sprintf(
			'<p class="wpcpm-student__note wpcpm-institution__module-lead">%s</p>',
			esc_html__( 'Only Administrators see this section.', 'wpcredits-program-manager' )
		);
		$out .= self::card(
			'wpcpm-admin-answers',
			__( 'From the application', 'wpcredits-program-manager' ),
			self::render_answers( $record_id )
		);
		$out .= self::card(
			'wpcpm-admin-history',
			__( 'History', 'wpcredits-program-manager' ),
			self::render_history( $record_id )
		);

		return $out . '</section>';
	}

	/**
	 * What happened, in a plain sentence.
	 *
	 * @param array $entry One row of `WPCPM_Institution_Audit::entries_for()`.
	 * @return string Text, never empty and never holding a record ID, a kind slug or a meta key.
	 *                An agreement's revoke row ends with "Note: " and the note it was revoked with.
	 */
	public static function sentence( array $entry ) {
		$kind    = isset( $entry['kind'] ) ? (string) $entry['kind'] : '';
		$data    = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array();
		$raw     = isset( $entry['message'] ) && is_scalar( $entry['message'] ) ? (string) $entry['message'] : '';
		$message = self::fold( $raw );
		$words   = self::words();
		$kinds   = array_merge( array_keys( $words ), array( self::KIND_IMPORT_STOPPED ) );

		if ( self::KIND_IMPORT_STOPPED === $kind ) {
			return self::stopped_import( $data );
		}

		$read = self::from_facts( $kind, $data, isset( $entry['ground'] ) ? (string) $entry['ground'] : '', $raw, $words, $kinds );

		if ( '' !== $read ) {
			return $read;
		}

		$sentence = $message;

		if ( ! self::is_plain( $message, $kinds ) || self::is_old_wording( $message ) ) {
			$sentence = isset( $words[ $kind ] ) ? $words[ $kind ] : __( 'Something was recorded for this institution.', 'wpcredits-program-manager' );
		}

		if ( 'agreement_revoke' === $kind && isset( $data['note'] ) && is_scalar( $data['note'] ) ) {
			$note = self::fold( $data['note'] );

			if ( '' !== $note ) {
				$sentence = sprintf(
					/* translators: 1: what happened, 2: the note an Administrator revoked the agreement with. */
					__( '%1$s Note: %2$s', 'wpcredits-program-manager' ),
					$sentence,
					$note
				);
			}
		}

		return $sentence;
	}

	/**
	 * Who did it.
	 *
	 * @param array $entry One row of `WPCPM_Institution_Audit::entries_for()`.
	 * @return string The account's display name (its login when it has none), "The site" for the
	 *                system, which acts as user 0, and "A removed account" for a user who is gone.
	 */
	public static function who( array $entry ) {
		$actor = isset( $entry['actor'] ) ? (int) $entry['actor'] : 0;

		if ( $actor <= 0 ) {
			return __( 'The site', 'wpcredits-program-manager' );
		}

		$name = self::account_name( $actor );

		return '' !== $name ? $name : __( 'A removed account', 'wpcredits-program-manager' );
	}

	/**
	 * What an account is called now.
	 *
	 * @param int $user_id The account.
	 * @return string The display name, the login when it has none, and '' for an account that is gone.
	 */
	private static function account_name( $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user instanceof WP_User ) {
			return '';
		}

		$name = trim( (string) $user->display_name );

		return '' !== $name ? $name : trim( (string) $user->user_login );
	}

	/**
	 * One folded card: a section with the card class around a closed details, as the dashboard's
	 * other cards are drawn.
	 *
	 * @param string $slug  The card's class and anchor.
	 * @param string $title The summary's title.
	 * @param string $body  The card's body, already escaped.
	 * @return string
	 */
	private static function card( $slug, $title, $body ) {
		return sprintf(
			'<section class="wpcpm-institution__card %1$s" id="%1$s"><details class="wpcpm-group wpcpm-group__disclosure %1$s__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%2$s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body %1$s__body">%3$s</div></details></section>',
			esc_attr( $slug ),
			esc_html( $title ),
			$body
		);
	}

	/**
	 * The kept answers, under the form's labels.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return string Escaped markup.
	 */
	private static function render_answers( $record_id ) {
		$kept = WPCPM_Institution_Record::get( $record_id );
		$rows = '';

		foreach ( WPCPM_Institution_Application::site_fields() as $key => $spec ) {
			if ( ! isset( $kept[ $key ] ) ) {
				continue;
			}

			$value = is_array( $kept[ $key ] ) ? implode( ', ', array_filter( array_map( 'trim', array_map( 'strval', $kept[ $key ] ) ), 'strlen' ) ) : trim( (string) $kept[ $key ] );

			if ( '' === $value ) {
				continue;
			}

			$rows .= sprintf(
				'<dt>%1$s</dt><dd>%2$s</dd>',
				esc_html( isset( $spec['label'] ) ? (string) $spec['label'] : '' ),
				esc_html( $value )
			);
		}

		if ( '' === $rows ) {
			return sprintf(
				'<p class="wpcpm-institution__empty">%s</p>',
				esc_html__( 'Nothing kept from the application.', 'wpcredits-program-manager' )
			);
		}

		return '<dl class="wpcpm-admin-answers__list">' . $rows . '</dl>';
	}

	/**
	 * The latest rows of the audit, newest first.
	 *
	 * @param string $record_id The institution's record ID.
	 * @return string Escaped markup.
	 */
	private static function render_history( $record_id ) {
		$entries = WPCPM_Institution_Audit::entries_for( $record_id, self::LIMIT + 1 );
		$more    = count( $entries ) > self::LIMIT;

		if ( array() === $entries ) {
			return sprintf(
				'<p class="wpcpm-institution__empty">%s</p>',
				esc_html__( 'Nothing has been recorded for this institution yet.', 'wpcredits-program-manager' )
			);
		}

		$out = '<ol class="wpcpm-admin-history__list">';

		foreach ( array_slice( $entries, 0, self::LIMIT ) as $entry ) {
			$time = isset( $entry['time'] ) ? (int) $entry['time'] : 0;

			$out .= sprintf(
				'<li class="wpcpm-admin-history__row"><span class="wpcpm-admin-history__meta"><time datetime="%1$s">%2$s</time> &middot; <span class="wpcpm-admin-history__who">%3$s</span></span><span class="wpcpm-admin-history__what">%4$s</span></li>',
				esc_attr( $time > 0 ? gmdate( 'c', $time ) : '' ),
				esc_html( WPCPM_Dates::moment( $time ) ),
				esc_html( self::who( $entry ) ),
				esc_html( self::sentence( $entry ) )
			);
		}

		$out .= '</ol>';

		if ( $more ) {
			$out .= sprintf(
				'<p class="wpcpm-institution__empty wpcpm-admin-history__limit">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: how many of the latest rows are shown, e.g. 100. */
						__( 'Showing the latest %s.', 'wpcredits-program-manager' ),
						number_format_i18n( self::LIMIT )
					)
				)
			);
		}

		return $out;
	}

	/**
	 * The sentence for a row that is read from its facts and not from its message, or '' for any other.
	 *
	 * The kinds are the literals the writers store, as `words()` keys them: the suite reads the writers'
	 * constants, so a kind renamed there fails it.
	 *
	 * @param string   $kind   The row's kind.
	 * @param array    $data   The row's facts.
	 * @param string   $ground The ground the row was written under.
	 * @param string   $raw    The row's stored message, its lines kept.
	 * @param string[] $words  `words()`.
	 * @param string[] $kinds  The kinds a plain message may not hold.
	 * @return string
	 */
	private static function from_facts( $kind, array $data, $ground, $raw, array $words, array $kinds ) {
		switch ( $kind ) {
			case 'member_added':
			case 'member_removed':
				return self::member_sentence( 'member_added' === $kind, $data, $words[ $kind ] );

			case 'request_raised':
				return isset( $data['kind'] ) && WPCPM_Institution_Request::KIND_DETAILS === $data['kind']
					? __( 'The institution asked to correct its details.', 'wpcredits-program-manager' )
					: '';

			case 'request_resolved':
				return self::resolved_sentence( $data, $ground, $raw, $kinds );
		}

		return '';
	}

	/**
	 * The sentence for a member row: the account's name now, then how it joined or left in the People
	 * card's words.
	 *
	 * The row stores the name as it was and the way as a code in brackets ("(provisioned)"), neither of
	 * which is to be read: the name is looked up again, "A former member" when the account is gone, and
	 * the way is `WPCPM_Institution_People::how_label()` or `reason_label()`, which say a way or a reason
	 * they do not know is unrecorded and never print it. A row that names no account, as an older one may
	 * not, reads the words for its kind, whatever its message says.
	 *
	 * @param bool   $added    Whether the row is an account joining, and not leaving.
	 * @param array  $data     The row's facts: `user` and `how`, or `user` and `reason`.
	 * @param string $fallback The words for the kind.
	 * @return string
	 */
	private static function member_sentence( $added, array $data, $fallback ) {
		$user = isset( $data['user'] ) && is_numeric( $data['user'] ) ? (int) $data['user'] : 0;

		if ( $user <= 0 ) {
			return $fallback;
		}

		$name = self::account_name( $user );
		$name = '' !== $name ? $name : __( 'A former member', 'wpcredits-program-manager' );

		if ( $added ) {
			return sprintf(
				/* translators: 1: the account's name, 2: how it came to be a member, in a short phrase such as "Joined by invitation". */
				__( '%1$s was added as a member. %2$s.', 'wpcredits-program-manager' ),
				$name,
				WPCPM_Institution_People::how_label( isset( $data['how'] ) && is_string( $data['how'] ) ? $data['how'] : '' )
			);
		}

		return sprintf(
			/* translators: 1: the account's name, 2: why it is no longer one, in a short phrase such as "They removed their own access". */
			__( '%1$s is no longer a member. %2$s.', 'wpcredits-program-manager' ),
			$name,
			WPCPM_Institution_People::reason_label( isset( $data['reason'] ) && is_string( $data['reason'] ) ? $data['reason'] : '' )
		);
	}

	/**
	 * The sentence for a closed request, from the state it was closed in.
	 *
	 * The outcome is the state's, and for a withdrawal the ground's: the school's own act, or an
	 * Administrator's on its behalf. The stored message's first line is the closing sentence of the day
	 * it was written, in that day's words, and is not read; what follows it is the Administrator's note,
	 * which follows the outcome on the same line when it is a sentence to show
	 * (`is_plain()`), and is left out when it is not, so the outcome is kept either way.
	 *
	 * @param array    $data   The row's facts: `request`, `kind` and `state`.
	 * @param string   $ground The ground the row was written under.
	 * @param string   $raw    The row's stored message, its lines kept.
	 * @param string[] $kinds  The kinds a plain note may not hold.
	 * @return string The sentence, or '' for a row that holds no state this knows.
	 */
	private static function resolved_sentence( array $data, $ground, $raw, array $kinds ) {
		$state = isset( $data['state'] ) && is_string( $data['state'] ) ? $data['state'] : '';

		switch ( $state ) {
			case WPCPM_Institution_Request::STATE_DONE:
				$sentence = isset( $data['kind'] ) && WPCPM_Institution_Request::KIND_DETAILS === $data['kind']
					? __( 'An Administrator approved a change to the institution\'s details.', 'wpcredits-program-manager' )
					: __( 'An Administrator closed a request as handled.', 'wpcredits-program-manager' );
				break;

			case WPCPM_Institution_Request::STATE_DECLINED:
				$sentence = __( 'An Administrator declined a request.', 'wpcredits-program-manager' );
				break;

			case WPCPM_Institution_Request::STATE_WITHDRAWN:
				$sentence = WPCPM_Institution_Audit::GROUND_MANAGER === $ground
					? __( 'An Administrator withdrew a request.', 'wpcredits-program-manager' )
					: __( 'The institution withdrew a request.', 'wpcredits-program-manager' );
				break;

			default:
				return '';
		}

		$lines = preg_split( '/\R/', trim( $raw ), 2 );
		$note  = isset( $lines[1] ) ? self::fold( $lines[1] ) : '';

		if ( '' === $note || ! self::is_plain( $note, $kinds ) ) {
			return $sentence;
		}

		return sprintf(
			/* translators: 1: what became of the request, as a sentence, 2: the note an Administrator closed it with. */
			__( '%1$s %2$s', 'wpcredits-program-manager' ),
			$sentence,
			$note
		);
	}

	/**
	 * The sentence for a row a stopped import left, from the facts the row stores.
	 *
	 * The reasons are the three `WPCPM_Institution_Create::park()` is handed, as it stores them: the
	 * agreement is no longer in force, the batch no longer names the account that confirmed it, and
	 * that account may no longer add students. A reason this does not know, or none, reads as the
	 * plain stem, so a fourth reason is a line in the history before it has words. A count that is
	 * missing or is no number reads 0.
	 *
	 * @param array $data The row's facts: `reason` and `created`.
	 * @return string
	 */
	private static function stopped_import( array $data ) {
		$reason  = isset( $data['reason'] ) && is_string( $data['reason'] ) ? $data['reason'] : '';
		$created = isset( $data['created'] ) && is_numeric( $data['created'] ) ? max( 0, (int) $data['created'] ) : 0;
		$count   = number_format_i18n( $created );

		switch ( $reason ) {
			case 'agreement':
				return sprintf(
					/* translators: %s: how many students were created before the import stopped. */
					_n( 'The import stopped because the institution\'s agreement is no longer in force. %s student had been created.', 'The import stopped because the institution\'s agreement is no longer in force. %s students had been created.', $created, 'wpcredits-program-manager' ),
					$count
				);

			case 'no-actor':
				return sprintf(
					/* translators: %s: how many students were created before the import stopped. */
					_n( 'The import stopped because the batch no longer names the account that confirmed it. %s student had been created.', 'The import stopped because the batch no longer names the account that confirmed it. %s students had been created.', $created, 'wpcredits-program-manager' ),
					$count
				);

			case 'not-allowed':
				return sprintf(
					/* translators: %s: how many students were created before the import stopped. */
					_n( 'The import stopped because the account that confirmed it may no longer add students. %s student had been created.', 'The import stopped because the account that confirmed it may no longer add students. %s students had been created.', $created, 'wpcredits-program-manager' ),
					$count
				);
		}

		return sprintf(
			/* translators: %s: how many students were created before the import stopped. */
			_n( 'The import stopped. %s student had been created.', 'The import stopped. %s students had been created.', $created, 'wpcredits-program-manager' ),
			$count
		);
	}

	/**
	 * Text on one line: runs of white space, line breaks among them, become one space.
	 *
	 * A closed request keeps the Administrator's words under its sentence on a line of their own,
	 * and a row is one line of a list.
	 *
	 * @param mixed $text A stored message or note.
	 * @return string
	 */
	private static function fold( $text ) {
		return trim( (string) preg_replace( '/\s+/', ' ', is_scalar( $text ) ? (string) $text : '' ) );
	}

	/**
	 * Whether a stored message is a sentence an Administrator can read as it is.
	 *
	 * It is not when it is empty, or when it holds anything the history must never show: a record
	 * ID (`rec` and fourteen letters or digits), a kind slug, or the shape of a meta key or a slug,
	 * which is an underscore inside a word, a pipe, or the plugin's own prefix. An address may carry
	 * an underscore, so words with an at sign are left out of that one test.
	 *
	 * @param string   $message A stored message, folded to one line.
	 * @param string[] $kinds   The kind slugs the plugin writes: the keys of `words()` and `import_stopped`,
	 *                          whose row is worded from its facts and has no entry there.
	 * @return bool
	 */
	private static function is_plain( $message, array $kinds ) {
		if ( '' === $message ) {
			return false;
		}

		if ( 1 === preg_match( '/rec[A-Za-z0-9]{14}/', $message ) || false !== stripos( $message, 'wpcpm' ) || false !== strpos( $message, '|' ) ) {
			return false;
		}

		if ( 1 === preg_match( '/[A-Za-z0-9]_[A-Za-z0-9]|(?:^|\s)_/', (string) preg_replace( '/\S+@\S+/', '', $message ) ) ) {
			return false;
		}

		$slugs = array_map(
			static function ( $kind ) {
				return preg_quote( $kind, '/' );
			},
			$kinds
		);

		return 1 !== preg_match( '/(?<![A-Za-z0-9_-])(?:' . implode( '|', $slugs ) . ')(?![A-Za-z0-9_-])/i', $message );
	}

	/**
	 * Whether a stored message still speaks in a word the program has retired.
	 *
	 * Rows written before the program's people were named Administrators say "program manager", in any
	 * case and number. The words for the kind are read in its place; the Administrator's own words in a
	 * note are never judged by this.
	 *
	 * @param string $message A stored message, folded to one line.
	 * @return bool
	 */
	private static function is_old_wording( $message ) {
		return false !== stripos( $message, 'program manager' );
	}

	/**
	 * The words for each kind of row an institution has, for a row whose stored message is not a
	 * sentence to show.
	 *
	 * One map, keyed by the kind each writer stores. It holds thirty of the plugin's thirty-one
	 * kinds, written in these places: members (`WPCPM_Institution_Audit::KIND_MEMBER_ADDED`,
	 * `KIND_MEMBER_REMOVED`), an approved application (`WPCPM_Institution_Approval::LOG_APPROVED`),
	 * the agreement (`WPCPM_Institution_Agreement::LOG_*` and
	 * `WPCPM_Agreement_Generate::LOG_GENERATED`), the roster's fence
	 * (`WPCPM_Institution_Roster::LOG_*`), imports (`WPCPM_Institution_Create::LOG_*`),
	 * invitations (`WPCPM_Institution_Invite::LOG_*`), requests (`WPCPM_Institution_Request::LOG_*`),
	 * students (`WPCPM_Institution_Student_Form::KIND_*`, `WPCPM_Institution_Students::KIND_*`,
	 * `WPCPM_Institutions::LOG_LINKED` and `WPCPM_Institution_Resend::KIND`) and a school's corrected
	 * details (`WPCPM_Institution_Details::LOG_SAVED`). The suite reads the constants from those files, and
	 * every kind passed to `WPCPM_Institution_Audit::record(` in includes/, so a kind renamed there, or
	 * written without words here, fails it.
	 *
	 * Two of the thirty, `note_added` and `note_deleted`, are no longer written by anything: they
	 * were the institution's notes, retired in Institutions release 2. Their words stay, because a row an
	 * older site holds is still read in the history, and the suite lists them apart from the declared kinds.
	 *
	 * The thirty-first kind, `import_stopped`, is not in this map, which is why it holds thirty:
	 * its row is worded from its facts (`stopped_import()`), and its slug is added to the kinds a
	 * plain message may not hold.
	 *
	 * These words are what a row reads when its stored message is no sentence to show. Three kinds are
	 * read from their facts first (see the class), and these are what they fall back to.
	 *
	 * Written so that none of these sentences holds a kind slug as a word: "extended" is not the
	 * kind `extend`.
	 *
	 * @return array<string, string> Kind => sentence.
	 */
	private static function words() {
		return array(
			'member_added'          => __( 'A member was added to this institution.', 'wpcredits-program-manager' ),
			'member_removed'        => __( 'A member was removed from this institution.', 'wpcredits-program-manager' ),
			'application_approved'  => __( 'The institution\'s application was approved.', 'wpcredits-program-manager' ),
			'agreement_generated'   => __( 'The Collaboration Agreement template was generated for signature.', 'wpcredits-program-manager' ),
			'agreement_on_file'     => __( 'A Collaboration Agreement the program already held was recorded as on file.', 'wpcredits-program-manager' ),
			'agreement_upload'      => __( 'A signed Collaboration Agreement was uploaded.', 'wpcredits-program-manager' ),
			'agreement_accept'      => __( 'A signed Collaboration Agreement was accepted.', 'wpcredits-program-manager' ),
			'agreement_return'      => __( 'A signed Collaboration Agreement was returned to the institution with a note.', 'wpcredits-program-manager' ),
			'agreement_withdraw'    => __( 'A submitted Collaboration Agreement was withdrawn.', 'wpcredits-program-manager' ),
			'agreement_revoke'      => __( 'A Collaboration Agreement was revoked, and the institution\'s account closed.', 'wpcredits-program-manager' ),
			'agreement_reinstate'   => __( 'A revoked Collaboration Agreement was reinstated.', 'wpcredits-program-manager' ),
			'claim_refused'         => __( 'A change to a student record was refused because a live read placed that student with another institution.', 'wpcredits-program-manager' ),
			'claim_locked'          => __( 'An account was refused too many changes in one day and cannot change the roster again until tomorrow.', 'wpcredits-program-manager' ),
			'import_created'        => __( 'An import of students finished.', 'wpcredits-program-manager' ),
			'invite_sent'           => __( 'An invitation to act for this institution was sent.', 'wpcredits-program-manager' ),
			'invite_resent'         => __( 'An invitation to act for this institution was sent again.', 'wpcredits-program-manager' ),
			'invite_accepted'       => __( 'An invitation to act for this institution was accepted.', 'wpcredits-program-manager' ),
			'invite_cancelled'      => __( 'An invitation to act for this institution was canceled.', 'wpcredits-program-manager' ),
			'invite_expired'        => __( 'An invitation to act for this institution expired without being accepted.', 'wpcredits-program-manager' ),
			'note_added'            => __( 'A note about a student was added.', 'wpcredits-program-manager' ),
			'note_deleted'          => __( 'A note about a student was deleted.', 'wpcredits-program-manager' ),
			'request_raised'        => __( 'The institution raised a request.', 'wpcredits-program-manager' ),
			'request_resolved'      => __( 'An Administrator closed a request.', 'wpcredits-program-manager' ),
			'student_edited'        => __( 'A student\'s details were edited.', 'wpcredits-program-manager' ),
			'extend'                => __( 'A student\'s end date was extended.', 'wpcredits-program-manager' ),
			'student_graduated'     => __( 'A student was marked as graduated.', 'wpcredits-program-manager' ),
			'student_withdrawn'     => __( 'A student was marked as withdrawn.', 'wpcredits-program-manager' ),
			'student_linked'        => __( 'A student was linked to this institution.', 'wpcredits-program-manager' ),
			'student-invite-resent' => __( 'A welcome invitation was sent to a student.', 'wpcredits-program-manager' ),
			'details_saved'         => __( 'The institution\'s details were corrected.', 'wpcredits-program-manager' ),
		);
	}
}
