<?php
/**
 * One student's card, as the institution that sent them reads it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The card behind a name on the roster, at `?wpcpm_institution_student=<user id>`.
 *
 * Six rules shape it. The first four are assertions in `bin/test-institution-student-view.php`;
 * the two about the controls hanging off the card are in `bin/test-student-view-controls.php`.
 *
 * **The fence is asked here, not taken from the caller.** `decide( ACT_VIEW_STUDENT )` on the
 * student's own account stamp, every render, whoever called it and whatever they passed in the
 * context. A reader who may not see this student is not shown an error either: the argument is
 * dropped and the roster is drawn instead, because "no such student" and "not your student"
 * must read the same way from outside, and a card that answered differently would be a
 * membership oracle somebody could walk a user ID at a time.
 *
 * **Every row prints, even when it is empty.** A blank row is information - nobody has recorded
 * a tutor - and a row that is missing altogether is a question about the page rather than about
 * the record. The same reasoning, and the same "Not set", as the mentor's card.
 *
 * **The mentor is a name and an address and nothing else.** Not their photo, not their
 * WordPress.org profile, not their Slack handle: the school needs to reach the person
 * responsible for their student, and the rest is the mentor's, disclosed to the program.
 * Theirs is also the only address here. The student's own is not one of the columns section
 * 7.5 writes out, and this card is not where a school reaches its own students.
 *
 * **What the student told the program in confidence is not on this page.** The accessibility
 * disclosure sits in the same cached row as the field of study, one array key away, so the
 * table is built from a written-out list of cells and never from a loop over that row. Section
 * 7.5 of the design spec says it plainly: it was disclosed to the program, not to the school.
 *
 * The Student Report Card is the exception to reading from cache: it is fetched from Airtable
 * when the disclosure is opened, through the same route and the same script the mentor's page
 * uses, and authorized there by `WPCPM_Institution_Roster::claim()` rather than by anything
 * this file can see. Grades live in it, and this is the one screen an institution meets them on.
 * It arrives with the calendar stylesheet, which is where the rules for that markup live: one
 * copy of the report's markup wants one copy of its dress, not a second in institution.css.
 *
 * **Four controls hang off the card, and none of them belongs to this file.** The single
 * student export is `WPCPM_Institution_Export`'s, drawn as a link when `decide( ACT_EXPORT )`
 * allows it; graduate and withdraw are `WPCPM_Institution_Students`', drawn by calling its own
 * renderer; the line saying whether the student has signed in yet, with the button that sends
 * their invitation again, is `WPCPM_Institution_Resend`'s, drawn the same way; and "Correct this
 * student's details" is `WPCPM_Institution_Student_Form`'s own form, folded inside the card. Every
 * rule about any of them - which states are offered, that a `Paused` student is refused by name,
 * what the confirm dialog says, who may send an invitation and how often, who may correct a
 * detail and what is saved, how the nonces are keyed - lives in those classes, and this file
 * decides nothing about them beyond where they go.
 *
 * **Where they go is a rule of its own.** The export is a quiet link inside the card. The
 * correction form is a closed disclosure inside the card, after the Student Report Card, so a
 * reader meets what the record says before the way to change it. The status card is the only
 * thing on this page that cannot be taken back - it moves `Status` on the Students table and the
 * program records answer that with mail nobody can recall - so it is drawn last, after the card
 * closes, and nobody meets it on the way to something they came for.
 *
 * **A student with no mentor reads as normal.** The program may move away from mentors for every
 * student, so the Mentor row says "No mentor yet", as the roster's mentor cell does, and the
 * Mentor email row is left out, since there is nobody to write to. Nothing here asks for one.
 */
class WPCPM_Institution_Student_View {

	/** The GET argument naming which student's card to draw. */
	const ARG = 'wpcpm_institution_student';

	/**
	 * Which student the request asks for.
	 *
	 * A user ID rather than an Airtable record: the roster links accounts, and an ID that
	 * belongs to nobody, to an administrator or to another school's student is answered the
	 * same way, by drawing the roster.
	 *
	 * @return int User ID, or 0 when the request names none.
	 */
	public static function requested() {
		return WPCPM_Request::id( self::ARG );
	}

	/**
	 * Whether this card will draw for this reader.
	 *
	 * The predicate the roster asks before it steps aside, so that a request the fence
	 * refuses falls back to the list rather than to an empty page. `render()` asks the same
	 * question again for itself: a caller that forgot this one still draws nothing.
	 *
	 * @param int              $user_id The student's account.
	 * @param int|WP_User|null $user    The reader; null for the current one.
	 * @return bool
	 */
	public static function shows( $user_id, $user = null ) {
		if ( ! self::student( $user_id ) instanceof WP_User ) {
			return false;
		}

		$decision = self::decision( $user_id, $user );

		return ! empty( $decision['allowed'] );
	}

