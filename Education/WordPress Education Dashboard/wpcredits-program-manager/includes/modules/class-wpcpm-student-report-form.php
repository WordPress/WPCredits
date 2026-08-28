<?php
/**
 * The report form a student fills in on their own Report Card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Students Reports record, as a form the student owns.
 *
 * **The fields differ by track, and the lists are the program's, not a guess.** A student on
 * *In Sensei* files twenty-two things; one on *In Sensei 50h* files ten. The two sets come from two
 * grid views in the base built for exactly this, and they are declared here in code rather than read
 * from those views because **Airtable exposes no way to read a view's visible fields** — the
 * metadata API returns a view's id, name and type, and asking for records with `view=` returned the
 * same forty-two fields for both. So the lists have to be maintained by hand, and `bin/test-report-form.php`
 * pins them so a silent drift shows up as a failure rather than as a missing field on somebody's card.
 *
 * **Grades and hours are the student's to type.** That looked wrong and was queried: they are graded
 * elsewhere and copy the score across, so this form is a transcription rather than an assessment.
 * Nothing here is computed from anything, and nothing here decides whether they pass.
 *
 * **The current values are read live from Airtable, not from the synced row.** The sync carries a
 * dozen fields for the cards; these twenty-two are not among them, and adding them would mean a page
 * that shows "Not set" for everything until the next sync runs — the trap that hid *Field of study*
 * on every student card for two days. A short transient keeps the page quick, and saving clears it,
 * so a student always sees what they just typed.
 */
class WPCPM_Student_Report_Form {

	const ACTION_SAVE = 'wpcpm_save_report';

	/** How long a fetched record is reused, in seconds. */
	const CACHE_TTL = 300;

	/** Transient prefix for one student's record. */
	const CACHE_PREFIX = 'wpcpm_report_';

	/** Longest a free-text answer may be. */
	const MAX_TEXT = 5000;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/*
	 * The two field sets
	 * --------------------------------------------------------------------
	 */

