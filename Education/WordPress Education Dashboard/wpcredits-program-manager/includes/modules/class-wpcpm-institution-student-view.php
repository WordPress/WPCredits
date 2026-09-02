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
 * Four rules shape it, and each of them is an assertion in `bin/test-institution-student-view.php`.
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
 * uses, and authorised there by `WPCPM_Institution_Roster::claim()` rather than by anything
 * this file can see. Grades live in it, and this is the one screen an institution meets them on.
 * It arrives with the calendar stylesheet, which is where the rules for that markup live: one
 * copy of the report's markup wants one copy of its dress, not a second in institution.css.
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
	 *     @type int    $read Unix time the roster index was read. Read from the index when absent.
	 *     @type string $back URL of the roster to return to. The current URL without the
	 *                        argument by default, which keeps the filters the reader arrived with.
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

		// The dashboard's own card box, so one student's card is the same block as the roster
		// it replaced rather than a second treatment of the same idea.
		echo '<section class="wpcpm-institution__card wpcpm-institution__student">';

		self::render_back( $context );
		self::render_identity( $student, $program, $row );

		// `fields` is null for every ground this module ships, so this narrows nothing today.
		// It is here because a field-scoped ground added later has to narrow every renderer
		// without one of them being edited, and the one that was not is the one that leaks.
		$cells = WPCPM_Institution_Policy::scope( $decision, self::cells( $student, $program, $row, $mentor ) );

		printf(
			'<table class="wpcpm-mentee__table wpcpm-institution__table"><caption class="screen-reader-text">%s</caption><tbody>',
			esc_html(
				sprintf(
					/* translators: %s: student name. */
					__( 'Program details for %s', 'wpcredits-program-manager' ),
					self::name( $student, $program, $row )
				)
			)
		);

		foreach ( $cells as $cell ) {
			self::render_row( $cell );
		}

		echo '</tbody></table>';

		self::render_report( $student );
		self::render_footer( $record_id, $user_id, $context );

		echo '</section>';
	}

	/**
	 * The decision, for this student's account and this reader.
	 *
	 * The subject is the account's own institution stamp, which is what makes a stranger's
	 * user ID cheap: nothing is fetched, and an account with no stamp belongs to nobody but a
	 * program manager.
	 *
	 * @param int              $user_id The student's account.
	 * @param int|WP_User|null $user    The reader; null for the current one.
	 * @return array What decide() returned.
	 */
	private static function decision( $user_id, $user = null ) {
		return WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_STUDENT,
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
	 * The identity block: photo, name, and the program badge.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param array   $row     Their roster index row.
	 */
	private static function render_identity( WP_User $student, array $program, array $row ) {
		$name     = self::name( $student, $program, $row );
		$username = self::first( array( isset( $program['username'] ) ? $program['username'] : '', isset( $row['username'] ) ? $row['username'] : '' ) );
		$status   = self::status( $program );
		$avatar   = WPCPM_Mentors_Dashboard::avatar_url( $username, $student->user_email, 176 );

		// The row institution.css lays out, on the element it names. The photo used to sit in
		// an outer wrapper of its own, which the stylesheet had never heard of, so the row rule
		// landed on the name alone and the photo stacked above it.
		echo '<div class="wpcpm-institution__student-identity">';

		if ( '' !== $avatar ) {
			// `wpcpm-avatar` and no size modifier: the student page's `--lg` lives in
			// calendar.css, which this page loads only when it draws a Student Report Card, so a
			// portrait sized by it would change size with the presence of a report record.
			printf(
				'<img class="wpcpm-avatar" src="%1$s" width="88" height="88" alt="%2$s" loading="lazy" decoding="async" />',
				esc_url( $avatar ),
				esc_attr(
					sprintf(
						/* translators: %s: student name. */
						__( 'Profile photo of %s', 'wpcredits-program-manager' ),
						$name
					)
				)
			);
		}

		// The name and its badge, in the mentor page's own group rather than a second one of
		// this page's: the two headers read as the same header, and the group is defined once,
		// in the stylesheet both pages already load.
		echo '<div class="wpcpm-mentee__identity">';

		// A paragraph, not a heading: the page's own `<h1>` is the dashboard's, and the card
		// sits inside the section the roster heading already named.
		printf( '<p class="wpcpm-institution__student-name">%s</p>', esc_html( $name ) );

		if ( '' !== $status ) {
			$badge = WPCPM_Program::badge( $status );

			printf(
				'<span class="wpcpm-badge%1$s">%2$s</span>',
				'' === $badge ? '' : esc_attr( ' wpcpm-badge--' . $badge ),
				esc_html( WPCPM_Program::label( $status ) )
			);
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * The program this student is on, as the Students Reports table says it.
	 *
	 * One source, and it has to be this one. Both Airtable tables have a `Status` column and
	 * they are not the same vocabulary: the Students table's is the application pipeline -
	 * `Interested`, `Not moving forward`, `Fail`, `SPAM`, and on this base one row whose status
	 * is the name of a school - while the reports side holds the track the program is named
	 * after. `WPCPM_Program::label()` passes anything it does not recognise straight through,
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

		return array(
			'reports|Status'                 => array(
				'label' => __( 'Program', 'wpcredits-program-manager' ),
				'value' => WPCPM_Program::label( $status ),
				// The Learn WordPress course for their track, so the syllabus is one click from
				// the row that says which track they are on.
				'url'   => WPCPM_Program::course_url( $status ),
			),
			// Both dates in one row, because a school reads a placement as a period rather than
			// as two facts. Keyed on the start date, which is the column that decides the cohort.
			'students|Start Date'            => array(
				'label' => __( 'Internship duration', 'wpcredits-program-manager' ),
				'value' => WPCPM_Mentors_Dashboard::format_dates( $start, $end ),
				'icon'  => 'calendar',
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
				'value' => isset( $mentor['name'] ) ? (string) $mentor['name'] : '',
				// An empty row here is the answer to the question institutions ask most often
				// about a new student, so it says what it means rather than "Not set".
				'blank' => __( 'No mentor assigned yet', 'wpcredits-program-manager' ),
			),
			'mentors|Email'                  => array(
				'label'    => __( 'Mentor email', 'wpcredits-program-manager' ),
				'value'    => isset( $mentor['email'] ) ? (string) $mentor['email'] : '',
				'url'      => ! empty( $mentor['email'] ) ? 'mailto:' . $mentor['email'] : '',
				'external' => false,
				'icon'     => 'email',
			),
		);
	}

	/**
	 * Render one row of the table.
	 *
	 * A second copy of the mentor card's row renderer, deliberately: that one is private to a
	 * class this page is not part of, and the two have different rules about what may appear in
	 * them. Sharing it would mean a public renderer that both pages have to agree about, and the
	 * disagreement is the point.
	 *
	 * An empty value prints as a muted placeholder rather than being skipped, unless the row
	 * says what its own blank means - "No mentor assigned yet" is an answer, and painting it as
	 * missing data would send somebody looking for a record that is not late.
	 *
	 * @param array $field {
	 *     The row to draw.
	 *
	 *     @type string $label     Visible label.
	 *     @type string $value     Display value; may be empty. Also decides whether the row counts as empty.
	 *     @type string $url       Optional URL to wrap the value in.
	 *     @type bool   $external  Whether the link opens in a new tab. Default true.
	 *     @type string $html      Pre-built, already-escaped cell markup. Takes precedence over `url`.
	 *     @type string $icon      Key into WPCPM_Icons for a fixed row icon.
	 *     @type string $icon_html Pre-built icon markup, for an icon that depends on the value.
	 *     @type string $blank     What an empty value means, when it means something.
	 * }
	 */
	private static function render_row( array $field ) {
		$label    = isset( $field['label'] ) ? (string) $field['label'] : '';
		$value    = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		$url      = isset( $field['url'] ) ? trim( (string) $field['url'] ) : '';
		$external = ! isset( $field['external'] ) || (bool) $field['external'];
		$blank    = isset( $field['blank'] ) ? trim( (string) $field['blank'] ) : '';
		$html     = isset( $field['html'] ) ? (string) $field['html'] : '';

		if ( '' === $value && '' !== $blank ) {
			$value = $blank;
		}

		$empty = ( '' === $value );

		printf( '<tr class="wpcpm-mentee__row%s">', $empty ? ' is-empty' : '' );

		$icon = isset( $field['icon_html'] )
			? (string) $field['icon_html']
			: ( isset( $field['icon'] ) ? WPCPM_Icons::svg( $field['icon'] ) : '' );

		printf(
			'<th scope="row" data-label="%1$s"><span class="wpcpm-mentee__label">%2$s%3$s</span></th>',
			esc_attr__( 'Field', 'wpcredits-program-manager' ),
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG from WPCPM_Icons; kses would lowercase viewBox and break it.
			esc_html( $label )
		);

		printf( '<td class="wpcpm-mentee__value" data-label="%s">', esc_attr__( 'Value', 'wpcredits-program-manager' ) );

		if ( $empty ) {
			printf(
				'<span class="wpcpm-mentee__missing">%s</span>',
				esc_html__( 'Not set', 'wpcredits-program-manager' )
			);
		} elseif ( '' !== $html ) {
			echo wp_kses_post( $html );
		} elseif ( '' !== $url ) {
			printf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $url ),
				$external ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( $value )
			);
		} else {
			echo esc_html( $value );
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * The way back to the roster.
	 *
	 * The current URL without the argument by default, so a reader who arrived from a filtered
	 * or searched roster is returned to the same one rather than to the top of the list.
	 *
	 * @param array $context What the dashboard passed in.
	 */
	private static function render_back( array $context ) {
		$back = isset( $context['back'] ) ? trim( (string) $context['back'] ) : '';

		if ( '' === $back ) {
			$back = (string) remove_query_arg( self::ARG );
		}

		printf(
			'<p class="wpcpm-institution__back"><a href="%1$s">%2$s</a></p>',
			esc_url( $back ),
			esc_html__( 'Back to the roster', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The Student Report Card, read only, fetched when it is opened.
	 *
	 * The same disclosure, the same attributes and the same route as the mentor's page, so
	 * there is one copy of the report markup and one place it is authorised. Reading one costs
	 * an Airtable request: a roster of forty students would pay for forty of them to look at
	 * one, so the body arrives on the toggle and not with the page.
	 *
	 * Whether this reader may have it is decided at the route, by `claim()` against the live
	 * Students row, and not here. A card that hid the disclosure would be a courtesy; the
	 * refusal is the fence.
	 *
	 * @param WP_User $student The student.
	 */
	private static function render_report( WP_User $student ) {
		$record = WPCPM_Mentor_Calls::student_record( $student->ID );

		if ( '' === $record ) {
			printf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html__( 'This student has no report record in the program data yet, so there is no Student Report Card to show.', 'wpcredits-program-manager' )
			);

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

		printf(
			'<details class="wpcpm-report__disclosure wpcpm-institution__report" data-wpcpm-report="%1$s">'
				. '<summary class="wpcpm-report__toggle">%2$s</summary>'
				. '<div class="wpcpm-report__body" data-wpcpm-report-body>'
				. '<p class="wpcpm-student__note">%3$s</p>'
				. '<noscript><p class="wpcpm-student__note">%4$s</p></noscript>'
				. '</div>'
				. '</details>',
			esc_attr( $record ),
			esc_html__( 'Student Report Card', 'wpcredits-program-manager' ),
			esc_html__( 'Loading…', 'wpcredits-program-manager' ),
			esc_html__( 'This one needs JavaScript: the Student Report Card is read from the program records when you open it, so that a roster of forty students does not read forty reports nobody asked for.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * When everything on this card was last read.
	 *
	 * Two caches and one live read, so the footer names all three rather than printing one
	 * timestamp over data of three different ages. Every screen in this module says how old
	 * what it shows is; this one has the most to say.
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

		printf(
			'<p class="wpcpm-institution__read">%s</p>',
			esc_html( implode( ' ', $facts ) )
		);
	}
}