	/**
	 * One student's card.
	 *
	 * `$record_id` is not a second fence and no institution ID is compared here: it names
	 * whose roster index to read the row and the read time from. Which students this reader
	 * may open is `decide()`'s answer and only its answer.
	 *
	 * @param string $record_id Institutions record ID whose roster the reader came from.
	 * @param int    $user_id   The student's account.
	 * @param array  $context {
	 *     What the dashboard already knows, so the card does not read it again.
	 *
	 *     @type int $read Unix time the roster index was read. Read from the index when absent.
	 * }
	 */
	public static function render( $record_id, $user_id, array $context ) {
		$user_id  = (int) $user_id;
		$student  = self::student( $user_id );
		$decision = self::decision( $user_id );

		// Asked again rather than trusted from the caller. This is the render the design spec's
		// first call pattern describes, and it draws nothing at all when it is refused: the
		// roster the reader came from is the answer, and the caller draws it.
		if ( ! $student instanceof WP_User || empty( $decision['allowed'] ) ) {
			return;
		}

		$program = WPCPM_Students_Sync::get_program( $user_id );
		$row     = self::index_row( $record_id, $user_id );
		$mentor  = WPCPM_Students_Sync::get_mentor( $user_id );

		// `fields` is null for every ground this module ships, so this narrows nothing today.
		// It is here because a field-scoped ground added later has to narrow every renderer
		// without one of them being edited, and the one that was not is the one that leaks.
		// Asked here: the header draws its facts from the same cells.
		$cells = WPCPM_Institution_Policy::scope( $decision, self::cells( $student, $program, $row, $mentor ) );

		// Drawn with the shared parts (the Institution Dashboard, design 6.1): the card is a block of the Students view,
		// under the view's own head, with no inset and no box of its own (the view's head is the card's edge). The side
		// menu's Students item is the way back to the roster, so the card prints none of its own.
		echo '<section class="wpcpm-ui-block">';

		self::render_identity_parts( $student, $program, $row, $cells );

		self::render_invite( $student );

		$caption = sprintf(
			/* translators: %s: student name. */
			__( 'Program details for %s', 'wpcredits-program-manager' ),
			self::name( $student, $program, $row )
		);

		self::render_table_parts( $cells, $caption );

		self::render_report( $student );
		self::render_correction( $record_id, $student );
		self::render_export( $user_id );
		self::render_footer( $record_id, $user_id, $context );

		echo '</section>';

		// Last, and outside the card, for two reasons. It is the one control on this page that
		// cannot be taken back, so it sits past everything a reader came here for rather than
		// between them and it. And `render_form()` prints the card "Finishing this placement" of
		// its own, so drawing it inside this one would put a card inside a card.
		self::render_status( $student, $program, $row );
	}

	/**
	 * The decision, for this student's account and this reader.
	 *
	 * The subject is the account's own institution stamp, which is what makes a stranger's
	 * user ID cheap: nothing is fetched, and an account with no stamp belongs to nobody but a
	 * program manager.
	 *
	 * The action is a parameter because this page draws more than one thing and they are not
	 * one permission: the card is `ACT_VIEW_STUDENT` and the export link is `ACT_EXPORT`. Both
	 * grounds shipped today answer the two the same way, so nothing on a live site turns on
	 * this; it is asked separately because `WPCPM_Institution_Export::handle_student()` asks
	 * `ACT_EXPORT` when the link is followed, and a ground that one day allows reading a card
	 * without allowing it to be carried away has to close the link and the handler together.
	 *
	 * @param int              $user_id The student's account.
	 * @param int|WP_User|null $user    The reader; null for the current one.
	 * @param string           $action  One of the policy's ACT_* constants; viewing the card by default.
	 * @return array What decide() returned.
	 */
	private static function decision( $user_id, $user = null, $action = WPCPM_Institution_Policy::ACT_VIEW_STUDENT ) {
		return WPCPM_Institution_Policy::decide(
			$action,
			WPCPM_Institution_Policy::subject_student_account( $user_id ),
			$user
		);
	}

