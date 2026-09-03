<?php
/**
 * Institutions module - graduate and withdraw, and the three guards on that one write.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The two status changes a school may make for itself, and what stops every other one.
 *
 * This is the module's one write that nobody can take back. Every other institution-side write
 * corrects a cell somebody can correct again; this one moves `Status` on the Students table, and
 * the base answers a status change with mail. `Certificate notice after graduation` and
 * `Dropped out notice` are Airtable automations that reach the student directly, and the audit
 * log cannot see them: a row here saying the site wrote a value is not evidence of what the
 * student received. So this class is three guards and a single `update_records()` call.
 *
 * **Guard 1: two states, and the two are the whole list.** `states()` offers `Graduate` and
 * `Dropped out`. `Paused` and `Pending graduation` are tracked statuses since decision 21, so the
 * first design's reason for leaving them out - a student moved to either lost their role at the
 * next sync - no longer holds. They stay out because pausing a student and holding them at
 * pending graduation are the program's calls about its own process, not a school's. The handler
 * matches the posted state against `states()` before anything reaches the network, so a
 * hand-written POST naming a third status spends no request and writes nothing.
 *
 * **Guard 2: the row is read live and has to agree, in `WPCPM_Mentor_Checker_Runner::promote()`'s
 * shape.** `claim()` returns the Students row as the base holds it now, `check()` reads its
 * `Status` and answers one of four ways: already the target, leave it alone; `Paused`, refuse and
 * say why; not a tracked active status, refuse and name what the row says; otherwise write. The
 * re-read is the point. The roster index a school presses the button from is the Students table
 * as the last sync read it, and between that sync and the press a program manager, a form
 * submission or one of the base's forty automations may have moved the row.
 *
 * **Why `Paused` refuses by name.** The automation that carries a Students status through to the
 * Students Reports row, `wflUYImI8OEvVuc4R`, is restricted to view `viwzSJspvACLnhXom`, and
 * `Paused` is not in that view. A Paused row marked `Graduate` would therefore fire the
 * certificate mail - that automation watches the Students row itself - while the reports row, the
 * student's account and their mentor's list all went on saying Paused. The four Universidad
 * Fidelitas rows reading `Paused` on Students and `Not moving forward` on Students Reports are
 * what that gap already produces without anybody pressing anything. The refusal comes out, with
 * its test, on the day the base owner adds `Paused` to the view (open question 4).
 *
 * **Guard 3: the confirm names the mail.** `confirm()` names the student, says Airtable will
 * email them and that the mail cannot be recalled, and says the change is logged under the
 * presser's name. There is no un-graduate on a school's screen: a program manager changes it in
 * Airtable, and both the card and every refusal say so out loud rather than leaving a school
 * hunting for a control that was never built.
 *
 * **The Students table and nothing else.** One `update_records()` call, one cell. The reports row
 * is not written here even though it carries a `Status` of its own: one authority per field is
 * the rule the whole module is built on, decision 19 makes the Students table the institution
 * side's authority, and a site that wrote both would be racing the automation for the second one.
 */
final class WPCPM_Institution_Students {

	/** The one change. Nonce keyed to the Students record being moved. */
	const ACTION_CHANGE = 'wpcpm_change_student_status';

	/** Flash channel. Carries the record it is about, so one card prints it and not every card. */
	const FLASH = 'institution_student_status';

	/** The card's anchor, so a press lands back on the card it came from. */
	const ANCHOR = 'wpcpm-student-status';

	/** The posted field naming which of `states()` was pressed. Nothing else in the form is read. */
	const FIELD_STATE = 'state';

	/** The state key for `Graduate`. */
	const STATE_GRADUATED = 'graduated';

	/** The state key for `Dropped out`. */
	const STATE_WITHDRAWN = 'withdrawn';

	/**
	 * The `WPCPM_Mentors_Sync::fields()` key holding the Students table's status column.
	 *
	 * A key rather than the string, for the reason `WPCPM_Institution_Roster::KEY_WITHHELD` is
	 * one: the base's spelling of a column is settled in a single file, and a second copy of it
	 * here is a bug waiting for the day somebody renames the column in only one of them.
	 */
	const KEY_STATUS = 'student_status';

	/**
	 * The status this refuses by name, and the view that is the reason.
	 *
	 * Written out as a literal because it is the base's own value and not a setting: a site that
	 * renamed the choice would be a different base, and the automation named below would have to
	 * be re-read anyway.
	 */
	const PAUSED = 'Paused';

	/** The Airtable view the mirror automation is restricted to. Named in the refusal on purpose. */
	const MIRROR_VIEW = 'viwzSJspvACLnhXom';