	/**
	 * The fields one track's report form holds, in the order they are shown.
	 *
	 * Keys are the **Airtable field names**, because those are what a write has to name and having
	 * one identifier rather than two is one fewer thing to keep in step. `Company ` carries a
	 * trailing space in the base — the same trap as `Tutor ` on the Students table, and dropping it
	 * makes the write silently do nothing.
	 *
	 * `step` mirrors the column's own precision: the grades allow two decimals, hours and the three
	 * course marks are whole numbers.
	 *
	 * @param string $track Track key from `WPCPM_Program::track()`: `150h`, `50h` or `dev`. Anything
	 *                      else, including the empty string a finished student has, gets the
	 *                      150-hour form — the one most of them filled in.
	 * @return array<string, array> Airtable field name => spec.
	 */
	public static function fields( $track ) {
		$grade = array(
			'type'  => 'number',
			'step'  => '0.01',
			'min'   => 0,
			'max'   => 100,
			'group' => 'onboarding',
		);

		$mark = array(
			'type'  => 'number',
			'step'  => '1',
			'min'   => 0,
			'max'   => 100,
			'group' => 'onboarding',
		);

		$hours = array(
			'Hours' => array(
				'label' => __( 'Hours contributed', 'wpcredits-program-manager' ),
				'type'  => 'number',
				'step'  => '1',
				'min'   => 0,
				'max'   => 10000,
				'group' => 'hours',
				'help'  => __( 'The total you have logged so far.', 'wpcredits-program-manager' ),
			),
		);

		// The first two lessons of Onboarding. They were rows in *My profile* until 1.48.0, which
		// meant the personal website was editable from two controls writing one Airtable column —
		// the thing that had already been fixed for contribution teams. The form owns all three
		// now, and the profile shows them without an editor.
		$contact = array(
			'WordPress Profile' => array(
				'label' => __( 'Your WordPress.org profile', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'onboarding',
				'row'   => 'contact',
				'help'  => __( 'Your profile page, or just your username.', 'wpcredits-program-manager' ),
			),
			'Slack Name'        => array(
				'label'     => __( 'Your Slack name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'maxlength' => 100,
				'group'     => 'onboarding',
				'row'       => 'contact',
				'help'      => __( 'Your display name in the Making WordPress Slack.', 'wpcredits-program-manager' ),
			),
		);

		$common_grades = array(
			'Open source basics and WordPress - final grade' => array(
				'label'    => __( 'Open source basics and WordPress', 'wpcredits-program-manager' ),
				'subgroup' => __( 'Enter your final grade', 'wpcredits-program-manager' ),
			) + $grade,
			'How decisions are made in the WordPress project - final grade' => array( 'label' => __( 'How decisions are made in the WordPress project', 'wpcredits-program-manager' ) ) + $grade,
		);

		// Conflict resolution is asked on both courses. It lived in `$fifty_grades` alone because the
		// 50-hour form was built first — the long course asks it too, between the voice course and
		// the three user levels, which is the order its own form uses.
		$conflict = array(
			'Basic principles of conflict resolution - final grade' => array( 'label' => __( 'Basic principles of conflict resolution', 'wpcredits-program-manager' ) ) + $grade,
		);

		$sensei_grades = array(
			'Community meeting etiquette - final grade'    => array( 'label' => __( 'Community meeting etiquette', 'wpcredits-program-manager' ) ) + $grade,
			'Writing in the WordPress voice - final grade' => array( 'label' => __( 'Writing in the WordPress voice', 'wpcredits-program-manager' ) ) + $grade,
		) + $conflict + array(
			'Beginner WordPress User - final grade'     => array(
				'label'   => __( 'Beginner WordPress User', 'wpcredits-program-manager' ),
				'divider' => true,
			) + $grade,
			'Intermediate WordPress User - final grade' => array( 'label' => __( 'Intermediate WordPress User', 'wpcredits-program-manager' ) ) + $grade,
			'Advance WordPress User - final grade'      => array(
				'label' => __( 'Advanced WordPress User', 'wpcredits-program-manager' ),
				'note'  => __( 'Complete one of the following courses', 'wpcredits-program-manager' ),
			) + $grade,
		);

		// Named on the form, because a mark for a course nobody had to take should not look like a
		// missing answer. The heading is printed above the first field carrying it.
		$sensei_courses = array(
			'Beginner WordPress Developer' => array( 'divider' => true )
				+ $mark + array( 'label' => __( 'Beginner WordPress Developer', 'wpcredits-program-manager' ) ),
			'Intermediate Theme Developer' => array( 'label' => __( 'Intermediate Theme Developer', 'wpcredits-program-manager' ) ) + $mark,
			'Beginner WordPress Designer'  => array(
				'label' => __( 'Beginner WordPress Designer', 'wpcredits-program-manager' ),
				'note'  => __( 'Optional courses', 'wpcredits-program-manager' ),
			) + $mark,
		);

		$fifty_grades = $conflict;

		// Developer track only. Long text in the base, so long text here — these are lists a
		// student writes out (modules taken, tickets commented on) rather than single values.
		$dev_basics = array(
			'Developer Basics: modules completed'      => array(
				'label' => __( 'Developer Basics: modules you completed', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'onboarding',
				'help'  => __( 'One per line.', 'wpcredits-program-manager' ),
			),
			// `Basics` capitalised above and lower here is how the base spells the two columns. The
			// keys are what a write has to name, so both are copied exactly rather than tidied.
			'Developer basics: Optional modules taken' => array(
				'label' => __( 'Developer Basics: optional modules you took', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'onboarding',
				'help'  => __( 'One per line. Leave empty if you took none.', 'wpcredits-program-manager' ),
			),
		);

		$dev_patch = array(
			'Patch Testing: Trac ticket comments' => array(
				'label' => __( 'Patch testing: your Trac ticket comments', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'onboarding',
				'help'  => __( 'Links to the tickets you commented on, one per line.', 'wpcredits-program-manager' ),
			),
		);

		$dev_project = array(
			'Optional: Additional Contribution Project Summary' => array(
				'label' => __( 'A second contribution project, if you had one', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
				'help'  => __( 'Optional. Leave empty if you worked on one project.', 'wpcredits-program-manager' ),
			),
		);

		// In *Project*, between the first-contribution post and the halfway one, which is where the
		// base's own dev-track view puts them. They read as end-of-programme questions and were in
		// Wrap-up until 1.63.0 — but where a question is asked is the program's decision, not an
		// inference from what it sounds like, and the view is where that decision is recorded.
		$dev_alumni = array(
			'Contributing beyond WP Credits'   => array(
				'label' => __( 'How you plan to keep contributing after the program', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Alumni program: personal email'   => array(
				'label' => __( 'A personal email address for the alumni programme', 'wpcredits-program-manager' ),
				'type'  => 'email',
				'group' => 'project',
				'row'   => 'alumni',
				'help'  => __( 'Somewhere that still reaches you once your student address stops working.', 'wpcredits-program-manager' ),
			),
			// The label says what is being agreed to. Repeating the column name here would ask for
			// consent without stating what for.
			'Alumni program: mentoring opt-in' => array(
				'label' => __( 'Yes, I am happy to be contacted about mentoring future WordPress Credits students.', 'wpcredits-program-manager' ),
				'type'  => 'checkbox',
				'group' => 'project',
				'row'   => 'alumni',
			),
		);

		// `Contribution Project Summary` is the column's name in the base. It was
		// `Contribution Project Description` here until 1.61.0 — a name matching no field, so the
		// answer neither loaded nor saved. The same class of failure as the trailing space on
		// `Company `, and the reason `bin/test-report-form.php` now checks every key against a
		// fixture of the table's real field names.
		$project = array(
			'Contribution Project Summary' => array(
				'label' => __( 'Describe your contribution project', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Personal Website URL'         => array(
				'label' => __( 'Your personal website URL', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'project',
			),
		);

		$posts = array(
			'Post Reflection: Building Your Personal Website' => array(
				'label' => __( 'Link to the Post "Reflection: Building Your Personal Website"', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'posts',
			),
			'Post Reflection: Choosing Your Team and Project' => array(
				'label' => __( 'Link to the Post "Reflection: Choosing Your Team and Project"', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'posts',
			),
			'Post Reflection: Your First Contribution' => array(
				'label' => __( 'Link to the Post "Reflection: Your First Contribution"', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'posts',
			),
			'Post Reflection: Halfway Check-In'        => array(
				'label' => __( 'Link to the Post "Reflection: Halfway Check-In"', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'posts',
			),
			'Closing post URL'                         => array(
				'label' => __( 'Your closing post', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'posts',
			),
		);

		$participation = array(
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => array(
				'label' => __( 'Meetings and discussions you took part in', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'part',
				'help'  => __( 'Slack, GitHub or blog links, one per line.', 'wpcredits-program-manager' ),
			),
		);

		// Asked for here rather than in *My profile*: it is a question about the project, and the
		// Airtable form this replaces asks it at the head of the Project section. One control, one
		// column — the profile no longer offers it, so there is still only one place to answer.
		$teams = array(
			'Main Contribution Team' => array(
				'label' => __( 'Main contribution team', 'wpcredits-program-manager' ),
				'type'  => 'team',
				'group' => 'project',
				'row'   => 'project',
				'help'  => __( 'The teams you are contributing to. Choose as many as apply.', 'wpcredits-program-manager' ),
			),
		);

		// A field's group differs by track: the personal website is onboarding on the long course
		// and an optional wrap-up lesson on the 50-hour one, which is how the two Airtable forms
		// have it. Rather than two copies of the spec, the group is set as each track is composed.
		$in = static function ( array $spec, $group ) {
			$spec['group'] = $group;

			return $spec;
		};

		if ( '50h' === $track ) {
			$fields = $hours + $contact + $common_grades + $fifty_grades + $teams + array(
				'Contribution Project Summary'      => array(
					'row'   => 'project',
					'stack' => true,
				) + $in( $project['Contribution Project Summary'], 'project' ),
				'Slack/GitHub/Blog WordPress Community meetings/discussions' => array(
					'row'   => 'project',
					'stack' => true,
				) + $in( $participation['Slack/GitHub/Blog WordPress Community meetings/discussions'], 'project' ),
				'Final Contribution Project Report' => array(
					'label' => __( 'Your final project report', 'wpcredits-program-manager' ),
					'type'  => 'richtext',
					'group' => 'wrapup',
					'help'  => __( 'The write-up of what you built and contributed.', 'wpcredits-program-manager' ),
				),
				'Personal Website URL'              => $in( $project['Personal Website URL'], 'wrapup' ),
			);
		} else {
			$fields = $hours + $contact + $common_grades + $sensei_grades + $sensei_courses + array(
				// Onboarding closes with the website and the post about building it.
				'Personal Website URL' => array(
					'subgroup' => __( 'Create your personal website', 'wpcredits-program-manager' ),
					'row'      => 'website',
				) + $in( $project['Personal Website URL'], 'onboarding' ),
				'Post Reflection: Building Your Personal Website' => array( 'row' => 'website' )
					+ $in( $posts['Post Reflection: Building Your Personal Website'], 'onboarding' ),
			) + $teams + array(
				'Contribution Project Summary'             => array(
					'row'   => 'project',
					'stack' => true,
				) + $in( $project['Contribution Project Summary'], 'project' ),
				'Post Reflection: Choosing Your Team and Project' => array(
					'row'   => 'project',
					'stack' => true,
				) + $in( $posts['Post Reflection: Choosing Your Team and Project'], 'project' ),
				'Slack/GitHub/Blog WordPress Community meetings/discussions' => array(
					'row'   => 'project',
					'stack' => true,
				) + $in( $participation['Slack/GitHub/Blog WordPress Community meetings/discussions'], 'project' ),
				'Post Reflection: Your First Contribution' => array( 'divider' => true )
					+ $in( $posts['Post Reflection: Your First Contribution'], 'project' ),
				'Post Reflection: Halfway Check-In'        => $in( $posts['Post Reflection: Halfway Check-In'], 'project' ),
				'WP event participation URL'               => array(
					'label' => __( 'Link to a WordPress event you have participated in (online or in person)', 'wpcredits-program-manager' ),
					'type'  => 'url',
					'group' => 'project',
				),
				'Closing post URL'                         => $in( $posts['Closing post URL'], 'wrapup' ),
			);

			// The developer track is the 150-hour form plus seven fields, in the places the base's
			// own "Temporal view for dev track" puts them. Written as insertions into that set
			// rather than as a third copy, because a copy would drift the moment either changed.
			if ( 'dev' === $track ) {
				$fields = self::insert_after( $fields, 'Advance WordPress User - final grade', $dev_basics );
				$fields = self::insert_after( $fields, 'Beginner WordPress Designer', $dev_patch );
				$fields = self::insert_after( $fields, 'Contribution Project Summary', $dev_project );
				$fields = self::insert_after( $fields, 'Post Reflection: Your First Contribution', $dev_alumni );
			}
		}

		/**
		 * Filter the report form's fields for one track.
		 *
		 * @param array  $fields Airtable field name => spec.
		 * @param string $track  Track key: `150h`, `50h` or `dev`.
		 */
		return (array) apply_filters( 'wpcpm_report_form_fields', $fields, $track );
	}

	/**
	 * Put fields straight after a named one, keeping every other key where it was.
	 *
	 * Order inside a group is the array's own order — `render_body()` groups with `array_filter()`,
	 * which preserves it — so where a field sits in this array is where a student sees it.
	 *
	 * A missing anchor appends rather than throws: a form with a question in the wrong place is
	 * recoverable, a fatal on the Student Report Card is not. `bin/test-report-form.php` asserts
	 * each insertion's position, so a renamed anchor fails a test rather than moving quietly.
	 *
	 * @param array  $fields Field set.
	 * @param string $anchor Field name to insert after.
	 * @param array  $add    Fields to insert.
	 * @return array
	 */
	private static function insert_after( array $fields, $anchor, array $add ) {
		if ( ! isset( $fields[ $anchor ] ) ) {
			return $fields + $add;
		}

		$out = array();

		foreach ( $fields as $name => $spec ) {
			$out[ $name ] = $spec;

			if ( $name === $anchor ) {
				foreach ( $add as $add_name => $add_spec ) {
					$out[ $add_name ] = $add_spec;
				}
			}
		}

		return $out;
	}

	/**
	 * The groups the fields are shown in, in order.
	 *
	 * **Twenty boxes in one run is a wall, not a form.** These are the sections of the Airtable form
	 * this replaces — Total hours, Onboarding, Project, Wrap-up — so a student who has filled that in
	 * before finds the same shape here, and the two can be read side by side while both exist.
	 *
	 * The numbers sit several to a row because they are two characters wide; the prose gets the full
	 * width.
	 *
	 * @return array<string, string> Group key => legend.
	 */
	public static function groups() {
		return array(
			'hours'      => __( 'Total hours', 'wpcredits-program-manager' ),
			'onboarding' => __( 'Onboarding', 'wpcredits-program-manager' ),
			'project'    => __( 'Project', 'wpcredits-program-manager' ),
			'wrapup'     => __( 'Wrap-up', 'wpcredits-program-manager' ),
		);
	}

	/*
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * One student's report record, from Airtable.
	 *
	 * Cached briefly and cleared on save. A failure returns the `WP_Error` rather than an empty
	 * array, so the form can say the record could not be read instead of showing every field blank
	 * — which a student would read as their work having been lost.
	 *
	 * @param string $record Airtable record ID.
	 * @return array|WP_Error Field name => value.
	 */
	public static function values( $record ) {
		$record = trim( (string) $record );

		if ( '' === $record ) {
			return new WP_Error( 'wpcpm_no_record', __( 'Your record could not be found in the program data.', 'wpcredits-program-manager' ) );
		}

		$key    = self::CACHE_PREFIX . md5( $record );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->get_record( $settings['reports_table'], $record );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = isset( $result['fields'] ) && is_array( $result['fields'] ) ? $result['fields'] : array();

		set_transient( $key, $fields, self::CACHE_TTL );

		return $fields;
	}

	/**
	 * Forget a cached record.
	 *
	 * @param string $record Airtable record ID.
	 */
	public static function forget( $record ) {
		delete_transient( self::CACHE_PREFIX . md5( trim( (string) $record ) ) );
	}

	/**
	 * Whether this person may fill in that student's form.
	 *
	 * The student themselves, or a program manager — the same rule the profile fields use. A mentor
	 * deliberately cannot: the report is the student's own account of their work, and a mentor typing
	 * it for them would make the record say something it does not mean.
	 *
	 * @param int $student_id Student user ID.
	 * @return bool
	 */
	public static function user_can_edit( $student_id ) {
		$student_id = (int) $student_id;

		if ( $student_id <= 0 || ! is_user_logged_in() ) {
			return false;
		}

		return get_current_user_id() === $student_id || current_user_can( WPCPM_Roles::CAP_MANAGE );
	}


	/*
	 * Rendering
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form, as the *Report form* section's contents.
	 *
	 * One form for every field rather than a row of little ones: a student sits down once to fill
	 * this in, and twenty-two separate saves would be twenty-two page loads.
	 *
	 * @param WP_User $student The student whose report this is.
	 * @param array   $program Their cached program row, for the track.
	 * @param string  $heading Optional label for the disclosure; the student's own wording by
	 *                         default, so a mentor reading one is not told it is theirs.
	 */
	public static function render( WP_User $student, array $program, $heading = '' ) {
		$message = self::message( self::status() );

		// Closed by default: it is a long form somebody opens deliberately, and a Report Card that
		// begins with twenty boxes buries everything under it. **Open when there is something to
		// say** — a "Saved" or a rejected grade behind a closed disclosure is a message nobody
		// reads, which is the same reasoning that reopens a student's card after a note is saved.
		printf(
			'<details class="wpcpm-report__disclosure"%s>',
			empty( $message ) ? '' : ' open'
		);
		// No field count beside it. It was there to say how much was behind the disclosure, and
		// what it actually said was "twenty-four things to do" — which is the opposite of the
		// reason the form is grouped and headed at all.
		printf(
			'<summary class="wpcpm-report__toggle">%s</summary>',
			esc_html( '' !== $heading ? $heading : __( 'Your report form', 'wpcredits-program-manager' ) )
		);

		self::render_body( $student, $program );

		echo '</details>';
	}

	/**
	 * What is inside the disclosure.
	 *
	 * Split from the wrapper so the mentor's page can fetch one on demand: the markup is the same
	 * either way, and there is no second copy of the form to keep in step.
	 *
	 * `$read_only` is the *view's* decision, separate from the capability check. A program manager
	 * may edit any report, but not from a mentor's page: there the report is somebody else's record
	 * being read, and an editable copy of it — with a Save button — is an invitation to answer a
	 * question on the student's behalf. The capability still governs where a manager does edit, on
	 * the student's own card.
	 *
	 * @param WP_User $student   The student whose report this is.
	 * @param array   $program   Their cached program row, for the track.
	 * @param bool    $read_only Force a record rather than a form, whatever the viewer may do.
	 */
	private static function render_body( WP_User $student, array $program, $read_only = false ) {
		$track  = WPCPM_Program::track( isset( $program['program'] ) ? $program['program'] : '' );
		$fields = self::fields( $track );
		$record = WPCPM_Mentor_Calls::student_record( $student->ID );
		$values = self::values( $record );
		$can    = ! $read_only && self::user_can_edit( $student->ID );

		echo '<div class="wpcpm-report__body">';

		self::render_message();

		if ( is_wp_error( $values ) ) {
			// Said out loud rather than drawn as an empty form. Twenty-two blank boxes over a
			// student's real answers is the one outcome worse than an error message: they would
			// fill it in again, press Save, and overwrite what was already there.
			printf(
				'<p class="wpcpm-student__note wpcpm-report__error">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the reason the record could not be read. */
						__( 'Your report form could not be loaded just now: %s Nothing has been lost — try reloading the page.', 'wpcredits-program-manager' ),
						$values->get_error_message()
					)
				)
			);

			echo '</div>';

			return;
		}

		if ( ! $can ) {
			printf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html__( 'This is the student\'s own report, so it is shown here but not editable.', 'wpcredits-program-manager' )
			);
		}

		// Not a `<form>` at all when it cannot be saved. Disabled fields post nothing and the save
		// handler checks the capability again, so a form here would be harmless — but it would still
		// be a form, and the reason to leave it out is what it says: a reader of somebody else's
		// report is looking at a record, not at something addressed to them. It also means there is
		// no nonce, no student ID and no submit path in markup nobody may submit.
		if ( $can ) {
			printf(
				'<form class="wpcpm-report" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Saving…', 'wpcredits-program-manager' )
			);

			wp_nonce_field( self::ACTION_SAVE . '_' . (int) $student->ID );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
		} else {
			// The same class, so one set of layout rules dresses both.
			echo '<div class="wpcpm-report wpcpm-report--readonly">';
		}

		// Grouped, so the form reads as four short questions rather than twenty boxes. `hours`
		// is skipped: it is rendered in *My course*, beside the course button, by
		// `render_hours()` — one field, posting to this same handler.
		foreach ( self::groups() as $group => $legend ) {
			if ( 'hours' === $group ) {
				continue;
			}

			$in_group = array_filter(
				$fields,
				static function ( $spec ) use ( $group ) {
					return isset( $spec['group'] ) && $group === $spec['group'];
				}
			);

			if ( empty( $in_group ) ) {
				continue;
			}

			printf(
				'<fieldset class="wpcpm-report__group wpcpm-report__group--%1$s"><legend>%2$s</legend>',
				esc_attr( $group ),
				esc_html( $legend )
			);

			$subgroup = '';
			$row      = '';
			$stacked  = false;

			foreach ( $in_group as $name => $spec ) {
				if ( ! empty( $spec['subgroup'] ) && $spec['subgroup'] !== $subgroup ) {
					$subgroup = $spec['subgroup'];

					// A heading closes whatever row was open: the lesson below it starts a new one.
					if ( '' !== $row ) {
						if ( $stacked ) {
							echo '</div>';
							$stacked = false;
						}

						echo '</div>';
						$row = '';
					}

					printf(
						'<p class="wpcpm-report__sub">%s</p>',
						esc_html( $subgroup )
					);
				}

				// A rule with no heading over it: a run of fields that starts a section of its own
				// but needs no naming, because its own labels already say what it is.
				if ( ! empty( $spec['divider'] ) ) {
					if ( '' !== $row ) {
						if ( $stacked ) {
							echo '</div>';
							$stacked = false;
						}

						echo '</div>';
						$row = '';
					}

					echo '<p class="wpcpm-report__rule" aria-hidden="true"></p>';
				}

				$wants = isset( $spec['row'] ) ? (string) $spec['row'] : '';

				if ( $wants !== $row ) {
					if ( '' !== $row ) {
						if ( $stacked ) {
							echo '</div>';
							$stacked = false;
						}

						echo '</div>';
					}

					// Its own grid inside the group's, so a pair stays a pair: the group packs
					// whatever fits into a row, which is how the Slack box ended up in the middle
					// of a run of course marks.
					if ( '' !== $wants ) {
						printf( '<div class="wpcpm-report__pair wpcpm-report__pair--%s">', esc_attr( $wants ) );
					}

					$row = $wants;
				}

				// Everything marked `stack` shares one column of the pair, in order. Opened at the
				// first of them and closed with the row.
				if ( ! empty( $spec['stack'] ) && ! $stacked ) {
					echo '<div class="wpcpm-report__stack">';
					$stacked = true;
				}

				self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : '', $can );

				// A note under the run rather than a heading over it: "complete one of the
				// following" is a condition on the answers above, not a name for them.
				if ( ! empty( $spec['note'] ) ) {
					if ( '' !== $row ) {
						if ( $stacked ) {
							echo '</div>';
							$stacked = false;
						}

						echo '</div>';
						$row = '';
					}

					printf( '<p class="wpcpm-report__note">%s</p>', esc_html( $spec['note'] ) );
				}
			}

			if ( '' !== $row ) {
				if ( $stacked ) {
					echo '</div>';
					$stacked = false;
				}

				echo '</div>';
			}

			echo '</fieldset>';
		}

		if ( $can ) {
			printf(
				'<p class="wpcpm-report__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
				esc_html__( 'Save my report', 'wpcredits-program-manager' )
			);
		}

		echo $can ? '</form>' : '</div>';
		echo '</div>';
	}

	/**
	 * Register the route that serves one student's report to their mentor.
	 */
	public static function register_route() {
		register_rest_route(
			'wpcpm/v1',
			'/report/(?P<record>[A-Za-z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_report' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
				'args'                => array(
					'record' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Who may read a report over the route.
	 *
	 * A program manager may read any; a mentor may read the students assigned to them and nobody
	 * else. **The mentee list is the authority**, not the request: it is the same list their page
	 * is drawn from, so a record they were never given cannot be asked for by editing a URL.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public static function rest_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		$record = (string) $request['record'];

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( get_current_user_id() ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === $record ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One student's report, rendered read only.
	 *
	 * Served on demand rather than with the page: reading a report costs an Airtable request, and
	 * a mentor with sixty students would pay for sixty of them to look at one.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public static function rest_report( $request ) {
		$record  = (string) $request['record'];
		$student = WPCPM_Students_Sync::user_for_record( $record );

		if ( ! $student instanceof WP_User ) {
			return new WP_REST_Response(
				array( 'html' => '<p class="wpcpm-report__error">' . esc_html__( 'That student has no account on this site yet, so there is no report to show.', 'wpcredits-program-manager' ) . '</p>' ),
				200
			);
		}

		$program = WPCPM_Students_Sync::get_program( $student->ID );

		ob_start();
		// Read only for everyone, including a program manager: this route exists to show a mentor
		// the report their student wrote, and the answers are the student's to give.
		self::render_body( $student, $program, true );

		return new WP_REST_Response( array( 'html' => ob_get_clean() ), 200 );
	}

	/**
	 * The hours box, for *My course* rather than for the form.
	 *
	 * The one number a student updates most often, and the only one they update without having
	 * anything else to report — so it sits beside the course button rather than behind a
	 * disclosure with twenty other questions.
	 *
	 * Its own `<form>`, posting to the same handler with the same nonce. Two forms, one field
	 * each way: the handler walks the posted keys and ignores what it was not sent, so nothing
	 * here can clear an answer given in the other one.
	 *
	 * @param WP_User $student The student whose report this is.
	 * @param array   $program Their cached program row.
	 */
	public static function render_hours( WP_User $student, array $program ) {
		$record = isset( $program['record_id'] ) ? (string) $program['record_id'] : '';

		if ( '' === $record ) {
			return;
		}

		$fields = self::fields( WPCPM_Program::track( isset( $program['program'] ) ? $program['program'] : '' ) );

		if ( ! isset( $fields['Hours'] ) ) {
			return;
		}

		$values = self::values( $record );

		// A failure is left to the form below, which says so properly. Showing an empty box here
		// would read as "you have logged nothing", which is a different and alarming statement.
		if ( is_wp_error( $values ) ) {
			return;
		}

		$can = self::user_can_edit( $student->ID );

		printf(
			'<form class="wpcpm-hours" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_SAVE . '_' . (int) $student->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );

		// Drawn here rather than through `render_field()`. That renders one `<p>` holding label,
		// input and hint, which leaves the Save button an outsider beside all three — the label
		// above it, the hint below, and nothing lining up with anything. This is a box and a
		// button on one line, with the label over them and the hint under: three rows, one column,
		// left aligned.
		$spec  = $fields['Hours'];
		$value = isset( $values['Hours'] ) ? $values['Hours'] : '';
		$id    = 'wpcpm-report-' . self::key( 'Hours' );

		printf(
			'<label class="wpcpm-hours__label" for="%1$s">%2$s</label>',
			esc_attr( $id ),
			esc_html( $spec['label'] )
		);

		echo '<span class="wpcpm-hours__entry">';

		printf(
			'<input type="number" id="%1$s" name="report[%2$s]" value="%3$s" step="%4$s" min="%5$s" max="%6$s" inputmode="numeric"%7$s />',
			esc_attr( $id ),
			esc_attr( self::key( 'Hours' ) ),
			esc_attr( is_scalar( $value ) ? (string) $value : '' ),
			esc_attr( isset( $spec['step'] ) ? $spec['step'] : 'any' ),
			esc_attr( isset( $spec['min'] ) ? (string) $spec['min'] : '' ),
			esc_attr( isset( $spec['max'] ) ? (string) $spec['max'] : '' ),
			$can ? '' : ' disabled="disabled"'
		);

		if ( $can ) {
			printf(
				'<button type="submit" class="wpcpm-button">%s</button>',
				esc_html__( 'Save hours', 'wpcredits-program-manager' )
			);
		}

		echo '</span>';

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint">%s</span>', esc_html( $spec['help'] ) );
		}

		echo '</form>';
	}

	/**
	 * One field.
	 *
	 * @param string $name  Airtable field name.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Its current value.
	 * @param bool   $can   Whether it may be edited.
	 */
	private static function render_field( $name, array $spec, $value, $can ) {
		$key  = self::key( $name );
		$id   = 'wpcpm-report-' . $key;
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
		$dis  = $can ? '' : ' disabled="disabled"';

		// A `<div>` for the checkbox list, a `<p>` for everything else. **A `<p>` cannot contain a
		// `<fieldset>`**: the parser closes the paragraph the moment one opens, so the list and the
		// hint after it became siblings of the field rather than its children — and, in a grid,
		// separate items. That is what scattered the team block across the row.
		//
		// A checkbox list also has no single control to point `for` at, so its name is a plain
		// span; the `<fieldset>` inside carries the accessible grouping instead.
		$wrapper = ( 'team' === $type ) ? 'div' : 'p';

		// A checkbox reads as "[x] Yes, I agree to…", so the box comes first and the label after
		// it. Printing the label above would turn a consent question into a heading with an
		// unlabelled tick under it.
		if ( 'checkbox' === $type ) {
			// **The hidden zero is what makes unticking possible.** A cleared checkbox posts
			// nothing at all, and `handle_save()` skips any field the browser did not send — so
			// without this a student could tick the box once and never take it back. It is a
			// consent checkbox, so that is the one direction that must work.
			printf(
				'<p class="wpcpm-field wpcpm-field--checkbox"><input type="hidden" name="report[%2$s]" value="0" /><input type="checkbox" id="%1$s" name="report[%2$s]" value="1"%3$s%4$s /><label for="%1$s">%5$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( self::is_ticked( $value ), true, false ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_html( $spec['label'] )
			);

			if ( ! empty( $spec['help'] ) ) {
				printf( '<span class="wpcpm-field__hint">%s</span>', esc_html( $spec['help'] ) );
			}

			echo '</p>';

			return;
		}

		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s">%3$s',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			'team' === $type
				? '<span class="wpcpm-field__label">' . esc_html( $spec['label'] ) . '</span>'
				: sprintf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $spec['label'] ) )
		);

		if ( 'team' === $type ) {
			self::render_teams( $key, $value, $can, $spec );
		} elseif ( 'number' === $type ) {
			printf(
				'<input type="number" id="%1$s" name="report[%2$s]" value="%3$s" step="%4$s" min="%5$s" max="%6$s" inputmode="decimal"%7$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				esc_attr( isset( $spec['step'] ) ? $spec['step'] : 'any' ),
				esc_attr( isset( $spec['min'] ) ? (string) $spec['min'] : '' ),
				esc_attr( isset( $spec['max'] ) ? (string) $spec['max'] : '' ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);
		} elseif ( 'textarea' === $type || 'richtext' === $type ) {
			printf(
				'<textarea id="%1$s" name="report[%2$s]" rows="%3$d" maxlength="%4$d"%5$s>%6$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				'richtext' === $type ? 8 : 4,
				(int) self::MAX_TEXT,
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_textarea( is_scalar( $value ) ? (string) $value : '' )
			);
		} elseif ( 'email' === $type ) {
			printf(
				'<input type="email" id="%1$s" name="report[%2$s]" value="%3$s" inputmode="email" autocomplete="email"%4$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);
		} else {
			// `type="text"` even for the URLs, for the reason the profile editor gives: `type="url"`
			// refuses a scheme-less address, and Airtable's url columns are full of them — the
			// browser would block the save with a message a student cannot act on. `clean_url()`
			// adds the scheme instead.
			printf(
				'<input type="text" id="%1$s" name="report[%2$s]" value="%3$s" inputmode="url"%4$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint">%s</span>', esc_html( $spec['help'] ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}


	/**
	 * Whether a stored value means a ticked box.
	 *
	 * Airtable sends a checkbox as `true` or omits the field entirely when it is unticked, so an
	 * absent value is a real answer here rather than missing data.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function is_ticked( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return is_scalar( $value ) && in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes' ), true );
	}

	/**
	 * The contribution-team checkboxes.
	 *
	 * A list of checkboxes rather than a `<select multiple>`: a student can contribute to more than
	 * one team, every option and every current answer is visible at once, and it is operable on a
	 * phone, where a multi-select is a scrolling trap that hides what is already chosen.
	 *
	 * Matched by **record ID**, not by name. The profile editor had to match on names because the
	 * cached student row keeps the resolved name; this form reads the record from Airtable live, so
	 * a linked-record column arrives as the IDs themselves.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Current value: an array of record IDs.
	 * @param bool   $can   Whether it may be edited.
	 * @param array  $spec  Field spec.
	 */
	private static function render_teams( $key, $value, $can, array $spec ) {
		$teams = WPCPM_Contribution_Teams::options();

		if ( empty( $teams ) ) {
			printf(
				'<span class="wpcpm-field__hint">%s</span>',
				esc_html__( 'The team list has not been read from Airtable yet. Run a sync and this becomes editable.', 'wpcredits-program-manager' )
			);

			return;
		}

		$selected = array();

		foreach ( (array) $value as $id ) {
			if ( is_scalar( $id ) && isset( $teams[ (string) $id ] ) ) {
				$selected[] = (string) $id;
			}
		}

		printf(
			'<fieldset class="wpcpm-report__teams"><legend class="screen-reader-text">%s</legend>',
			esc_html( $spec['label'] )
		);

		foreach ( $teams as $record_id => $team_name ) {
			printf(
				// The team's own icon, the same one the student's card and the mentor's table
				// show for it, so a team is recognisable here by the mark rather than only by
				// reading the name. `label_icon()` escapes what it builds and is decorative —
				// `aria-hidden` — because the name beside it already says which team this is.
				'<label class="wpcpm-report__check"><input type="checkbox" name="report[%1$s][]" value="%2$s"%3$s%4$s />%5$s <span>%6$s</span></label>',
				esc_attr( $key ),
				esc_attr( $record_id ),
				in_array( (string) $record_id, $selected, true ) ? ' checked="checked"' : '',
				$can ? '' : ' disabled="disabled"',
				WPCPM_Contribution_Teams::label_icon( $team_name ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One escaped <span> built by label_icon().
				esc_html( $team_name )
			);
		}

		// Unchecking every box posts nothing at all for `report[team]`, and the save loop skips a
		// key it was not sent — so clearing the last team would silently do nothing. This empty
		// value is always posted, so the array always arrives.
		//
		// Only where there is something to post to. In a read-only view it is the one control left
		// that could still carry a value, and a field nobody may change should not be in the markup
		// at all.
		if ( $can ) {
			printf( '<input type="hidden" name="report[%s][]" value="" />', esc_attr( $key ) );
		}

		echo '</fieldset>';
	}

	/**
	 * The outcome of the last save, if there is one.
	 */
	private static function render_message() {
		$message = self::message( self::status() );

		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-calls__message wpcpm-calls__message--%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/*
	 * Saving
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write the submitted answers back to Airtable.
	 */
	public static function handle_save() {
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_SAVE . '_' . $student_id );

		if ( ! self::user_can_edit( $student_id ) ) {
			wp_die( esc_html__( 'You cannot fill in that report form.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Mentor_Calls::student_record( $student_id );

		if ( '' === $record ) {
			self::bounce( 'report-no-record' );
		}

		$program = WPCPM_Students_Sync::get_program( $student_id );
		$fields  = self::fields( WPCPM_Program::track( isset( $program['program'] ) ? $program['program'] : '' ) );

		$posted = isset( $_POST['report'] ) && is_array( $_POST['report'] )
			? wp_unslash( $_POST['report'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is validated by type below.
			: array();

		$cells    = array();
		$rejected = array();

		foreach ( $fields as $name => $spec ) {
			$key = self::key( $name );

			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}

			list( $ok, $value ) = self::clean( $posted[ $key ], $spec );

			// **"Rejected" and "cleared" cannot be the same answer.** Airtable empties a number
			// column with `null`, so `null` is a legitimate value to send — and a first draft used
			// it for "could not be understood" as well, which would have made an unreadable grade
			// silently erase the stored one. Hence the pair: whether to write, and what.
			if ( $ok ) {
				$cells[ $name ] = $value;
			} else {
				$rejected[] = $spec['label'];
			}
		}

		if ( empty( $cells ) ) {
			self::bounce( empty( $rejected ) ? 'report-nothing' : 'report-rejected' );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->update_records(
			$settings['reports_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::bounce( 'report-refused' );
		}

		// The cache holds what Airtable had a moment ago, which is now wrong.
		self::forget( $record );

		// Four of these answers are also shown on the cards, from a copy the sync leaves behind —
		// so without this a student who chose their team saw *Not set* on their own card until the
		// next sync, with the answer sitting in Airtable the whole time.
		WPCPM_Students_Sync::apply_report( $student_id, $cells );

		// Everything readable was saved; anything that was not is named rather than dropped in
		// silence, which is what makes a rejected grade findable instead of mysterious.
		self::bounce( empty( $rejected ) ? 'report-saved' : 'report-partly' );
	}

	/**
	 * A submitted value, in the shape Airtable takes, or null if it cannot be used.
	 *
	 * Every type is handled explicitly. Nothing reaches Airtable without passing through here —
	 * a number field given "twelve" is a 422 for the whole request, so one bad answer would
	 * otherwise lose the other twenty-one.
	 *
	 * @param mixed $raw  Posted value.
	 * @param array $spec Field spec.
	 * @return array{0:bool,1:mixed} Whether to write it, and what to write.
	 */
	private static function clean( $raw, array $spec ) {
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';

		// Before the scalar guard: teams post as an array of checkbox values, and reading them
		// through `is_scalar()` would turn the array into an empty string — the field would stop
		// saving the moment the control became a checkbox list, and silently.
		if ( 'team' === $type ) {
			$known = WPCPM_Contribution_Teams::options();
			$ids   = array();

			foreach ( (array) $raw as $id ) {
				if ( ! is_scalar( $id ) ) {
					continue;
				}

				$id = trim( (string) $id );

				// Duplicates collapse, because Airtable will happily store the same link twice.
				if ( '' !== $id && isset( $known[ $id ] ) && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}

			// A linked-record field takes an array of record IDs, however many. An empty array is
			// how every link is cleared; a bare empty string would be rejected.
			return array( true, $ids );
		}

		if ( ! is_scalar( $raw ) ) {
			return array( false, null );
		}

		$raw = trim( (string) $raw );

		// A checkbox posts `1` when ticked and `0` from the hidden input when not, so both are
		// answers and neither can be rejected. Airtable takes a real boolean.
		if ( 'checkbox' === $type ) {
			return array( true, '1' === $raw || 'true' === strtolower( $raw ) );
		}

		if ( 'email' === $type ) {
			// Emptying the box clears the column, the same bargain the number fields strike.
			if ( '' === $raw ) {
				return array( true, '' );
			}

			$email = sanitize_email( $raw );

			return is_email( $email ) ? array( true, $email ) : array( false, null );
		}

		if ( 'number' === $type ) {
			// Emptying the box means emptying the column, and Airtable does that with `null`. An
			// empty string in a number field is a 422 for the whole request, which would lose the
			// other twenty-one answers along with this one.
			if ( '' === $raw ) {
				return array( true, null );
			}

			// A comma decimal is what half of Europe types, and Airtable will not take it.
			$raw = str_replace( ',', '.', $raw );

			if ( ! is_numeric( $raw ) ) {
				return array( false, null );
			}

			$number = ( isset( $spec['step'] ) && '1' === $spec['step'] ) ? (int) round( (float) $raw ) : round( (float) $raw, 2 );

			if ( isset( $spec['min'] ) && $number < $spec['min'] ) {
				return array( false, null );
			}

			if ( isset( $spec['max'] ) && $number > $spec['max'] ) {
				return array( false, null );
			}

			return array( true, $number );
		}

		if ( 'url' === $type ) {
			return array( true, self::clean_url( $raw ) );
		}

		if ( 'text' === $type ) {
			$max = isset( $spec['maxlength'] ) ? (int) $spec['maxlength'] : self::MAX_TEXT;

			return array( true, sanitize_text_field( mb_substr( $raw, 0, $max ) ) );
		}

		// `textarea` and `richtext` both arrive as text. Rich text is Markdown in Airtable, so it
		// is stored as typed rather than being converted — a student writing a bullet list gets a
		// bullet list, and one writing prose gets prose.
		return array( true, mb_substr( sanitize_textarea_field( $raw ), 0, self::MAX_TEXT ) );
	}


	/**
	 * A URL the way the rest of the plugin normalizes them.
	 *
	 * Airtable's url columns are full of scheme-less values, and a student retyping one the same
	 * way should not be told off for it.
	 *
	 * @param string $raw Posted value.
	 * @return string
	 */
	private static function clean_url( $raw ) {
		if ( '' === $raw ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $raw ) ) {
			$raw = 'https://' . ltrim( $raw, '/' );
		}

		$url = esc_url_raw( $raw, array( 'http', 'https' ) );

		return $url ? $url : '';
	}


	/**
	 * A form key for an Airtable field name.
	 *
	 * Airtable names contain spaces, slashes and colons, none of which belong in a form key — and
	 * `Company ` ends in a space, which would be lost in transit and take the field with it.
	 *
	 * @param string $name Airtable field name.
	 * @return string
	 */
	public static function key( $name ) {
		return 'f' . substr( md5( (string) $name ), 0, 12 );
	}

	/**
	 * Back to the student page with a message.
	 *
	 * @param string $status Outcome flag.
	 */
	private static function bounce( $status ) {
		WPCPM_Flash::set( 'report', $status );

		$page = WPCPM_Students_Dashboard::page_url();

		wp_safe_redirect( ( '' !== $page ? $page : home_url( '/' ) ) . '#wpcpm-report-form' );
		exit;
	}

	/**
	 * The message for an outcome flag.
	 *
	 * @param string $status Outcome flag.
	 * @return array{0:string,1:string}|array
	 */
	public static function message( $status ) {
		$messages = array(
			'report-saved'     => array( 'success', __( 'Your report form is saved, and your mentor can see it.', 'wpcredits-program-manager' ) ),
			'report-nothing'   => array( 'error', __( 'Nothing was submitted, so nothing changed.', 'wpcredits-program-manager' ) ),
			'report-no-record' => array( 'error', __( 'Your record could not be found in the program data, so there is nothing to save to.', 'wpcredits-program-manager' ) ),
			'report-refused'   => array( 'error', __( 'The program records refused the change, so nothing was saved. Please try again.', 'wpcredits-program-manager' ) ),
			'report-partly'    => array( 'error', __( 'Saved, except for one or more numbers that could not be read. Check the grades and hours: they take digits, and a grade is between 0 and 100.', 'wpcredits-program-manager' ) ),
			'report-rejected'  => array( 'error', __( 'Nothing was saved: the numbers could not be read. Grades and hours take digits, and a grade is between 0 and 100.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
	}

	/**
	 * The outcome flag on this request, if any.
	 *
	 * @return string
	 */
	public static function status() {
		return sanitize_key( (string) WPCPM_Flash::take( 'report' ) );
	}
}