	/**
	 * The account behind the argument, when there is a student behind it.
	 *
	 * A program manager passes the fence for every account, including accounts that are not
	 * students at all, so the shape is checked here as well: a card of ten "Not set" rows for
	 * somebody's administrator account is not a student record, and the roster is the better
	 * answer. Either mark is enough - the role is what the sync grants, the record stamp is
	 * what it writes - because an account can lose one and keep the other.
	 *
	 * @param int $user_id The account.
	 * @return WP_User|null
	 */
	private static function student( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return null;
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) ) {
			return $user;
		}

		return '' !== WPCPM_Mentor_Calls::student_record( $user->ID ) ? $user : null;
	}

	/**
	 * This student's row on that institution's roster index, or an empty row.
	 *
	 * Matched on the account, which is the one identifier both sides carry: the index is keyed
	 * by Students record ID and the card was reached by user ID. An institution whose index has
	 * no row for this account still gets a card - the account's own cached row draws it - and
	 * the footer says when each of the two was last read.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param int    $user_id   The student's account.
	 * @return array
	 */
	private static function index_row( $record_id, $user_id ) {
		$user_id = (int) $user_id;

		foreach ( WPCPM_Roster_Index::rows( $record_id ) as $row ) {
			if ( is_array( $row ) && isset( $row['user_id'] ) && (int) $row['user_id'] === $user_id ) {
				return $row;
			}
		}

		return array();
	}

	/**
	 * The name to print, from whichever cache has one.
	 *
	 * The account's display name first: it is the name the student signs in under and the one
	 * the roster's link was clicked on.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param array   $row     Their roster index row.
	 * @return string
	 */
	private static function name( WP_User $student, array $program, array $row ) {
		$names = array(
			(string) $student->display_name,
			isset( $program['name'] ) ? (string) $program['name'] : '',
			isset( $row['name'] ) ? (string) $row['name'] : '',
		);

		foreach ( $names as $name ) {
			if ( '' !== trim( $name ) ) {
				return trim( $name );
			}
		}

		return __( 'Unnamed student', 'wpcredits-program-manager' );
	}

	/**
	 * The first of several stored values that is not empty.
	 *
	 * Two caches hold this student - the account's own row, written by the students sync, and
	 * the roster index, written by the same run's Students-table pass - and either can be the
	 * one that has not been rebuilt since a field was added. Reading both is the same
	 * self-healing the students sync does for the field of study, and the third place in this
	 * plugin where a page looked broken while the code was right.
	 *
	 * @param array $values Candidate values, best source first.
	 * @return string
	 */
	private static function first( array $values ) {
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * The portrait's address, for both branches: the WordPress.org portrait only, never the Gravatar fallback. That fallback
	 * is a URL carrying a hash of the student's address, which put an address-derived identifier of the student into a page
	 * drawn for a third party and sent the school's browser to gravatar.com with it. With no username there is no portrait,
	 * and the row is drawn without one.
	 *
	 * @param array $program Their cached program row.
	 * @param array $row     Their roster index row.
	 * @return string The address, or ''.
	 */
	private static function portrait( array $program, array $row ) {
		$username = self::first( array( isset( $program['username'] ) ? $program['username'] : '', isset( $row['username'] ) ? $row['username'] : '' ) );

		return WPCPM_Mentors_Dashboard::avatar_url( $username, '', 176 );
	}

	/**
	 * The identity block on the shared parts: the parts' item header (design 2.10), as the Administrator Dashboard heads an
	 * item. The photo in its box (today's portrait, for the same students; with none, the name's first letter, as the
	 * roster's students draw it), the name as the card's one heading (the view's head holds the
	 * page's H1), the program as a status tag in today's words, green for a track as the roster's tags are, and the
	 * placement's dates and cohort as facts, from the cells this reader may read.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param array   $row     Their roster index row.
	 * @param array   $cells   The table's cells this reader may read, keyed by column.
	 */
	private static function render_identity_parts( WP_User $student, array $program, array $row, array $cells ) {
		$name   = self::name( $student, $program, $row );
		$status = self::status( $program );
		$avatar = self::portrait( $program, $row );
		$facts  = array();

		if ( isset( $cells['students|Start Date'] ) ) {
			$dates = self::dates_parts( $cells['students|Start Date'] );

			if ( '' !== $dates ) {
				$facts[] = array( 'html' => $dates );
			}
		}

		if ( isset( $cells['site|cohort'] ) && '' !== trim( (string) $cells['site|cohort']['value'] ) ) {
			$facts[] = (string) $cells['site|cohort']['value'];
		}

		$html = WPCPM_UI::item_header(
			array(
				'name'     => $name,
				'level'    => 'h2',
				// Decoration beside the name the heading already says: an empty alt, so the name is not heard twice.
				'media'    => '' !== $avatar ? sprintf( '<img class="wpcpm-avatar" src="%s" width="44" height="44" alt="" loading="lazy" decoding="async" />', esc_url( $avatar ) ) : '',
				'initials' => WPCPM_UI::initials( $name ),
				'tag'      => '' !== $status ? array(
					'words' => WPCPM_Program::status_words( $status ),
					'tone'  => WPCPM_Program::tone( $status ),
				) : null,
				'facts'    => $facts,
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header(); the photo and the dates escaped here.
	}

	/**
	 * The key and value table on the shared parts (design 2.7, 6): one row per cell this reader may read, in today's
	 * order, each label a row header. Today's rules for a value: what an empty value means when it means something ("No
	 * mentor yet"), else "Not set", both kept in words and muted; the program's course link and a waiting student's tag;
	 * the teams as links; an address, a profile and a website as token cells, which may break anywhere; a mentor's or a
	 * tutor's name as it is; the dates whole.
	 *
	 * @param array  $cells   The cells, keyed by column.
	 * @param string $caption The table's caption, for a screen reader.
	 */
	private static function render_table_parts( array $cells, $caption ) {
		$label = array(
			'key'    => 'label',
			'label'  => '',
			'kind'   => 'text',
			'header' => true,
		);
		$text  = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'text',
		);
		$token = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'token',
		);
		$out   = WPCPM_UI::table_open(
			array( $label, $text ),
			array(
				'keyvalue' => true,
				'caption'  => $caption,
			)
		);

		foreach ( $cells as $key => $cell ) {
			$value = isset( $cell['value'] ) ? trim( (string) $cell['value'] ) : '';
			$blank = isset( $cell['blank'] ) ? trim( (string) $cell['blank'] ) : '';
			$url   = isset( $cell['url'] ) ? trim( (string) $cell['url'] ) : '';
			$kind  = '';

			if ( '' === $value ) {
				$html = esc_html( '' !== $blank ? $blank : __( 'Not set', 'wpcredits-program-manager' ) );
				$kind = 'empty';
			} elseif ( 'students|Start Date' === $key ) {
				$html = self::dates_parts( $cell );
			} elseif ( 'reports|Status' === $key ) {
				$html = '' !== $url ? sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $url ), esc_html( $value ) ) : esc_html( $value );

				// A student waiting to graduate: the course they took, then the status as a tag, as today's row has its badge.
				if ( ! empty( $cell['html'] ) && isset( $cell['status'] ) ) {
					$html .= ' ' . WPCPM_UI::tag( WPCPM_Program::label( (string) $cell['status'] ), WPCPM_Program::tone( (string) $cell['status'] ) );
				}
			} elseif ( ! empty( $cell['html'] ) ) {
				$html = wp_kses_post( (string) $cell['html'] );
			} elseif ( '' !== $url ) {
				$html = sprintf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( $url ),
					! isset( $cell['external'] ) || $cell['external'] ? ' target="_blank" rel="noopener noreferrer"' : '',
					esc_html( $value )
				);
			} else {
				$html = esc_html( $value );
			}

			$out .= WPCPM_UI::row_open()
				. WPCPM_UI::cell( $label, esc_html( isset( $cell['label'] ) ? (string) $cell['label'] : '' ) )
				. WPCPM_UI::cell( '' === $kind && in_array( $key, array( 'reports|WordPress Profile', 'reports|Personal Website URL', 'mentors|Email' ), true ) ? $token : $text, $html, $kind )
				. WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value escaped above.
	}

	/**
	 * The placement's two dates in the mentor card's own words (`WPCPM_Mentors_Dashboard::format_dates()`, called and not
	 * copied, so the two cannot drift), escaped, with each date it names made a whole run (`WPCPM_UI::whole()`), so a
	 * range may break at its words and never inside a date.
	 *
	 * @param array $cell The Internship duration cell.
	 * @return string Escaped markup, or '' when there is neither date.
	 */
	private static function dates_parts( array $cell ) {
		$start = isset( $cell['start'] ) ? (string) $cell['start'] : '';
		$end   = isset( $cell['end'] ) ? (string) $cell['end'] : '';
		$html  = esc_html( (string) WPCPM_Mentors_Dashboard::format_dates( $start, $end ) );

		if ( '' === $html ) {
			return '';
		}

		$format = (string) get_option( 'date_format' );
		$at     = 0;

		// Each date as format_dates() writes it, found in its words from where the last one ended, so a start and an end that
		// read the same are each wrapped once.
		foreach ( array( $start, $end ) as $date ) {
			$words = '' !== $date ? esc_html( (string) date_i18n( $format, strtotime( $date ) ) ) : '';
			$found = '' !== $words ? strpos( $html, $words, $at ) : false;

			if ( false === $found ) {
				continue;
			}

			$whole = WPCPM_UI::whole( html_entity_decode( $words, ENT_QUOTES ) );
			$html  = substr( $html, 0, $found ) . $whole . substr( $html, $found + strlen( $words ) );
			$at    = $found + strlen( $whole );
		}

		return $html;
	}

	/**
	 * Whether the student has signed in yet, under their name, drawn by the class that owns it.
	 *
	 * Every rule about the line and its button is `WPCPM_Institution_Resend`'s: who may press, for
	 * which students, the two limits, what the line and the notices say. Not one of them is
	 * repeated here, and that includes whether to draw anything at all. Guarded, as the other two
	 * controls are, so a site that has not loaded the class draws the card without the line.
	 *
	 * @param WP_User $student The student.
	 */
	private static function render_invite( WP_User $student ) {
		if ( ! class_exists( 'WPCPM_Institution_Resend' ) ) {
			return;
		}

		WPCPM_Institution_Resend::render( $student );
	}

	/**
	 * The program this student is on, as the Students Reports table says it.
	 *
	 * One source, and it has to be this one. Both Airtable tables have a `Status` column and
	 * they are not the same vocabulary: the Students table's is the application pipeline -
	 * `Interested`, `Not moving forward`, `Fail`, `SPAM`, and on this base one row whose status
	 * is the name of a school - while the reports side holds the track the program is named
	 * after. `WPCPM_Program::label()` passes anything it does not recognize straight through,
	 * so reading the roster index row here printed a raw pipeline value in the row a school
	 * reads as "Program". The badge is painted from this same answer, so the two cannot
	 * disagree about which track a student is on.
	 *
	 * Nothing falls back to the index row when this is empty. An empty row says the program
	 * records have not been joined up for this student yet, which is true and is a program
	 * manager's to fix; the other table's status would be an answer to a different question.
	 *
	 * The students sync writes the track under `program`, never under `status`: the key it does
	 * not write was read first here, so the day a cached row grew one it would have taken over
	 * both the row and the badge.
	 *
	 * @param array $program Their cached program row.
	 * @return string
	 */
	private static function status( array $program ) {
		$status = isset( $program['program'] ) ? $program['program'] : '';

		return is_scalar( $status ) ? trim( (string) $status ) : '';
	}

	/**
	 * The rows of the table, keyed by the column each one is about.
	 *
	 * Keys are `"<table>|<field>"`, the vocabulary `WPCPM_Institution_Policy::scope()` narrows:
	 * `reports` and `students` are the two Airtable tables, `mentors` is the mentor's own record,
	 * and `site` is a value this plugin derives rather than reads. `Tutor ` really does end in a
	 * space - that is the column's name in the base, and writing it without one is how a field
	 * silently stops resolving.
	 *
	 * **This list is written out rather than looped.** The cached program row also carries the
	 * accessibility disclosure the student made to the program, and a loop over that row is one
	 * added key away from putting it on a school's screen.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param array   $row     Their roster index row.
	 * @param array   $mentor  Their cached mentor card.
	 * @return array<string, array> Row specs for render_row(), keyed by column.
	 */
	private static function cells( WP_User $student, array $program, array $row, array $mentor ) {
		$status   = self::status( $program );
		$start    = self::first( array( isset( $program['start'] ) ? $program['start'] : '', isset( $row['start'] ) ? $row['start'] : '' ) );
		$end      = self::first( array( isset( $program['end'] ) ? $program['end'] : '', isset( $row['end'] ) ? $row['end'] : '' ) );
		$username = self::first( array( isset( $program['username'] ) ? $program['username'] : '', isset( $row['username'] ) ? $row['username'] : '' ) );
		$website  = self::first( array( isset( $program['website'] ) ? $program['website'] : '' ) );
		$team     = WPCPM_Mentors_Sync::resolve_stored( isset( $program['team'] ) ? (string) $program['team'] : '', 'teams' );

		// Whether there is a mentor to name: the Mentor row's value, and the one thing that decides whether
		// the Mentor email row is drawn at all.
		$mentor_name = isset( $mentor['name'] ) ? trim( (string) $mentor['name'] ) : '';

		// The row the three cards share, with the words this page's roster prints for a status: the
		// shared row says "Graduate" where the roster says "Graduated", and it is not this page's to
		// change for the Mentor Report Card and the Student Report Card. A row with markup of its
		// own (a student waiting to graduate, with the course they took and its badge) is left as
		// it is; its badge says "Pending graduation", a status with no plainer word.
		$program_field = WPCPM_Program::program_field( $status, $student->ID );

		if ( empty( $program_field['html'] ) ) {
			$program_field['value'] = WPCPM_Program::status_words( $status );
		}

		// The status the row is about, for the parts' table, which draws a waiting student's badge as a tag.
		$program_field['status'] = $status;

		$cells = array(
			// The Learn WordPress course for their track, so the syllabus is one click from the row
			// that says which track they are on. The cards' shared row: for a student waiting to
			// graduate it names the course they took, with the Pending graduation badge beside it.
			'reports|Status'                 => $program_field,
			// Both dates in one row, because a school reads a placement as a period rather than
			// as two facts. Keyed on the start date, which is the column that decides the cohort.
			'students|Start Date'            => array(
				'label' => __( 'Internship duration', 'wpcredits-program-manager' ),
				'value' => WPCPM_Mentors_Dashboard::format_dates( $start, $end ),
				'icon'  => 'calendar',
				// The two dates themselves, for the parts' table, which prints each date whole.
				'start' => $start,
				'end'   => $end,
			),
			// Derived, not read: the semester the start date falls in, so the row on the card
			// agrees with the cohort picker on the roster.
			'site|cohort'                    => array(
				'label' => __( 'Cohort', 'wpcredits-program-manager' ),
				'value' => WPCPM_Cohort::label( WPCPM_Cohort::key( $start ) ),
			),
			'students|Your field of study'   => array(
				'label' => __( 'Field of study', 'wpcredits-program-manager' ),
				'value' => self::first( array( isset( $program['field_of_study'] ) ? $program['field_of_study'] : '', isset( $row['field_of_study'] ) ? $row['field_of_study'] : '' ) ),
			),
			'students|Tutor '                => array(
				'label' => __( 'Tutor', 'wpcredits-program-manager' ),
				'value' => self::first( array( isset( $program['tutor'] ) ? $program['tutor'] : '', isset( $row['tutor'] ) ? $row['tutor'] : '' ) ),
			),
			'reports|WordPress Profile'      => array(
				'label' => __( 'WordPress.org', 'wpcredits-program-manager' ),
				'value' => '' !== $username ? '@' . $username : '',
				'url'   => '' !== $username ? 'https://profiles.wordpress.org/' . rawurlencode( $username ) . '/' : '',
				'icon'  => 'profile',
			),
			// No row for the student's own address. Section 7.5 writes this card's columns out
			// and there is no address among them; the one address it names for this page is the
			// mentor's, below, because the person responsible for a placement is the one a
			// school cannot reach without asking the program. Reaching its own students is not
			// something a school needs this card for.
			'reports|Main Contribution Team' => array(
				'label'     => __( 'Contribution teams', 'wpcredits-program-manager' ),
				'value'     => $team,
				// A student can be on more than one team, so the whole cell is built as links.
				'html'      => WPCPM_Contribution_Teams::links( $team ),
				'icon_html' => WPCPM_Contribution_Teams::label_icon( $team ),
			),
			'reports|Personal Website URL'   => array(
				'label' => __( 'Personal website', 'wpcredits-program-manager' ),
				'value' => '' !== $website ? (string) preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) : '',
				'url'   => WPCPM_Mentors_Dashboard::normalize_url( $website ),
				'icon'  => 'website',
			),
			// The mentor, in two rows and no more. A school with a question about their
			// student's placement needs the person to ask and a way to reach them; the rest of
			// the mentor's card - their photo, their profile, their Slack handle, the teams they
			// contribute to - is the mentor's own and was disclosed to the program.
			'mentors|Full Name'              => array(
				'label' => __( 'Mentor', 'wpcredits-program-manager' ),
				'value' => $mentor_name,
				// An empty row here is the answer to the question institutions ask most often
				// about a new student, so it says what it means rather than "Not set". The words are
				// the roster's mentor cell's, so the list and the card agree.
				'blank' => __( 'No mentor yet', 'wpcredits-program-manager' ),
			),
		);

		// The mentor's address is a row only when there is a mentor to write to. A student with
		// none is the ordinary case now and not a gap in the record, so a "Not set" row for
		// somebody who does not exist would send a school looking for an address that is not late.
		// A mentor known by name and not by address keeps the row: that is a gap in the record.
		if ( '' !== $mentor_name ) {
			$cells['mentors|Email'] = array(
				'label'    => __( 'Mentor email', 'wpcredits-program-manager' ),
				'value'    => isset( $mentor['email'] ) ? (string) $mentor['email'] : '',
				'url'      => ! empty( $mentor['email'] ) ? 'mailto:' . $mentor['email'] : '',
				'external' => false,
				'icon'     => 'email',
			);
		}

		return $cells;
	}

	/**
	 * The Student Report Card, read only, fetched when it is opened.
	 *
	 * The same disclosure, the same attributes and the same route as the mentor's page, so
	 * there is one copy of the report markup and one place it is authorized. Reading one costs
	 * an Airtable request: a roster of forty students would pay for forty of them to look at
	 * one, so the body arrives on the toggle and not with the page.
	 *
	 * Whether this reader may have it is decided at the route, by `claim()` against the live
	 * Students row, and not here. A card that hid the disclosure would be a courtesy; the
	 * refusal is the fence.
	 *
	 * A student whose account holds no report record has nothing to fetch. The note says so in a
	 * school's words: the report is the student's progress, and what is missing is the record of it.
	 *
	 * The disclosure is the parts' inner fold titled "Student Report Card", with the fetch hook, and the body the script
	 * replaces is a block inside the fold's body, so the fold keeps its own.
	 *
	 * @param WP_User $student The student.
	 */
	private static function render_report( WP_User $student ) {
		$record = WPCPM_Mentor_Calls::student_record( $student->ID );

		if ( '' === $record ) {
			$none = __( 'This student has no progress record yet, so there is no Student Report Card to show.', 'wpcredits-program-manager' );

			echo WPCPM_UI::intro( $none, 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

			return;
		}

		// The disclosure, its toggle and every field the fetched body arrives with are the
		// report form's own markup, and the rules for all of it live in the calendar
		// stylesheet - the mentor's page has them because it also draws a calendar. Without
		// this, the school's copy of the Student Report Card was the one unstyled thing on the
		// page. None of it is copied into institution.css: two copies of a twenty-field form's
		// layout would drift apart at the next field added to it.
		wp_enqueue_style( WPCPM_Call_Calendar::STYLE );

		// Registered by the mentor dashboard and enqueued here, because this page is the only
		// other one that fetches a report. Both are enqueued from the render rather than from
		// the dashboard's own hook so that a page which never draws a card loads neither.
		wp_enqueue_script( WPCPM_Mentors_Dashboard::SCRIPT );
		wp_localize_script(
			WPCPM_Mentors_Dashboard::SCRIPT,
			'wpcpmDashboard',
			array(
				'reportEndpoint' => esc_url_raw( rest_url( 'wpcpm/v1/report/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'strings'        => array(
					'failed' => __( 'That report could not be loaded just now. Close this and open it again to retry.', 'wpcredits-program-manager' ),
				),
			)
		);

		$title   = __( 'Student Report Card', 'wpcredits-program-manager' );
		$loading = __( 'Loading…', 'wpcredits-program-manager' );
		$script  = __( 'This one needs JavaScript: the Student Report Card is read from the program records when you open it, so that a roster of forty students does not read forty reports nobody asked for.', 'wpcredits-program-manager' );

		$fold = WPCPM_UI::inner_open(
			array(
				'title' => $title,
				'data'  => array( 'wpcpm-report' => $record ),
			)
		)
			. '<div data-wpcpm-report-body>' . WPCPM_UI::intro( $loading, 'muted' ) . '<noscript>' . WPCPM_UI::intro( $script, 'muted' ) . '</noscript></div>'
			. WPCPM_UI::inner_close();

		echo $fold; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open(), intro() and inner_close().
	}

	/**
	 * "Correct this student's details", folded inside the card.
	 *
	 * The existing form, `WPCPM_Institution_Student_Form::render_form()`, in a closed disclosure whose
	 * summary names it. Whether to draw anything at all is the form's: it asks `ACT_EDIT_STUDENT` for
	 * itself and draws nothing for a reader who is refused, for a student with no Students Reports record
	 * and for a student whose record it cannot key a nonce to. So the form is drawn into a buffer and
	 * wrapped only when it drew something, or a refused reader would meet a summary that opens onto
	 * nothing, which is itself an answer about whose student this is.
	 *
	 * **The Students Reports record, and not the Students one.** The form claims the report and keys its
	 * nonce to it, the same record the disclosure above is keyed to; the status card is the one that wants
	 * the Students record, for the reason `render_status()` gives. The context names the institution the
	 * reader came from and the student on screen, never the reader.
	 *
	 * **Closed, except for a save that left something to say.** The form prints the outcome of the last
	 * save inside itself, and a sentence inside a closed disclosure is a sentence nobody reads, so the
	 * disclosure opens when the form drew one. The form is given no flag for it: the buffer already
	 * carries the one class that outcome is printed with, which the form names as a constant
	 * (`CLASS_MESSAGE`) and `bin/test-institution-student-form.php` pins on the real form, and that class
	 * is what is looked for.
	 *
	 * Guarded, as the other controls are, so a site that has not loaded the class draws the card without it.
	 *
	 * The disclosure is the parts' inner fold, under the form's own anchor (`ANCHOR`, where its handler's way back lands).
	 *
	 * @param string  $record_id Institutions record ID whose roster the reader came from.
	 * @param WP_User $student   The student.
	 */
	private static function render_correction( $record_id, WP_User $student ) {
		if ( ! class_exists( 'WPCPM_Institution_Student_Form' ) ) {
			return;
		}

		ob_start();

		WPCPM_Institution_Student_Form::render_form(
			WPCPM_Mentor_Calls::student_record( $student->ID ),
			array(
				'institution' => (string) $record_id,
				'user_id'     => (int) $student->ID,
			)
		);

		$form = (string) ob_get_clean();

		if ( '' === trim( $form ) ) {
			return;
		}

		$open  = false !== strpos( $form, WPCPM_Institution_Student_Form::CLASS_MESSAGE );
		$title = __( 'Correct this student\'s details', 'wpcredits-program-manager' );

		$html = WPCPM_UI::inner_open(
			array(
				'title' => $title,
				'id'    => WPCPM_Institution_Student_Form::ANCHOR,
				'open'  => $open,
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::inner_open().

		// Built and escaped by the form class, field by field; passing it through kses would strip the
		// form's own inputs and its nonce.
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup the form class escapes as it builds it.

		echo WPCPM_UI::inner_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
	}

	/**
	 * The single-student export, as a quiet link.
	 *
	 * A link and not a button: it takes nothing away and changes nothing, and the file it
	 * fetches is this card plus the course grades - which, with the disclosure above it, is
	 * the only place an institution meets a grade at all (design spec 7.5).
	 *
	 * Asked with `ACT_EXPORT` and not borrowed from the decision that drew the card, for the
	 * reason `decision()` gives. Nothing is drawn when it is refused, never a disabled control
	 * with an explanation beside it: why one reader may take a file and another may not is the
	 * policy's business, and a page that answered it would be answering a question about
	 * somebody else's membership.
	 *
	 * There is no `scope()` call here, unlike the table above, because there is no one cell
	 * whose absence would mean "no file": the columns are narrowed inside
	 * `WPCPM_Institution_Export::student_matrix()`, against the decision that handler makes for
	 * itself on the live Students row, and a decision permitting no column refuses there.
	 *
	 * Guarded rather than called outright, for the reason `WPCPM_Student_Report_Form` guards
	 * this module's classes on the report route: this file is not the one that decides which
	 * files a site has loaded, and a card that lost its download link is a smaller failure than
	 * a fatal on the one page a school reads.
	 *
	 * The link is a quiet text link, a line of its own (the parts' intro holding a link, as a sentence with its link is
	 * printed): the parts' quiet button never wraps, and these words are wider than a phone.
	 *
	 * @param int $user_id The student's account.
	 */
	private static function render_export( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! class_exists( 'WPCPM_Institution_Export' ) ) {
			return;
		}

		$decision = self::decision( $user_id, null, WPCPM_Institution_Policy::ACT_EXPORT );

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// The account on screen and never the reader's own: `student_url()` keys its nonce to
		// the user ID it is handed, so `get_current_user_id()` here would mint a token for
		// exporting the member who is looking rather than the student they are looking at.
		$url   = WPCPM_Institution_Export::student_url( $user_id );
		$label = __( 'Download this student\'s details and course grades as a CSV file', 'wpcredits-program-manager' );

		echo WPCPM_UI::intro( '', '', '', sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro(); the address and the words escaped here.
	}

	/**
	 * Graduate and withdraw, drawn by the class that owns them.
	 *
	 * Every rule about these two is `WPCPM_Institution_Students`': which states are offered,
	 * that a `Paused` student is refused by name and told why, that the confirm dialog names
	 * the mail the program records send, how each nonce is keyed to one record and one state,
	 * and whether to draw anything at all. Not one of them is repeated here, and that includes
	 * the last: `render_form()` is called for a student with no Students record too, and left
	 * to say so itself. A second copy of a rule about a write that mails a student is the copy
	 * that goes stale.
	 *
	 * **The Students record, and not the Students Reports one.**
	 * `WPCPM_Mentor_Calls::student_record()` answers with the reports record - it is what the
	 * disclosure above is keyed to, and what `handle_student()` claims as `TYPE_REPORT` - while
	 * `handle_change()` claims this one as `TYPE_STUDENT` against the Students table. Handing
	 * the reports ID over would key both nonces to a record that claim cannot find, so the two
	 * buttons would draw and neither would work. The Students ID is on the roster index row,
	 * under `record_id`, which is also the key `render_form()` looks that row up by.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param array   $row     Their roster index row.
	 */
	private static function render_status( WP_User $student, array $program, array $row ) {
		if ( ! class_exists( 'WPCPM_Institution_Students' ) ) {
			return;
		}

		WPCPM_Institution_Students::render_form(
			isset( $row['record_id'] ) ? (string) $row['record_id'] : '',
			// The name this card already resolved across the account, the cached row and the
			// index, so the confirm dialog names the student the way the heading above it does
			// rather than working out a fourth answer of its own.
			array( 'name' => self::name( $student, $program, $row ) )
		);
	}

	/**
	 * When everything on this card was last read.
	 *
	 * Two caches and one live read, so the footer names all three rather than printing one
	 * timestamp over data of three different ages. Every screen in this module says how old
	 * what it shows is; this one has the most to say.
	 *
	 * The line is the parts' muted intro, the card's last line.
	 *
	 * @param string $record_id Institutions record ID whose roster this is.
	 * @param int    $user_id   The student's account.
	 * @param array  $context   What the dashboard passed in.
	 */
	private static function render_footer( $record_id, $user_id, array $context ) {
		$read = isset( $context['read'] ) ? (int) $context['read'] : 0;

		if ( $read <= 0 ) {
			$index = WPCPM_Roster_Index::read( $record_id );
			$read  = (int) $index['read'];
		}

		$synced = (int) get_user_meta( (int) $user_id, WPCPM_Students_Sync::META_UPDATED, true );
		$facts  = array();

		if ( $read > 0 ) {
			$facts[] = sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours". */
				__( 'The roster this student is on was read %s ago.', 'wpcredits-program-manager' ),
				human_time_diff( $read, time() )
			);
		}

		if ( $synced > 0 ) {
			$facts[] = sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours". */
				__( 'Their own details were synced %s ago.', 'wpcredits-program-manager' ),
				human_time_diff( $synced, time() )
			);
		}

		$facts[] = __( 'The Student Report Card is read when you open it.', 'wpcredits-program-manager' );

		echo WPCPM_UI::intro( implode( ' ', $facts ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().
	}
}