	/** The one cell this class writes, in the vocabulary `WPCPM_Institution_Policy::scope()` narrows. */
	const SLOT = 'students|Status';

	/** Outcome: the row already carries the target status, so nothing was written. */
	const OUT_ALREADY = 'status-already';

	/** Outcome: the row says `Paused`, which the mirror automation's view does not hold. */
	const OUT_PAUSED = 'status-paused';

	/** Outcome: the row is not on a tracked active status, so its status is the program's to set. */
	const OUT_UNTRACKED = 'status-untracked';

	/** Audit kind: a school recorded one of its students as graduated. */
	const KIND_GRADUATED = 'student_graduated';

	/** Audit kind: a school recorded one of its students as dropped out. */
	const KIND_WITHDRAWN = 'student_withdrawn';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_CHANGE, array( __CLASS__, 'handle_change' ) );
	}

	/*
	 * Guard 1: the list
	 * --------------------------------------------------------------------
	 */

	/**
	 * The states a school may move one of its own students to, and what each writes.
	 *
	 * **This map is the list, and the list is two.** The renderer draws a control per entry and
	 * the handler matches the posted value against the keys, so nothing outside it has a button
	 * to press or a branch to reach. Adding a third entry is a one-line diff here and a failing
	 * assertion in `bin/test-institution-graduate.php` until somebody has decided it belongs.
	 *
	 * Deliberately absent, and the reason is not a technical one:
	 *
	 * - `Paused`. Pausing a student is the program deciding to hold its own process for a
	 *   while, usually for something the school is not party to.
	 * - `Pending graduation`. It means the program has not finished checking the work yet, so a
	 *   school setting it would be a school marking its own homework as marked.
	 *
	 * Both are tracked statuses today (decision 21), which is what makes it worth saying that
	 * their absence is a decision rather than an oversight left over from the first design.
	 *
	 * @return array<string, string> State key to the value written to the Students table.
	 */
	public static function states() {
		return array(
			self::STATE_GRADUATED => 'Graduate',
			self::STATE_WITHDRAWN => 'Dropped out',
		);
	}

	/**
	 * What each control is called on the card.
	 *
	 * Kept apart from `states()` so that map stays exactly the two values written to Airtable and
	 * nothing else: a label added to it would be one more thing to read past when checking what a
	 * school may write.
	 *
	 * @return array<string, string> State key to its visible label.
	 */
	public static function labels() {
		return array(
			self::STATE_GRADUATED => __( 'Mark as graduated', 'wpcredits-program-manager' ),
			self::STATE_WITHDRAWN => __( 'Mark as dropped out', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The audit kind each state files its row under.
	 *
	 * One kind per state rather than one kind carrying the value, because "who graduated our
	 * students" is a question a manager asks of the log directly, and a list filtered by kind
	 * answers it without reading every row's data.
	 *
	 * @return array<string, string> State key to audit kind.
	 */
	public static function kinds() {
		return array(
			self::STATE_GRADUATED => self::KIND_GRADUATED,
			self::STATE_WITHDRAWN => self::KIND_WITHDRAWN,
		);
	}

	/**
	 * The Students table's status column, by the name the base gives it.
	 *
	 * `''` when `WPCPM_Mentors_Sync::fields()` names none, which the handler treats as a refusal
	 * rather than as a column called nothing. `fields()` runs through the `wpcpm_mentors_fields`
	 * filter, so a site can take the key away; a write of `array( '' => 'Graduate' )` would be a
	 * 422 for the whole record at best, and this fails before the request instead.
	 *
	 * Not trimmed. One column in this base really is called `Tutor ` with a trailing space, so a
	 * reader that tidied a column name would be a reader that silently stopped resolving it.
	 *
	 * @return string The column name, or ''.
	 */
	public static function column() {
		$fields = WPCPM_Mentors_Sync::fields();

		return ( isset( $fields[ self::KEY_STATUS ] ) && is_string( $fields[ self::KEY_STATUS ] ) ) ? $fields[ self::KEY_STATUS ] : '';
	}

	/*
	 * Guard 2: the re-read
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether this change may be written, given what the row says right now.
	 *
	 * **The caller passes the status it read live, never the one the roster index holds.** The
	 * index is the Students table as the last sync read it, and this decision is about what the
	 * base says at the moment of the press. `WPCPM_Mentor_Checker_Runner::promote()` is the shape
	 * being copied, including the order: the target is tested first, so a row that already says
	 * `Graduate` is answered "already, left unchanged" rather than falling through to the source
	 * test and being told it is not on the program.
	 *
	 * `Paused` is tested before the tracked-status test, and that order matters on a site whose
	 * saved `student_statuses` has not been through `WPCPM_Settings::maybe_upgrade()` yet: with
	 * `Paused` missing from the list a paused row would be refused as merely untracked, and the
	 * school would be told to ask a program manager without ever learning that the row is paused
	 * or that the mirror automation is the reason.
	 *
	 * The renderer asks this too, so a control is never drawn for a change the handler is going
	 * to refuse. One predicate, two callers.
	 *
	 * @param string $state  One of `states()`' keys.
	 * @param string $status The `Status` the Students row carries now.
	 * @return array{write: bool, outcome: string, detail: string} `outcome` keys `messages()` and
	 *         `notes()`; `detail` is what the row says, for a sentence that quotes it.
	 */
	public static function check( $state, $status ) {
		$states = self::states();
		$state  = (string) $state;
		$status = trim( (string) $status );

		// Guard 1 again, so this predicate cannot be asked a question with no answer. A caller
		// that reached here with an unknown state gets a refusal rather than a write.
		if ( ! isset( $states[ $state ] ) ) {
			return self::verdict( false, self::OUT_UNTRACKED, $status );
		}

		if ( 0 === strcmp( $status, $states[ $state ] ) ) {
			return self::verdict( false, self::OUT_ALREADY, $status );
		}

		if ( 0 === strcmp( $status, self::PAUSED ) ) {
			return self::verdict( false, self::OUT_PAUSED, $status );
		}

		// The tracked active statuses, from the settings both syncs build their formulas from, so
		// the roster, the two syncs and this guard cannot disagree about what "on the program"
		// means. A finished status is caught here: `Graduate` cannot be withdrawn and
		// `Dropped out` cannot be graduated from a school's screen, which is the same rule the
		// missing un-graduate control states.
		$active = WPCPM_Mentors_Sync::tracked_statuses()['active'];

		if ( ! in_array( $status, $active, true ) ) {
			return self::verdict( false, self::OUT_UNTRACKED, $status );
		}

		return self::verdict( true, 'status-' . $state, $status );
	}

	/**
	 * The verdict shape, in one place so every branch of `check()` returns the same keys.
	 *
	 * @param bool   $write   Whether the write may happen.
	 * @param string $outcome The slug `messages()` and `notes()` are keyed by.
	 * @param string $status  What the row says now.
	 * @return array{write: bool, outcome: string, detail: string}
	 */
	private static function verdict( $write, $outcome, $status ) {
		return array(
			'write'   => (bool) $write,
			'outcome' => (string) $outcome,
			'detail'  => (string) $status,
		);
	}

	/**
	 * Which single reason to print when neither change is offered.
	 *
	 * Built from `check()`'s own answers rather than from a second walk over the statuses,
	 * because a second walk is a second place to get the order wrong. A row at `Graduate`
	 * answers `already` for one state and `untracked` for the other, and "the records already
	 * say Graduate" is the sentence a school can act on; a paused row answers `paused` twice and
	 * that sentence outranks everything.
	 *
	 * @param string[] $outcomes What `check()` answered for each state.
	 * @return string One outcome slug.
	 */
	private static function blocked_by( array $outcomes ) {
		if ( in_array( self::OUT_PAUSED, $outcomes, true ) ) {
			return self::OUT_PAUSED;
		}

		if ( in_array( self::OUT_ALREADY, $outcomes, true ) ) {
			return self::OUT_ALREADY;
		}

		return self::OUT_UNTRACKED;
	}

	/*
	 * Guard 3: what the press says it will do
	 * --------------------------------------------------------------------
	 */

	/**
	 * What the confirm dialog asks, naming the student and the mail.
	 *
	 * **The mail is the whole reason there is a dialog.** Marking a student changes one cell here
	 * and sends them a letter from the program that nobody can recall, so the sentence names the
	 * student it is about, says the mail is going out, and says the change is logged under the
	 * presser's name. It also says there is no undo on this screen, because the moment somebody
	 * presses this by mistake the next thing they will do is look for the control that puts it
	 * back, and there is not one.
	 *
	 * "them" rather than "her" or "him": the program's records hold no pronoun, and a dialog that
	 * guessed one would be wrong for somebody on every roster.
	 *
	 * @param string $state One of `states()`' keys.
	 * @param string $name  The student's name as the card knows it.
	 * @return string The dialog text, or '' for a state that is not offered.
	 */
	public static function confirm( $state, $name ) {
		$name = trim( (string) $name );
		$name = '' !== $name ? $name : __( 'this student', 'wpcredits-program-manager' );

		if ( self::STATE_GRADUATED === $state ) {
			return sprintf(
				/* translators: %s: the student's name. */
				__( 'Mark %s as graduated? The program records will email them a certificate notice that cannot be recalled, and the change is logged under your name. There is no un-graduate on this screen: a program manager changes it back in the program records.', 'wpcredits-program-manager' ),
				$name
			);
		}

		if ( self::STATE_WITHDRAWN === $state ) {
			return sprintf(
				/* translators: %s: the student's name. */
				__( 'Mark %s as dropped out? The program records will email them a notice that cannot be recalled, and the change is logged under your name. There is no undo on this screen: a program manager changes it back in the program records.', 'wpcredits-program-manager' ),
				$name
			);
		}

		return '';
	}

	/*
	 * The card
	 * --------------------------------------------------------------------
	 */

	/**
	 * The graduate and withdraw controls for one student, drawn inside the student card.
	 *
	 * Nothing at all when the decision refuses, which is the module's first call pattern: the
	 * card the reader came from is the answer, and a refused reader is not shown a disabled
	 * control telling them what they may not do.
	 *
	 * No Airtable read happens here. The status the controls are decided from is the roster
	 * index's, because that index is the Students table as the last sync read it, so drawing a
	 * roster of forty cards costs no requests. The index can be a day behind, which is exactly
	 * why `handle_change()` asks `check()` again against the live row: this is a card, not a
	 * fence.
	 *
	 * @param string $record  The student's Students record ID.
	 * @param array  $context {
	 *     What the card already knows.
	 *
	 *     @type string $name The student's display name, for the confirm dialog.
	 * }
	 */
	public static function render_form( $record, array $context ) {
		$record = trim( (string) $record );

		// A row with no Students record has nothing to key the nonce to and nothing to claim.
		// The card already says the program's records are incomplete for this student.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return;
		}

		// The same cheap decision `claim()` makes as its own step 2, asked through the same
		// method, so a control cannot be drawn that the handler's first step would refuse.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_CHANGE_STATUS,
			WPCPM_Institution_Roster::cached_subject( $record, WPCPM_Institution_Roster::TYPE_STUDENT )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// `fields` is null for every ground this module ships, so this narrows nothing today. It
		// is here for the reason every other renderer in the module has it: a field-scoped ground
		// added later has to narrow every surface without one of them being edited, and the one
		// that was not is the one that leaks.
		if ( empty( WPCPM_Institution_Policy::scope( $decision, array( self::SLOT => true ) ) ) ) {
			return;
		}

		// **Not a value from the caller.** The handler files its audit row under the institution
		// the DECISION names and reads the roster of that institution; this draws from the same
		// one, so the status the button was decided from and the status the handler compares
		// against are the same table's.
		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';
		$row         = self::index_row( $institution, $record );
		$status      = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';
		$name        = self::name( $context, $row );

		$offered  = array();
		$outcomes = array();

		foreach ( self::states() as $state => $target ) {
			$verdict = self::check( $state, $status );

			if ( ! empty( $verdict['write'] ) ) {
				$offered[] = $state;
			}

			$outcomes[] = $verdict['outcome'];
		}

		printf( '<section class="wpcpm-institution__card wpcpm-institution__status" id="%s">', esc_attr( self::ANCHOR ) );

		printf(
			'<h3 class="wpcpm-institution__heading">%s</h3>',
			esc_html__( 'Finishing this placement', 'wpcredits-program-manager' )
		);

		self::render_message( $record );

		if ( empty( $offered ) ) {
			// The reason is printed before a press rather than only after one. Open question 4
			// settles that for the paused case in as many words: an institution cannot graduate a
			// paused student, and the roster says so on the row rather than failing silently.
			self::render_note( self::blocked_by( $outcomes ), $status );

			echo '</section>';

			return;
		}

		printf(
			'<p class="wpcpm-institution__status-lede">%s</p>',
			esc_html__( 'These two are yours to record when a placement ends. Both send the student a letter from the program records straight away, and neither can be undone from here.', 'wpcredits-program-manager' )
		);

		foreach ( $offered as $state ) {
			self::render_button( $record, $state, $name );
		}

		printf(
			'<p class="wpcpm-institution__status-note">%s</p>',
			esc_html__( 'Recorded the wrong one? Nothing on this screen puts it back. Tell your program contact, who changes it in the program records.', 'wpcredits-program-manager' )
		);

		echo '</section>';
	}

	/**
	 * One control: its own form, its own nonce, its own confirm.
	 *
	 * A form per state rather than one form with two submit buttons, because the value of a
	 * submit button is not posted by every browser when the form is sent another way, and a
	 * handler that read an empty state would be a handler one guard short.
	 *
	 * The nonce is keyed to the record and the state together, so a token for graduating one
	 * student is neither a token for withdrawing them nor a token for anybody else.
	 *
	 * @param string $record The student's Students record ID.
	 * @param string $state  One of `states()`' keys.
	 * @param string $name   The student's name, for the confirm dialog.
	 */
	private static function render_button( $record, $state, $name ) {
		$labels = self::labels();

		printf(
			'<form class="wpcpm-institution__status-form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving...', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_CHANGE . '_' . $record . '_' . $state );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CHANGE ) );
		printf( '<input type="hidden" name="record" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_STATE ), esc_attr( $state ) );

		printf(
			'<button type="submit" class="wpcpm-button" onclick="return confirm(%1$s)">%2$s</button>',
			esc_attr( wp_json_encode( self::confirm( $state, $name ) ) ),
			esc_html( isset( $labels[ $state ] ) ? $labels[ $state ] : $state )
		);

		echo '</form>';
	}

	/**
	 * Why neither control is drawn, in one sentence on the card.
	 *
	 * @param string $outcome One of the OUT_* slugs.
	 * @param string $status  What the roster row says.
	 */
	private static function render_note( $outcome, $status ) {
		$notes = self::notes();

		if ( ! isset( $notes[ $outcome ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-institution__status-note">%1$s%2$s</p>',
			esc_html( $notes[ $outcome ] ),
			esc_html( self::says( $outcome, $status ) )
		);
	}

	/**
	 * The clause that quotes what the program records say, or ''.
	 *
	 * Only the two outcomes whose sentence is about a value get one. A row with no status at all
	 * gets nothing quoted rather than an empty pair of words, because "the records say ." reads
	 * as a bug and is one.
	 *
	 * @param string $outcome One of the OUT_* slugs.
	 * @param string $status  What the row says.
	 * @return string
	 */
	private static function says( $outcome, $status ) {
		$status = trim( (string) $status );

		if ( '' === $status || ! in_array( $outcome, array( self::OUT_ALREADY, self::OUT_UNTRACKED ), true ) ) {
			return '';
		}

		return ' ' . sprintf(
			/* translators: %s: the status stored in the program records. */
			__( 'They say: %s.', 'wpcredits-program-manager' ),
			$status
		);
	}

	/**
	 * The student's name, from the card first and the roster row second.
	 *
	 * The card has already resolved a display name across the account, the cached program row and
	 * the index; this takes that answer rather than resolving a fourth one, so the dialog names
	 * the student the way the card above it does. A row with neither is named generically by
	 * `confirm()`, which is better than naming a record ID at somebody.
	 *
	 * @param array $context The caller's context.
	 * @param array $row     The roster index row.
	 * @return string
	 */
	private static function name( array $context, array $row ) {
		$given = isset( $context['name'] ) && is_scalar( $context['name'] ) ? trim( (string) $context['name'] ) : '';

		if ( '' !== $given ) {
			return $given;
		}

		return isset( $row['name'] ) && is_scalar( $row['name'] ) ? trim( (string) $row['name'] ) : '';
	}

	/**
	 * One roster index row, or an empty array.
	 *
	 * @param string $institution Institutions record ID.
	 * @param string $record      Students record ID.
	 * @return array
	 */
	private static function index_row( $institution, $record ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return array();
		}

		$rows = WPCPM_Roster_Index::rows( $institution );

		return ( isset( $rows[ $record ] ) && is_array( $rows[ $record ] ) ) ? $rows[ $record ] : array();
	}

	/**
	 * The outcome of the last press, on the card it happened on.
	 *
	 * @param string $record The Students record this card is about.
	 */
	private static function render_message( $record ) {
		$flash   = WPCPM_Flash::take( self::FLASH );
		$outcome = is_array( $flash ) && isset( $flash['outcome'] ) ? sanitize_key( (string) $flash['outcome'] ) : '';
		$detail  = is_array( $flash ) && isset( $flash['detail'] ) ? (string) $flash['detail'] : '';
		$about   = is_array( $flash ) && isset( $flash['record'] ) ? trim( (string) $flash['record'] ) : '';

		// Routing, not authorisation: which card prints a sentence, never who may read one.
		if ( '' === $outcome || 0 !== strcmp( trim( (string) $record ), $about ) ) {
			return;
		}

		$messages = self::messages();

		if ( ! isset( $messages[ $outcome ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-institution__status-message is-%1$s" role="status">%2$s%3$s</p>',
			esc_attr( $messages[ $outcome ][0] ),
			esc_html( $messages[ $outcome ][1] ),
			esc_html( self::says( $outcome, $detail ) )
		);
	}

	/*
	 * What the outcomes say
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sentence a refused state gets on the card, before anybody presses anything.
	 *
	 * Kept apart from `messages()` because these are read by somebody who has not pressed a
	 * button: "nothing was changed" would be an answer to a question they did not ask. Same keys,
	 * so a test can assert neither map has an outcome the other does not.
	 *
	 * @return array<string, string>
	 */
	public static function notes() {
		return array(
			self::OUT_ALREADY   => __( 'This placement is already recorded as finished, so there is nothing to record here. A program manager changes it in the program records.', 'wpcredits-program-manager' ),
			self::OUT_PAUSED    => self::paused_sentence(),
			self::OUT_UNTRACKED => __( 'This student is not on the program right now, so how their placement ended is a program manager\'s to record.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * What each outcome says after a press.
	 *
	 * Every refusal opens by saying what did and did not happen, because the reader has just
	 * pressed a button and cannot see the server. The two successes are separate sentences rather
	 * than one with the value filled in, because "recorded as graduated" and "recorded as dropped
	 * out" are the two things somebody will read to check they pressed the right one.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function messages() {
		$manager = __( 'A program manager changes it in the program records.', 'wpcredits-program-manager' );

		return array(
			'status-' . self::STATE_GRADUATED => array( 'success', __( 'Saved. This student is recorded as graduated, and their certificate notice is on its way from the program records. It cannot be recalled from here, and neither can the change.', 'wpcredits-program-manager' ) . ' ' . $manager ),
			'status-' . self::STATE_WITHDRAWN => array( 'success', __( 'Saved. This student is recorded as dropped out, and the program records have sent them their own notice. It cannot be recalled from here, and neither can the change.', 'wpcredits-program-manager' ) . ' ' . $manager ),
			self::OUT_ALREADY                 => array( 'info', __( 'Nothing was changed: the program records already say this.', 'wpcredits-program-manager' ) ),
			self::OUT_PAUSED                  => array( 'error', __( 'Nothing was changed.', 'wpcredits-program-manager' ) . ' ' . self::paused_sentence() ),
			self::OUT_UNTRACKED               => array( 'error', __( 'Nothing was changed: this student is not on the program right now, so how their placement ended is a program manager\'s to record.', 'wpcredits-program-manager' ) ),
			'status-unknown'                  => array( 'error', __( 'Nothing was changed: that is not a change this screen makes. Graduated and dropped out are the two it records; everything else about a student\'s status is the program\'s.', 'wpcredits-program-manager' ) ),
			'status-refused'                  => array( 'error', WPCPM_Institution_Policy::refusal()->get_error_message() ),
			'status-unreadable'               => array( 'error', __( 'Nothing was changed: the program records could not be read just now, so the site did not write anything it could not check first. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'status-airtable'                 => array( 'error', __( 'Nothing was changed: the program records refused the change. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'status-no-row'                   => array( 'error', __( 'Nothing was changed: the program records hold no student row to write this to. A program manager completes the record first.', 'wpcredits-program-manager' ) ),
			'status-column'                   => array( 'error', __( 'Nothing was changed: this site cannot tell which column in the program records holds a student\'s status, so it did not guess. Tell your program contact.', 'wpcredits-program-manager' ) ),
			'status-unfiled'                  => array( 'error', __( 'Nothing was changed: this student\'s row in the program records is not linked to any institution, so there is nothing to record the change against and this screen does not make a change it cannot record. A program manager links the row first.', 'wpcredits-program-manager' ) ),
			'status-unlogged'                 => array( 'error', __( 'The change reached the program records, but the entry saying who made it could not be written, so it is not in this institution\'s history. Tell your program contact, and do not press this again until they have looked.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The paused refusal, written once and printed in two places.
	 *
	 * It names the view because the view is the whole of the reason, and because the person who
	 * can fix it - a program manager, talking to whoever owns the base - needs the name to act
	 * on. A sentence that said only "this student is paused" would send a school to ask for the
	 * pause to be lifted without anybody learning why the site would not simply write the cell.
	 *
	 * @return string
	 */
	private static function paused_sentence() {
		return sprintf(
			/* translators: %s: the Airtable view ID the mirror automation is restricted to. */
			__( 'The program records say this student is paused. The automation that carries a status change through to the rest of their records is restricted to one view, %s, and a paused student is not in it: recording a paused student as finished would email them while their report record, their site account and their mentor\'s list all went on saying paused. Ask your program contact to take them off pause first.', 'wpcredits-program-manager' ),
			self::MIRROR_VIEW
		);
	}

	/*
	 * The write
	 * --------------------------------------------------------------------
	 */

	/**
	 * Move one student's `Status` on the Students table, and nowhere else.
	 *
	 * The order is the whole of the security. The nonce is first, before the record is used for
	 * anything, because `claim()` makes an HTTP request on this site's Airtable credentials and a
	 * cross-site POST must not be able to cause one. Guard 1 is next, because it is free: a
	 * posted state outside `states()` is refused before a single byte reaches the network. Then
	 * `claim()`, which decides from cache, reads the live Students row and decides again against
	 * the link that row carries. Only then is guard 2 asked, against the row that came back.
	 *
	 * The claim settles two things at once and both are taken from it and from nowhere else:
	 * **which Students record** may be written, and **which institution** the audit row is filed
	 * under.
	 */
	public static function handle_change() {
		// Read to key the token and for nothing else. Whether this record is real, whose it is
		// and whether it may be written are all decided below.
		$record = WPCPM_Request::posted_text( 'record' );
		$state  = WPCPM_Request::posted_key( self::FIELD_STATE );

		check_admin_referer( self::ACTION_CHANGE . '_' . $record . '_' . $state );

		$states = self::states();

		// **Guard 1.** Before `claim()` on purpose: a hand-written POST naming `Paused`,
		// `Pending graduation` or anything else this screen does not offer costs no Airtable
		// request at all, so the guard cannot be turned into a way of making this site fetch.
		if ( ! isset( $states[ $state ] ) ) {
			self::bounce( 'status-unknown', '', $record );
		}

		$column = self::column();

		// Fails closed before the network, for the reason `column()` gives: a write naming no
		// column is a write this class does not make.
		if ( '' === $column ) {
			self::bounce( 'status-column', '', $record );
		}

		$claim = WPCPM_Institution_Roster::claim(
			$record,
			WPCPM_Institution_Policy::ACT_CHANGE_STATUS,
			WPCPM_Institution_Roster::TYPE_STUDENT
		);

		if ( is_wp_error( $claim ) ) {
			// The one refusal is the one refusal, byte for byte. Anything else is the base being
			// unreachable, which is a different sentence: a reader told "that record is not on
			// your roster" because Airtable timed out goes looking for a permissions fault that
			// does not exist.
			self::bounce(
				WPCPM_Institution_Policy::REFUSAL_CODE === $claim->get_error_code() ? 'status-refused' : 'status-unreadable',
				'',
				$record
			);
		}

		$decision = isset( $claim['decision'] ) && is_array( $claim['decision'] ) ? $claim['decision'] : array();
		$fields   = isset( $claim['record']['fields'] ) && is_array( $claim['record']['fields'] ) ? $claim['record']['fields'] : array();

		// **The identity of what is about to be written.** `claim()` read this row live and
		// allowed the action on the `Educational Institutions` link that row carries, so this
		// record ID is the only Students record this request holds an authorisation for. Taken
		// from the claim and not from the request, which named the record but proved nothing.
		$students_id = isset( $claim['record']['id'] ) ? trim( (string) $claim['record']['id'] ) : '';

		if ( ! WPCPM_Mentors_Sync::is_record_id( $students_id ) ) {
			self::bounce( 'status-no-row', '', $record );
		}

		$institution = isset( $decision['institution'] ) ? trim( (string) $decision['institution'] ) : '';

		// Refused before the write, because a change on this path that cannot be logged is one
		// this class does not make - and this one sends the student a letter. The manager ground
		// allows an institution-less subject, so a student whose live row has lost its link would
		// otherwise be graduated with nothing written down at all, and there is nothing honest to
		// put in that row's place: the roster index's answer would be a guess.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			self::bounce( 'status-unfiled', '', $record );
		}

		// The same narrowing the renderer applies, so a field-scoped ground added later closes
		// the handler as well as the card.
		if ( empty( WPCPM_Institution_Policy::scope( $decision, array( self::SLOT => true ) ) ) ) {
			self::bounce( 'status-refused', '', $record );
		}

		// **Guard 2**, on the row the claim just read rather than on the roster index the button
		// was drawn from.
		$before  = WPCPM_Airtable::flatten( isset( $fields[ $column ] ) ? $fields[ $column ] : '' );
		$verdict = self::check( $state, $before );

		if ( empty( $verdict['write'] ) ) {
			self::bounce( $verdict['outcome'], $verdict['detail'], $record );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );

		// One table, one cell. The Students Reports row carries a `Status` of its own and is not
		// written here: decision 19 makes the Students table the institution side's authority,
		// and automation `wflUYImI8OEvVuc4R` is what carries this value across.
		$result = $airtable->update_records(
			$settings['students_table'],
			array(
				array(
					'id'     => $students_id,
					'fields' => array( $column => $states[ $state ] ),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::bounce( 'status-airtable', '', $record );
		}

		$logged = self::log( $students_id, $state, $before, $column, $decision );

		self::forget( $institution, $students_id, $states[ $state ] );

		// The guard above rules out the one refusal this handler can cause, so reaching here
		// means the log itself failed - a post that would not insert. Said out loud rather than
		// swallowed: the change is in the base, the student has been emailed, and "Saved." over a
		// change nobody can be held to is the sentence the log exists to prevent.
		if ( is_wp_error( $logged ) ) {
			self::bounce( 'status-unlogged', '', $record );
		}

		self::bounce( $verdict['outcome'], '', $record );
	}

	/**
	 * One audit row, naming the actor, both values and the ground.
	 *
	 * What this row cannot say is whether the student got the letter. The mail is Airtable's, the
	 * log cannot see the automations, and a row here is evidence that this site wrote a cell and
	 * of nothing that happened afterwards. That is worth knowing before somebody reads a graduate
	 * row as proof a certificate went out.
	 *
	 * @param string $students_id The Students record that was written.
	 * @param string $state       One of `states()`' keys.
	 * @param string $from        What the cell said before the write.
	 * @param string $column      The column that was written.
	 * @param array  $decision    The claim's decision.
	 * @return int|WP_Error The audit row's post ID, or why there is not one.
	 */
	private static function log( $students_id, $state, $from, $column, array $decision ) {
		$states = self::states();
		$kinds  = self::kinds();

		return WPCPM_Institution_Audit::record(
			array(
				'kind'        => isset( $kinds[ $state ] ) ? $kinds[ $state ] : '',
				'institution' => isset( $decision['institution'] ) ? $decision['institution'] : '',
				'subject'     => $students_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => sprintf(
					/* translators: 1: the status before the change, 2: the status after it. */
					__( 'Status: %1$s to %2$s.', 'wpcredits-program-manager' ),
					'' === $from ? __( 'empty', 'wpcredits-program-manager' ) : $from,
					$states[ $state ]
				),
				'data'        => array(
					'field'           => $column,
					'from'            => $from,
					'to'              => $states[ $state ],
					'students_record' => $students_id,
				),
			)
		);
	}

	/**
	 * The caches that would otherwise show the status this student had a moment ago.
	 *
	 * The roster index, because that index **is** the Students table as the last sync read it and
	 * it is the one this write actually changed: the school's own list, its group headings and
	 * its counts all read it, so without this the row stays under "current students" until
	 * tomorrow's sync.
	 *
	 * The Student Report Card's transient is dropped for each report record the row names, so the
	 * next reader of that card fetches rather than serving a snapshot taken before the press.
	 * That is a chance and not a promise: the reports row is rewritten by automation
	 * `wflUYImI8OEvVuc4R` and not by this site, and it may not have run yet.
	 *
	 * **The account's own cached program row is deliberately left alone.** Its status comes from
	 * the Students Reports row, which this site did not write; filling it in here would put a
	 * value on a student's own card that no table may ever carry, and it is the one cache a
	 * student reads about themselves.
	 *
	 * @param string $institution Institutions record ID the decision named.
	 * @param string $students_id The Students record that was written.
	 * @param string $to          The value that was written.
	 */
	private static function forget( $institution, $students_id, $to ) {
		$row = self::index_row( $institution, $students_id );

		WPCPM_Roster_Index::update( $institution, $students_id, array( 'status' => $to ) );

		$reports = ( isset( $row['reports'] ) && is_array( $row['reports'] ) ) ? $row['reports'] : array();

		foreach ( $reports as $report ) {
			if ( is_string( $report ) && WPCPM_Mentors_Sync::is_record_id( $report ) ) {
				WPCPM_Student_Report_Form::forget( $report );
			}
		}
	}

	/**
	 * Record the outcome and return to the card the press came from.
	 *
	 * **This does not return.** Every call to it ends the request, which is why a refusal above
	 * reads as one line rather than as an early return with a branch around it.
	 *
	 * The record travels with the outcome, so the sentence is printed on the card it happened to
	 * and not under every student on the roster.
	 *
	 * @param string $outcome Outcome slug, one of `messages()`' keys.
	 * @param string $detail  What the message quotes: the status the row carries.
	 * @param string $record  The Students record the outcome is about.
	 */
	private static function bounce( $outcome, $detail, $record ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				// `outcome` and not `status`: on this card `status` is the student's Airtable
				// status, and one word meaning two things in one array is how the wrong one gets
				// printed at somebody.
				'outcome' => $outcome,
				'detail'  => $detail,
				'record'  => trim( (string) $record ),
			)
		);

		$back = wp_get_referer();

		if ( ! $back ) {
			$page = WPCPM_Institutions_Dashboard::page_url();
			$back = '' !== $page ? $page : home_url( '/' );
		}

		wp_safe_redirect( $back . '#' . self::ANCHOR );
		exit;
	}
}
