<?php
/**
 * The report form a student fills in on their own Student Report Card.
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
 * from those views because **Airtable exposes no way to read a view's visible fields** - the
 * metadata API returns a view's id, name and type, and asking for records with `view=` returned the
 * same forty-two fields for both. So the lists have to be maintained by hand, and `bin/test-report-form.php`
 * pins them so a silent drift shows up as a failure rather than as a missing field on somebody's card.
 *
 * The Designer Track's set has no view behind it at all: it is the Learn course, module by module
 * and lesson by lesson, as the design spec of 7 September 2026 section 4 writes it out. It brings
 * the form's two newest controls with it, a single select and a screenshot.
 *
 * **A screenshot is a public file, and that is the decision, not an oversight.** The picture is
 * stored in the Media Library authored by the student, and Airtable is sent its URL rather than its
 * bytes: an attachment column is written with an address the base fetches for itself, which is how
 * `WPCPM_Sponsor_Logo` writes a logo and the only way an attachment column can be written at all.
 * The copies Airtable hands back are signed and expire within hours, so the card shows this site's
 * own file and never the base's; when the base holds a file the site has no copy of, the control
 * says how many and offers to replace them (design spec section 5, and assumption 6.3).
 *
 * **The file is public; the Media Library record is not.** Assumption 6.3 makes the picture's
 * address public because Airtable fetches it, and says nothing about the row behind it. An
 * `inherit` attachment with no parent reads as published, so the unauthenticated `wp/v2/media`
 * listing would name every screenshot together with its author, which is a student. The
 * attachment is stored `private` instead: the listing drops it, the attachment page is a 404 for
 * a stranger and oEmbed answers nothing, while the file's own address goes on being served.
 *
 * **Grades and hours are the student's to type.** That looked wrong and was queried: they are graded
 * elsewhere and copy the score across, so this form is a transcription rather than an assessment.
 * Nothing here is computed from anything, and nothing here decides whether they pass.
 *
 * **The current values are read live from Airtable, not from the synced row.** The sync carries a
 * dozen fields for the cards; these twenty-two are not among them, and adding them would mean a page
 * that shows "Not set" for everything until the next sync runs - the trap that hid *Field of study*
 * on every student card for two days. A short transient keeps the page quick, and saving clears it,
 * so a student always sees what they just typed.
 */
class WPCPM_Student_Report_Form {

	/**
	 * The Developer Track's alumni-programme answers, which an institution's card never shows.
	 *
	 * Named here rather than by group, because they share the `project` group with the
	 * student's contribution links, which the school does see.
	 */
	const ALUMNI_FIELDS = array(
		'Contributing beyond WP Credits',
		'Alumni program: personal email',
		'Alumni program: mentoring opt-in',
	);

	const ACTION_SAVE = 'wpcpm_student_report_save';

	/** Taking one screenshot back off the record. */
	const ACTION_REMOVE_IMAGE = 'wpcpm_student_report_image_remove';

	/**
	 * The site's copy of each screenshot: Airtable field name => attachment ID, per student.
	 *
	 * Hidden meta (the leading underscore), because it is the plugin's bookkeeping and not
	 * something a profile editor should offer to change. `uninstall.php` deletes it.
	 */
	const META_IMAGES = '_wpcpm_report_images';

	/** The one file input on the form; its keys are `key()` of each screenshot column. */
	const FILES_KEY = 'report_image';

	/** Ceiling key prefix. The rest is the student's user ID. */
	const CEILING_IMAGES = 'report-images:';

	/** How many screenshots one student may upload in a day. */
	const IMAGES_PER_DAY = 20;

	/**
	 * The screenshot size ceiling, in kilobytes.
	 *
	 * Its own rule rather than the `logo_max_kb` setting: a full-page site screenshot passes
	 * the sponsors' 1024 KB logo default more often than it fails it, and that setting is
	 * named, and sized, for a logo.
	 */
	const IMAGE_MAX_KB = 4096;

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
		add_action( 'admin_post_' . self::ACTION_REMOVE_IMAGE, array( __CLASS__, 'handle_image_remove' ) );
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
	 * trailing space in the base - the same trap as `Tutor ` on the Students table, and dropping it
	 * makes the write silently do nothing.
	 *
	 * `step` mirrors the column's own precision: the grades allow two decimals, hours and the three
	 * course marks are whole numbers.
	 *
	 * @param string $track Track key from `WPCPM_Program::track()`. Any key the sets below do not
	 *                      name, including the empty string a finished student has, gets the
	 *                      150-hour form - the one most of them filled in.
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
		// meant the personal website was editable from two controls writing one Airtable column -
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
				'subgroup' => __( 'Enter your final grade, 0 to 100', 'wpcredits-program-manager' ),
			) + $grade,
			'How decisions are made in the WordPress project - final grade' => array( 'label' => __( 'How decisions are made in the WordPress project', 'wpcredits-program-manager' ) ) + $grade,
		);

		// Conflict resolution is asked on both courses. It lived in `$fifty_grades` alone because the
		// 50-hour form was built first - the long course asks it too, between the voice course and
		// the three user levels, which is the order its own form uses.
		$conflict = array(
			'Basic principles of conflict resolution - final grade' => array( 'label' => __( 'Basic principles of conflict resolution', 'wpcredits-program-manager' ) ) + $grade,
		);

		// The two grades the long course and the Designer Track share beyond `$common_grades`,
		// in the order Learn lists them on both. Named once rather than written out on each
		// track, so a relabelled course cannot end up saying two things.
		$voice_grades = array(
			'Community meeting etiquette - final grade'    => array( 'label' => __( 'Community meeting etiquette', 'wpcredits-program-manager' ) ) + $grade,
			'Writing in the WordPress voice - final grade' => array( 'label' => __( 'Writing in the WordPress voice', 'wpcredits-program-manager' ) ) + $grade,
		);

		$sensei_grades = $voice_grades + $conflict + array(
			'Beginner WordPress User - final grade'     => array(
				'label' => __( 'Beginner WordPress User', 'wpcredits-program-manager' ),
				// The condition on the three user-level marks, as a heading over them (`lead`):
				// it was a `note` under the last of them until 1.94.3, where it read as an orphan.
				'lead'  => __( 'Complete one of the following courses', 'wpcredits-program-manager' ),
			) + $grade,
			'Intermediate WordPress User - final grade' => array( 'label' => __( 'Intermediate WordPress User', 'wpcredits-program-manager' ) ) + $grade,
			'Advance WordPress User - final grade'      => array( 'label' => __( 'Advanced WordPress User', 'wpcredits-program-manager' ) ) + $grade,
		);

		// Named on the form, because a mark for a course nobody had to take should not look like a
		// missing answer. The lead-in is printed above the first field carrying it.
		$sensei_courses = array(
			'Beginner WordPress Developer' => array( 'lead' => __( 'Optional courses', 'wpcredits-program-manager' ) )
				+ $mark + array( 'label' => __( 'Beginner WordPress Developer', 'wpcredits-program-manager' ) ),
			'Intermediate Theme Developer' => array( 'label' => __( 'Intermediate Theme Developer', 'wpcredits-program-manager' ) ) + $mark,
			'Beginner WordPress Designer'  => array( 'label' => __( 'Beginner WordPress Designer', 'wpcredits-program-manager' ) ) + $mark,
		);

		$fifty_grades = $conflict;

		// Developer track only. Long text in the base, so long text here - these are lists a
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
				// Lesson 3 of the course is "Practical: Patch Testing", and the heading is the
				// half of that name the field's own label does not already say.
				'subgroup' => __( 'Practical', 'wpcredits-program-manager' ),
				'label'    => __( 'Patch testing: your Trac ticket comments', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'group'    => 'project',
				'help'     => __( 'Links to the tickets you commented on, one per line.', 'wpcredits-program-manager' ),
			),
		);

		$dev_project = array(
			'Optional: Additional Contribution Project Summary' => array(
				'label' => __( 'A second contribution project, if you had one', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
				// It is inserted into the middle of the team/project pair, so it has to belong to
				// that pair's stacked column. A field without the row *ends* the pair, and the
				// questions after it open a second one with an empty right half - which is what
				// scattered the Project section when this was first added.
				'row'   => 'project',
				'stack' => true,
				'help'  => __( 'Optional. Leave empty if you worked on one project.', 'wpcredits-program-manager' ),
			),
		);

		// In *Project*, between the first-contribution post and the halfway one, which is where the
		// base's own dev-track view puts them. They read as end-of-programme questions and were in
		// Wrap-up until 1.63.0 - but where a question is asked is the program's decision, not an
		// inference from what it sounds like, and the view is where that decision is recorded.
		$dev_alumni = array(
			'Contributing beyond WP Credits'   => array(
				'label' => __( 'How you plan to keep contributing after the program', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Alumni program: personal email'   => array(
				'label' => __( 'A personal email address for the alumni program', 'wpcredits-program-manager' ),
				'type'  => 'email',
				'group' => 'project',
				'help'  => __( 'Somewhere that still reaches you once your student address stops working.', 'wpcredits-program-manager' ),
			),
			// The label says what is being agreed to. Repeating the column name here would ask for
			// consent without stating what for.
			'Alumni program: mentoring opt-in' => array(
				'label' => __( 'Yes, I am happy to be contacted about mentoring future WordPress Credits students.', 'wpcredits-program-manager' ),
				'type'  => 'checkbox',
				'group' => 'project',
			),
		);

		// `Contribution Project Summary` is the column's name in the base. It was
		// `Contribution Project Description` here until 1.61.0 - a name matching no field, so the
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
		// column - the profile no longer offers it, so there is still only one place to answer.
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

		// Designer Track only.
		//
		// **The Beginner WordPress Designer course is required here and optional on the long
		// course**, so it is its own lesson with the Required mark rather than one of the three
		// marks under "Optional courses". The mark is a label, not a `required` attribute on the
		// box: a student saves this form a dozen times while the term runs, and a browser
		// refusing to submit until every required answer is filled in would stop them saving
		// anything at all until the course is graded.
		$design_course = array(
			'Beginner WordPress Designer' => array(
				'label'    => __( 'Beginner WordPress Designer', 'wpcredits-program-manager' ),
				'lead'     => __( 'Complete the Beginner WordPress Designer course', 'wpcredits-program-manager' ),
				'required' => true,
			) + $mark,
		);

		// The lesson is "Create your portfolio", so the two questions are about a portfolio. The
		// keys stay the columns the base has: one site, one column, whatever a course calls it.
		$design_portfolio = array(
			'Personal Website URL' => array(
				'label'    => __( 'Your portfolio site URL', 'wpcredits-program-manager' ),
				'subgroup' => __( 'Create your portfolio', 'wpcredits-program-manager' ),
				'row'      => 'website',
			) + $in( $project['Personal Website URL'], 'onboarding' ),
			'Post Reflection: Building Your Personal Website' => array(
				'label' => __( 'Link to the post "Reflection: Building Your Portfolio"', 'wpcredits-program-manager' ),
				'row'   => 'website',
			) + $in( $posts['Post Reflection: Building Your Personal Website'], 'onboarding' ),
		);

		// The eight practical lessons of the Project module, each headed by the lesson's name as
		// Learn writes it and holding the questions that lesson asks.
		//
		// **The keys are the base's column names and three of them look like slips.** The base
		// shortens the library lesson to `Duplicate & Explore WP Design Library` and ends two of
		// its columns in lower case, and `Site’s` carries the typographic apostrophe (U+2019)
		// where Learn's lesson title has a plain one. A write has to name the column exactly, so
		// the keys are copied and the labels are what is written for the student.
		$design_practicals = array(
			'Practical: Duplicate & Explore WP Design Library - Reflection' => array(
				'lead'  => __( 'Practical: Duplicate and Explore the WordPress Design Library', 'wpcredits-program-manager' ),
				'label' => __( 'Your reflection', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Duplicate & Explore WP Design Library - link' => array(
				'label' => __( 'A link to your copy of the library', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'project',
			),
			'Practical: Duplicate & Explore WP Design Library - image' => array(
				'label' => __( 'A screenshot of your copy', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Local WordPress Environment for Design Testing - Tool used' => array(
				'lead'    => __( 'Practical: Set Up a Local WordPress Environment for Design Testing', 'wpcredits-program-manager' ),
				'label'   => __( 'The tool you used', 'wpcredits-program-manager' ),
				'type'    => 'select',
				'group'   => 'project',
				// The three choices the column has, spelled as the base spells them - `MAAMP`
				// included. Nothing sends `typecast`, so a fourth name is a 422 for the record.
				'options' => array( 'WordPress Studio', 'MAAMP', 'DevKinsta' ),
			),
			'Practical: Local WordPress Environment for Design Testing - Screenshot' => array(
				'label' => __( 'A screenshot of your local site', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Change Your Site’s Global Styles - Notes' => array(
				'lead'  => __( 'Practical: Change Your Site\'s Global Styles', 'wpcredits-program-manager' ),
				'label' => __( 'Your notes', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Change Your Site’s Global Styles - Before Screenshot' => array(
				'label' => __( 'A screenshot before your changes', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Change Your Site’s Global Styles - After Screenshot' => array(
				'label' => __( 'A screenshot after your changes', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Style Book - Notes'      => array(
				'lead'  => __( 'Practical: Customize with the Style Book', 'wpcredits-program-manager' ),
				'label' => __( 'Your notes', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Style Book - Screenshot' => array(
				'label' => __( 'A screenshot of your Style Book', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Landing Page with Layout Blocks - Note' => array(
				'lead'  => __( 'Practical: Compose a Landing Page with Layout Blocks', 'wpcredits-program-manager' ),
				'label' => __( 'Your note', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Landing Page with Layout Blocks - Screenshot' => array(
				'label' => __( 'A screenshot of your landing page', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Apply Custom CSS in the Site Editor - Notes' => array(
				'lead'  => __( 'Practical: Apply Custom CSS in the Site Editor', 'wpcredits-program-manager' ),
				'label' => __( 'Your notes', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Apply Custom CSS in the Site Editor - CSS' => array(
				'label' => __( 'The CSS you added', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
				// Code, so a proportional font and a spell checker underlining every property
				// are both in the way. `render_field()` reads this and nothing else does.
				'mono'  => true,
				'help'  => __( 'Paste the rules exactly as you wrote them.', 'wpcredits-program-manager' ),
			),
			'Practical: Apply Custom CSS in the Site Editor - Screenshot' => array(
				'label' => __( 'A screenshot of the result', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Submit a Custom Block Pattern - Link' => array(
				'lead'  => __( 'Practical: Create and Submit a Custom Block Pattern', 'wpcredits-program-manager' ),
				'label' => __( 'A link to your pattern', 'wpcredits-program-manager' ),
				'type'  => 'url',
				'group' => 'project',
			),
			'Practical: Submit a Custom Block Pattern - Screenshot' => array(
				'label' => __( 'A screenshot of your pattern', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Test Your Site for Accessibility - Part 1 - Note' => array(
				'lead'  => __( 'Practical: Test Your Site for Accessibility', 'wpcredits-program-manager' ),
				'label' => __( 'Your note on part 1', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Test Your Site for Accessibility - Part 1 - Screenshot' => array(
				'label' => __( 'A screenshot from part 1', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
			'Practical: Test Your Site for Accessibility - Part 2 - Note' => array(
				'label' => __( 'Your note on part 2', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'group' => 'project',
			),
			'Practical: Test Your Site for Accessibility - Part 2 - Screenshot' => array(
				'label' => __( 'A screenshot from part 2', 'wpcredits-program-manager' ),
				'type'  => 'image',
				'group' => 'project',
			),
		);

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
		} elseif ( 'design' === $track ) {
			// **Written out rather than inserted into the long course's set.** The Developer
			// Track is the 150-hour form plus seven fields, so it is expressed as insertions;
			// this one relabels the portfolio pair, moves the project questions under the team
			// list and adds twenty-one questions in eight lessons, which as a list of edits would
			// be longer than the set and impossible to read against the course.
			//
			// The order is the Learn course's, module by module and lesson by lesson (design
			// spec of 7 September 2026, section 4), with the product owner's additions of
			// 8 September 2026: every course grade the base holds, the project questions
			// directly under the team, the second project, and the alumni program as its own
			// section.
			//
			// **Every course grade, the required designer course first.** Learn's Onboarding
			// module names only the designer course for this track, but the program asks
			// designers for the user-level grades and the two developer marks as well, so they
			// follow it in the long course's own shape: the three user levels under "Complete one
			// of the following courses", the two developer courses under "Optional courses". The
			// designer mark is not repeated among the optional ones.
			$user_levels    = array_diff_key( $sensei_grades, $voice_grades, $conflict );
			$design_options = array_diff_key( $sensei_courses, $design_course );

			$fields = $hours + $contact + $common_grades + $voice_grades + $conflict + $design_course + $user_levels + $design_options + $design_portfolio;

			// The team list opens Project as it does on the long course, with the project
			// questions directly under it and the practical lessons after them, but not as half
			// of a pair: the first lesson heading below would close the pair's grid and leave an
			// empty column beside the list.
			$design_team = $teams;
			unset( $design_team['Main Contribution Team']['row'] );

			// The second project is the Developer Track's question without that form's pairing,
			// so it stands full width under the first.
			$design_second = $dev_project['Optional: Additional Contribution Project Summary'];
			unset( $design_second['row'], $design_second['stack'] );

			// The alumni program is its own lesson on Learn, so its two questions stand under
			// that lesson's heading between the meetings question and the event link, and the
			// event link carries its own lesson heading so the section reads as closed on both
			// sides.
			$design_alumni = array_intersect_key(
				$dev_alumni,
				array(
					'Alumni program: personal email'   => true,
					'Alumni program: mentoring opt-in' => true,
				)
			);
			$design_alumni['Alumni program: personal email']['lead'] = __( 'Alumni Program: Connect with the community and plan your contribution beyond WP Credits', 'wpcredits-program-manager' );

			$fields += $design_team + array(
				'Contribution Project Summary'                      => array(
					'lead' => __( 'Define and begin developing your contribution project', 'wpcredits-program-manager' ),
				) + $in( $project['Contribution Project Summary'], 'project' ),
				'Optional: Additional Contribution Project Summary' => $design_second,
			) + $design_practicals + array(
				'Post Reflection: Choosing Your Team and Project' => array( 'lead' => __( 'Your reflection posts', 'wpcredits-program-manager' ) )
					+ $in( $posts['Post Reflection: Choosing Your Team and Project'], 'project' ),
				'Post Reflection: Your First Contribution' => $in( $posts['Post Reflection: Your First Contribution'], 'project' ),
				'Post Reflection: Halfway Check-In'        => $in( $posts['Post Reflection: Halfway Check-In'], 'project' ),
				'Slack/GitHub/Blog WordPress Community meetings/discussions' => $in( $participation['Slack/GitHub/Blog WordPress Community meetings/discussions'], 'project' ),
			) + $design_alumni + array(
				'WP event participation URL' => array(
					'label' => __( 'Link to a WordPress event you have participated in (online or in person)', 'wpcredits-program-manager' ),
					'lead'  => __( 'Participate at a WordPress Event (online or in person)', 'wpcredits-program-manager' ),
					'type'  => 'url',
					'group' => 'project',
				),
				'Closing post URL'           => $in( $posts['Closing post URL'], 'wrapup' ),
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
				'Post Reflection: Your First Contribution' => array( 'lead' => __( 'Your reflection posts', 'wpcredits-program-manager' ) )
					+ $in( $posts['Post Reflection: Your First Contribution'], 'project' ),
				'Post Reflection: Halfway Check-In'        => $in( $posts['Post Reflection: Halfway Check-In'], 'project' ),
				'WP event participation URL'               => array(
					'label' => __( 'Link to a WordPress event you have participated in (online or in person)', 'wpcredits-program-manager' ),
					'type'  => 'url',
					'group' => 'project',
				),
				'Closing post URL'                         => $in( $posts['Closing post URL'], 'wrapup' ),
			);

			// The developer track is the 150-hour form plus seven fields. Written as insertions into
			// that set rather than as a third copy, because a copy would drift the moment either
			// changed.
			//
			// **The anchors follow the Learn course, not the Airtable view.** The view lists the
			// fields in the order the columns happen to sit in the table; the course is the order
			// the student works through, and that is what a form should follow. The two disagree
			// twice - patch testing is lesson 3 and belongs with the project rather than among the
			// course grades, and the alumni programme is lesson 7, ahead of the first-contribution
			// reflection at lesson 9 rather than after it.
			if ( 'dev' === $track ) {
				$fields = self::insert_after( $fields, 'Advance WordPress User - final grade', $dev_basics );
				$fields = self::insert_after( $fields, 'Post Reflection: Building Your Personal Website', $dev_patch );
				$fields = self::insert_after( $fields, 'Contribution Project Summary', $dev_project );

				// On this course the meetings and discussions are asked inside the Alumni Program
				// lesson, not with the project questions - so on this track alone the field moves
				// out of the column beside the team list and heads that run instead. It carries the
				// heading because it is the lesson's first question.
				//
				// Moved rather than copied: the same field left in both places would be one Airtable
				// column with two boxes writing to it, which is the bug the contribution teams had.
				$meetings = 'Slack/GitHub/Blog WordPress Community meetings/discussions';
				$moved    = isset( $fields[ $meetings ] ) ? $fields[ $meetings ] : array();

				unset( $fields[ $meetings ] );

				// It is not one of the stacked questions any more, and a field carrying a heading
				// could not be: `render_body()` closes the open row before printing one.
				unset( $moved['row'], $moved['stack'] );

				$moved['subgroup'] = __( 'Alumni Program', 'wpcredits-program-manager' );

				$fields = self::insert_after(
					$fields,
					'Post Reflection: Choosing Your Team and Project',
					array( $meetings => $moved ) + $dev_alumni
				);

				// On this track the pair follows the Practical lesson rather than sitting under
				// the section legend, so it needs a heading of its own; the other tracks do not.
				$fields['Main Contribution Team']['lead'] = __( 'Your contribution team and project', 'wpcredits-program-manager' );
			}
		}

		/**
		 * Filter the report form's fields for one track.
		 *
		 * @param array  $fields Airtable field name => spec.
		 * @param string $track  Track key: `150h`, `50h`, `dev` or `design`.
		 */
		return (array) apply_filters( 'wpcpm_report_form_fields', $fields, $track );
	}


	/**
	 * Every Airtable column on any track that holds screenshots.
	 *
	 * Read by `WPCPM_Students_Sync`, which asks Airtable for these columns by name and keeps
	 * the number of files each one holds in the student's program row. **The count is all it
	 * keeps.** Airtable's own attachment URLs expire within hours, so a card that had one would
	 * show a broken picture by the afternoon; the card shows this site's copy instead, and falls
	 * back to saying how many files the base holds.
	 *
	 * Derived from the field sets rather than listed again here, so a screenshot question added
	 * to a track is a column the sync asks for from the same release.
	 *
	 * @return string[] Airtable field names, in the order the form asks them.
	 */
	public static function image_columns() {
		// Memoized: the students sync asks for this once per page and once per record, and
		// building a field set per track for every student to answer the same question is a
		// bill nobody is paying attention to. A filter on `fields()` is registered long before
		// any sync tick, so nothing changes between the calls of one request.
		static $columns = null;

		if ( null !== $columns ) {
			return $columns;
		}

		$columns = array();

		// The tracks off the program map rather than written out here, the way
		// `WPCPM_Semester_Report::link_labels()` and the Administrator Dashboard's tile strip
		// read them: a fifth track is then one entry in `WPCPM_Program` and nothing else. A
		// list of its own would go stale silently - the sync asks Airtable for these columns by
		// name, so a track missing from it is a track whose every card says "No screenshot yet"
		// for ever, with nothing failing anywhere to say why.
		$tracks = array();

		foreach ( array_keys( WPCPM_Program::labels() ) as $status ) {
			$track = WPCPM_Program::track( $status );

			// A status on no track - Paused, Graduate and the rest. `fields()` answers the
			// 150-hour set for anything it does not know, and that set is already in this
			// loop under the 150-hour status itself.
			if ( '' !== $track ) {
				$tracks[ $track ] = true;
			}
		}

		foreach ( array_keys( $tracks ) as $track ) {
			foreach ( self::fields( $track ) as $name => $spec ) {
				if ( isset( $spec['type'] ) && 'image' === $spec['type'] && ! in_array( $name, $columns, true ) ) {
					$columns[] = $name;
				}
			}
		}

		return $columns;
	}

	/**
	 * One student's screenshots: Airtable field name => attachment ID.
	 *
	 * @param int $student_id Student user ID.
	 * @return array<string, int>
	 */
	public static function images( $student_id ) {
		$stored = get_user_meta( (int) $student_id, self::META_IMAGES, true );
		$out    = array();

		foreach ( is_array( $stored ) ? $stored : array() as $name => $id ) {
			if ( is_string( $name ) && (int) $id > 0 ) {
				$out[ $name ] = (int) $id;
			}
		}

		return $out;
	}

	/**
	 * Put fields straight after a named one, keeping every other key where it was.
	 *
	 * Order inside a group is the array's own order - `render_body()` groups with `array_filter()`,
	 * which preserves it - so where a field sits in this array is where a student sees it.
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
	 * this replaces - Total hours, Onboarding, Project, Wrap-up - so a student who has filled that in
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
	 * - which a student would read as their work having been lost.
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
	 * The student themselves, or a program manager - the same rule the profile fields use. A mentor
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

		// Closed by default: it is a long form somebody opens deliberately, and a Student Report
		// Card that begins with twenty boxes buries everything under it. **Open when there is
		// something to say** - a "Saved" or a rejected grade behind a closed disclosure is a
		// message nobody reads, which is the same reasoning that reopens a student's card after
		// a note is saved.
		printf(
			'<details class="wpcpm-report__disclosure"%s>',
			empty( $message ) ? '' : ' open'
		);
		// No field count beside it. It was there to say how much was behind the disclosure, and
		// what it actually said was "twenty-four things to do" - which is the opposite of the
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
	 * being read, and an editable copy of it - with a Save button - is an invitation to answer a
	 * question on the student's behalf. The capability still governs where a manager does edit, on
	 * the student's own card.
	 *
	 * `$audience` is who is reading, and it decides what is drawn at all. A student, their
	 * mentor and a program manager see the whole card. An institution sees the card with two
	 * things left out: every field of type `email`, and the three alumni-programme answers on
	 * the Developer Track (a personal address the student gave so the program can reach them
	 * after their student address dies, their plans, and the mentoring opt-in). Those are
	 * between the student and the program; the institution's card promises the school sees no
	 * address of the student's, and design spec 7.5 says the same. Filtered here, before any
	 * group is drawn, so no branch below can print what the audience was not to see.
	 *
	 * @param WP_User $student   The student whose report this is.
	 * @param array   $program   Their cached program row, for the track.
	 * @param bool    $read_only Force a record rather than a form, whatever the viewer may do.
	 * @param string  $audience  `own`, `mentor`, `manager` or `institution`.
	 */
	private static function render_body( WP_User $student, array $program, $read_only = false, $audience = 'own' ) {
		$track  = WPCPM_Program::track( isset( $program['program'] ) ? $program['program'] : '' );
		$fields = self::fields( $track );

		if ( 'institution' === $audience ) {
			$fields = self::for_institution( $fields );
		}
		$record = WPCPM_Mentor_Calls::student_record( $student->ID );
		$values = self::values( $record );
		$can    = ! $read_only && self::user_can_edit( $student->ID );

		// What the screenshot control needs and no other control does: whose card this is, the
		// site's copy of each picture, and how many files the base holds for a question the site
		// has no copy of. Built once here rather than looked up per field, and passed rather
		// than read from a global, so `render_field()` stays a function of its arguments.
		$context = array(
			'student' => (int) $student->ID,
			'images'  => self::images( $student->ID ),
			'files'   => isset( $program['report_files'] ) && is_array( $program['report_files'] ) ? $program['report_files'] : array(),
		);

		// A file input needs the form to post as `multipart/form-data`, and only the tracks with
		// screenshot questions have one. Asked of the field set rather than of the track, so a
		// screenshot added to another course brings its own encoding with it.
		$has_files = false;

		foreach ( $fields as $spec ) {
			if ( isset( $spec['type'] ) && 'image' === $spec['type'] ) {
				$has_files = true;
				break;
			}
		}

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
						__( 'Your report form could not be loaded just now: %s Nothing has been lost - try reloading the page.', 'wpcredits-program-manager' ),
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
		// handler checks the capability again, so a form here would be harmless - but it would still
		// be a form, and the reason to leave it out is what it says: a reader of somebody else's
		// report is looking at a record, not at something addressed to them. It also means there is
		// no nonce, no student ID and no submit path in markup nobody may submit.
		if ( $can ) {
			printf(
				'<form class="wpcpm-report" method="post" action="%1$s"%3$s data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Saving…', 'wpcredits-program-manager' ),
				$has_files ? ' enctype="multipart/form-data"' : ''
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
		// `render_hours()` - one field, posting to this same handler.
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

					// A heading element, not a styled paragraph: a screen reader moving by headings
					// lands on the lesson, as a sighted reader's eye does (1.94.3).
					printf(
						'<h4 class="wpcpm-report__sub">%s</h4>',
						esc_html( $subgroup )
					);
				}

				// A run's opening sentence and a lesson's name are the same kind of heading: both
				// introduce what follows, and printing them as two different classes read as two
				// rules rather than one. The owner asked for a single treatment - capitals, the
				// rule above - for both, so this prints the same class `subgroup` does above (the
				// consistency pass of 6 September 2026; this heading had a class of its own from
				// 1.94.3 until here).
				if ( ! empty( $spec['lead'] ) ) {
					if ( '' !== $row ) {
						if ( $stacked ) {
							echo '</div>';
							$stacked = false;
						}

						echo '</div>';
						$row = '';
					}

					printf( '<h4 class="wpcpm-report__sub">%s</h4>', esc_html( $spec['lead'] ) );
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

				self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : '', $can, $context );

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

		// The form every Remove button on the card posts through, printed here because a
		// `<form>` inside a `<form>` is markup a browser drops: the buttons name this one by
		// id, and each carries the field it removes as its own value. One form, one nonce,
		// however many screenshots the track asks for.
		//
		// Guarded once like the report form above it and like the sponsor logo's own Remove.
		// Remove is a delete, and a second press while the first is in flight sends a second
		// PATCH of a cell the first one already emptied.
		if ( $can && $has_files ) {
			printf(
				'<form class="wpcpm-report__remove" id="wpcpm-report-remove-%1$d" method="post" action="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
				(int) $student->ID,
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Removing', 'wpcredits-program-manager' )
			);

			wp_nonce_field( self::ACTION_REMOVE_IMAGE . '_' . (int) $student->ID );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_REMOVE_IMAGE ) );
			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );

			echo '</form>';
		}

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
	 * Who the current user is to this record's student, for `render_body()`.
	 *
	 * The same three grounds `rest_permission()` accepts, in the same order: a manager, the
	 * mentor whose synced list holds the record, and otherwise the institution whose claim
	 * let the request through. Never `own`: the REST route is not how a student reads their
	 * own card.
	 *
	 * @param string $record Students Reports record ID.
	 * @return string `manager`, `mentor` or `institution`.
	 */
	public static function audience_for( $record ) {
		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return 'manager';
		}

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( get_current_user_id() ) as $mentee ) {
			if ( isset( $mentee['record_id'] ) && (string) $mentee['record_id'] === (string) $record ) {
				return 'mentor';
			}
		}

		return 'institution';
	}

	/**
	 * The card's fields with everything an institution is not shown removed.
	 *
	 * Every field of type `email`, and the three alumni-programme fields by name: they are
	 * the student's arrangement with the program for after the course, not part of what a
	 * school sent them to do. Public so the suite can hold the list to the promise.
	 *
	 * @param array $fields Field specs, keyed by Airtable column name.
	 * @return array The same array with those fields removed.
	 */
	public static function for_institution( array $fields ) {
		$out = array();

		foreach ( $fields as $name => $spec ) {
			if ( isset( $spec['type'] ) && 'email' === $spec['type'] ) {
				continue;
			}

			if ( in_array( $name, self::ALUMNI_FIELDS, true ) ) {
				continue;
			}

			$out[ $name ] = $spec;
		}

		return $out;
	}

	/**
	 * Who may read a report over the route.
	 *
	 * A program manager may read any; a mentor may read the students assigned to them and nobody
	 * else. **The mentee list is the authority**, not the request: it is the same list their page
	 * is drawn from, so a record they were never given cannot be asked for by editing a URL.
	 *
	 * **The third audience is an institution**, since the Institutions module: a member reading
	 * the card of a student on their own roster, from the detail view, which ships the same
	 * disclosure the mentor's card does and fetches it from this route. That branch owns none of
	 * the decision - `WPCPM_Institution_Roster::claim()` makes it, against the live Students row
	 * and its `Educational Institutions` link, because a record ID arriving in a URL is not
	 * evidence of anything. It is tried last of the three: a manager and a mentor are answered
	 * from what the site already holds, and `claim()` may spend an Airtable request.
	 *
	 * **A refusal is `false` and never the `WP_Error`.** One route, three audiences: a mentor who
	 * is not this student's mentor already gets core's one answer, and a member who gets a
	 * different one - with a code naming this module - could tell from the outside which
	 * question the site asked about them. The fence's one refusal message belongs where the
	 * fence puts it, in what `claim()` returns to the paths that render it. Every `WP_Error` is
	 * a no, an unreadable Airtable included: a route that opened when the base was down would
	 * be a fence that fails open on the one day nothing can check it.
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

		// Guarded, not assumed: this route is a mentor's every working day, and it must not
		// depend on the Institutions module's files having been loaded to answer them.
		if ( class_exists( 'WPCPM_Institution_Roster' ) && class_exists( 'WPCPM_Institution_Policy' ) ) {
			$claim = WPCPM_Institution_Roster::claim( $record, WPCPM_Institution_Policy::ACT_VIEW_REPORT, 'report' );

			if ( ! is_wp_error( $claim ) ) {
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
		// the report their student wrote, and the answers are the student's to give. Who is
		// reading decides what is drawn: a manager or the student's own mentor sees the card
		// whole, and anybody else the permission callback let through is an institution.
		self::render_body( $student, $program, true, self::audience_for( $record ) );

		return new WP_REST_Response( array( 'html' => ob_get_clean() ), 200 );
	}

	/**
	 * The hours box, for *My course* rather than for the form.
	 *
	 * The one number a student updates most often, and the only one they update without having
	 * anything else to report - so it sits beside the course button rather than behind a
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
		// input and hint, which leaves the Save button an outsider beside all three - the label
		// above it, the hint below, and nothing lining up with anything. This is a box and a
		// button on one line, with the label over them and the hint under: three rows, one column,
		// left aligned.
		$spec  = $fields['Hours'];
		$value = isset( $values['Hours'] ) ? $values['Hours'] : '';
		$id    = 'wpcpm-report-' . self::key( 'Hours' );

		// Same id scheme as render_field(), so a screen reader hears this box described the same
		// way as every other control on the card (phase two of the type review, 1.94.6).
		$hint_id = $id . '-hint';

		printf(
			'<label class="wpcpm-hours__label" for="%1$s">%2$s</label>',
			esc_attr( $id ),
			esc_html( $spec['label'] )
		);

		echo '<span class="wpcpm-hours__entry">';

		printf(
			'<input type="number" id="%1$s" name="report[%2$s]" value="%3$s" step="%4$s" min="%5$s" max="%6$s" inputmode="numeric"%7$s%8$s />',
			esc_attr( $id ),
			esc_attr( self::key( 'Hours' ) ),
			esc_attr( is_scalar( $value ) ? (string) $value : '' ),
			esc_attr( isset( $spec['step'] ) ? $spec['step'] : 'any' ),
			esc_attr( isset( $spec['min'] ) ? (string) $spec['min'] : '' ),
			esc_attr( isset( $spec['max'] ) ? (string) $spec['max'] : '' ),
			$can ? '' : ' disabled="disabled"',
			empty( $spec['help'] ) ? '' : ' aria-describedby="' . esc_attr( $hint_id ) . '"'
		);

		if ( $can ) {
			printf(
				'<button type="submit" class="wpcpm-button">%s</button>',
				esc_html__( 'Save hours', 'wpcredits-program-manager' )
			);
		}

		echo '</span>';

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s">%2$s</span>', esc_attr( $hint_id ), esc_html( $spec['help'] ) );
		}

		echo '</form>';
	}

	/**
	 * One field.
	 *
	 * @param string $name    Airtable field name.
	 * @param array  $spec    Field spec.
	 * @param mixed  $value   Its current value.
	 * @param bool   $can     Whether it may be edited.
	 * @param array  $context What the screenshot control needs: `student`, `images`, `files`.
	 */
	private static function render_field( $name, array $spec, $value, $can, array $context = array() ) {
		$key  = self::key( $name );
		$id   = 'wpcpm-report-' . $key;
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
		$dis  = $can ? '' : ' disabled="disabled"';

		// Said in the label, not enforced by the browser. A student saves this form again and
		// again while the term runs, and `required` on the box would refuse every save until the
		// course they have not finished yet is graded.
		$required = empty( $spec['required'] )
			? ''
			: ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>';

		// A hint is a description of its control, for a screen reader as for the eye: the span
		// gets an id and the control names it. One id scheme for the whole form, so a test can
		// pair every hint with its control (phase two of the type review, 1.94.6).
		$hint_id   = $id . '-hint';
		$described = empty( $spec['help'] ) ? '' : ' aria-describedby="' . esc_attr( $hint_id ) . '"';

		// A reader, not a writer: a mentor or a manager reading somebody else's card never fills
		// it in, and a disabled box said "something is wrong here" while lowering the contrast of
		// what it holds. The team list and the screenshot are the two exceptions: their own
		// renderers read `$can` and draw a reader the tiles and the picture, which a
		// label-and-value row cannot show at all - which is why they are excluded here rather
		// than inside `render_read_only()` itself.
		if ( ! $can && ! in_array( $type, array( 'team', 'image' ), true ) ) {
			self::render_read_only( $id, $spec, $value, $type );
			return;
		}

		// A `<div>` for the two controls that hold a block of their own - the checkbox list and
		// the screenshot - and a `<p>` for the rest. **A `<p>` cannot contain a `<fieldset>`**:
		// the parser closes the paragraph the moment one opens, so the list and the hint after it
		// became siblings of the field rather than its children - and, in a grid, separate items.
		// That is what scattered the team block across the row. The screenshot takes the same
		// wrapper for the block layout its own CSS gives it, a column of controls rather than a
		// line of text.
		//
		// A checkbox list also has no single control to point `for` at, so its name is a plain
		// span; the `<fieldset>` inside carries the accessible grouping instead.
		$wrapper = in_array( $type, array( 'team', 'image' ), true ) ? 'div' : 'p';

		// A checkbox reads as "[x] Yes, I agree to…", so the box comes first and the label after
		// it. Printing the label above would turn a consent question into a heading with an
		// unlabelled tick under it.
		if ( 'checkbox' === $type ) {
			// **The hidden zero is what makes unticking possible.** A cleared checkbox posts
			// nothing at all, and `handle_save()` skips any field the browser did not send - so
			// without this a student could tick the box once and never take it back. It is a
			// consent checkbox, so that is the one direction that must work.
			printf(
				'<p class="wpcpm-field wpcpm-field--checkbox"><input type="hidden" name="report[%2$s]" value="0" /><input type="checkbox" id="%1$s" name="report[%2$s]" value="1"%3$s%4$s%6$s /><label for="%1$s">%5$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( self::is_ticked( $value ), true, false ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_html( $spec['label'] ),
				$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
			);

			if ( ! empty( $spec['help'] ) ) {
				printf( '<span class="wpcpm-field__hint" id="%1$s">%2$s</span>', esc_attr( $hint_id ), esc_html( $spec['help'] ) );
			}

			echo '</p>';

			return;
		}

		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s">%3$s',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			'team' === $type
				? '<span class="wpcpm-field__label">' . esc_html( $spec['label'] ) . $required . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is built above from esc_html__() or the empty string.
				: sprintf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $spec['label'] ), $required ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- As above.
		);

		if ( 'team' === $type ) {
			self::render_teams( $key, $value, $can, $spec );
		} elseif ( 'image' === $type ) {
			self::render_image( $name, $key, $id, $spec, $can, $context );
		} elseif ( 'select' === $type ) {
			$chosen = is_scalar( $value ) ? (string) $value : '';

			printf(
				'<select id="%1$s" name="report[%2$s]"%3$s%4$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
			);

			// An empty first option, so a question nobody has answered yet does not read as
			// having been answered with whichever choice happens to be first. Choosing it back
			// clears the cell, which is the only way to unsay an answer in a select.
			printf( '<option value="">%s</option>', esc_html__( 'Choose one', 'wpcredits-program-manager' ) );

			foreach ( isset( $spec['options'] ) ? (array) $spec['options'] : array() as $option ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $option ),
					selected( $chosen, $option, false ),
					esc_html( $option )
				);
			}

			echo '</select>';
		} elseif ( 'number' === $type ) {
			printf(
				'<input type="number" id="%1$s" name="report[%2$s]" value="%3$s" step="%4$s" min="%5$s" max="%6$s" inputmode="decimal"%7$s%8$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				esc_attr( isset( $spec['step'] ) ? $spec['step'] : 'any' ),
				esc_attr( isset( $spec['min'] ) ? (string) $spec['min'] : '' ),
				esc_attr( isset( $spec['max'] ) ? (string) $spec['max'] : '' ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
			);
		} elseif ( 'textarea' === $type || 'richtext' === $type ) {
			printf(
				'<textarea id="%1$s" name="report[%2$s]" rows="%3$d" maxlength="%4$d"%5$s%7$s%8$s>%6$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				'richtext' === $type ? 8 : 4,
				(int) self::MAX_TEXT,
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_textarea( is_scalar( $value ) ? (string) $value : '' ),
				$described, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
				// The CSS answer is code: a proportional font misreads its indentation, and a
				// spell checker underlines every property name in it.
				empty( $spec['mono'] ) ? '' : ' class="wpcpm-report__code" spellcheck="false"'
			);
		} elseif ( 'email' === $type ) {
			printf(
				'<input type="email" id="%1$s" name="report[%2$s]" value="%3$s" inputmode="email" autocomplete="email"%4$s%5$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
			);
		} else {
			// `type="text"` even for the URLs, for the reason the profile editor gives: `type="url"`
			// refuses a scheme-less address, and Airtable's url columns are full of them - the
			// browser would block the save with a message a student cannot act on.
			// `WPCPM_Field_Value::clean_url()` adds the scheme instead.
			printf(
				'<input type="text" id="%1$s" name="report[%2$s]" value="%3$s" inputmode="url"%4$s%5$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_attr() or the empty string.
			);
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s">%2$s</span>', esc_attr( $hint_id ), esc_html( $spec['help'] ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}


	/**
	 * One screenshot question: what is on record, and the two things that can be done to it.
	 *
	 * **The picture shown is always this site's copy, never Airtable's.** The base holds its own
	 * copy of every screenshot, fetched from the Media Library URL the save wrote; the URLs
	 * Airtable hands back for those copies are signed and expire within hours, so a card that
	 * linked one would show a broken picture by the afternoon. When the site has no copy - a file
	 * uploaded in the base by hand, or an account restored without its uploads - the control says
	 * how many files the record holds and offers to replace them, which is everything a student
	 * needs to know and nothing that can rot.
	 *
	 * The count comes from the student's program row, where the students sync leaves it. Reading
	 * it from the live record instead would put the expiring URLs in the renderer's hands, which
	 * is the one place they must not be.
	 *
	 * @param string $name    Airtable field name.
	 * @param string $key     Form key.
	 * @param string $id      Control id.
	 * @param array  $spec    Field spec.
	 * @param bool   $can     Whether it may be changed.
	 * @param array  $context `student` (int), `images` and `files` (both Airtable field name => int).
	 */
	private static function render_image( $name, $key, $id, array $spec, $can, array $context ) {
		$student = isset( $context['student'] ) ? (int) $context['student'] : 0;
		$stored  = isset( $context['images'][ $name ] ) ? (int) $context['images'][ $name ] : 0;
		$url     = $stored > 0 ? wp_get_attachment_url( $stored ) : '';
		$url     = is_string( $url ) ? $url : '';
		$held    = isset( $context['files'][ $name ] ) ? (int) $context['files'][ $name ] : 0;

		echo '<span class="wpcpm-report__image">';

		if ( '' !== $url ) {
			$thumb = wp_get_attachment_image_url( $stored, 'medium' );

			printf(
				'<a class="wpcpm-report__image-link" href="%1$s" target="_blank" rel="noopener noreferrer"><img class="wpcpm-report__image-thumb" src="%2$s" alt="%3$s" /></a>',
				esc_url( $url ),
				esc_url( is_string( $thumb ) && '' !== $thumb ? $thumb : $url ),
				// The label says what the picture is; a screen reader reading the file name
				// instead would hear the upload's name, which says nothing.
				esc_attr( $spec['label'] )
			);
		} elseif ( $held > 0 ) {
			printf(
				'<span class="wpcpm-report__image-note">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: how many files the program records hold for this question. */
						_n( '%s file on record in Airtable', '%s files on record in Airtable', $held, 'wpcredits-program-manager' ),
						number_format_i18n( $held )
					)
				)
			);
		} else {
			printf(
				'<span class="wpcpm-report__image-note wpcpm-report__image-note--empty">%s</span>',
				esc_html__( 'No screenshot yet', 'wpcredits-program-manager' )
			);
		}

		if ( ! $can ) {
			echo '</span>';

			return;
		}

		echo '<span class="wpcpm-report__image-actions">';

		if ( '' !== $url ) {
			printf(
				'<span class="wpcpm-report__image-swap">%s</span>',
				esc_html__( 'Replace it by choosing a new file.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<input type="file" id="%1$s" name="%2$s[%3$s]" accept="image/png,image/jpeg,image/webp"%4$s />',
			esc_attr( $id ),
			esc_attr( self::FILES_KEY ),
			esc_attr( $key ),
			empty( $spec['help'] ) ? '' : ' aria-describedby="' . esc_attr( $id . '-hint' ) . '"'
		);

		// Only where this site holds the file. A cell whose only copy is in the base is the
		// program's record of something, and emptying it from here would be the site deciding
		// about a file it never had. Replace is offered instead, and the base's cell is
		// replaced with it.
		//
		// Read off the URL rather than off the map, because the two can disagree: a manager
		// deleting the attachment in wp-admin leaves the map naming a row that is gone, and the
		// question would then say "No screenshot yet" with a Remove button under it. There is
		// nothing there to remove.
		//
		// The button sits inside the report form, so its own form is a sibling printed after
		// it and named by id: a `<form>` inside a `<form>` is markup no browser keeps.
		if ( '' !== $url ) {
			printf(
				'<button type="submit" class="wpcpm-report__image-remove" form="wpcpm-report-remove-%1$d" name="field" value="%2$s">%3$s</button>',
				(int) $student,
				esc_attr( $key ),
				esc_html__( 'Remove', 'wpcredits-program-manager' )
			);
		}

		echo '</span></span>';
	}

	/**
	 * A field for a reader, not a writer: the label and the value as a row.
	 *
	 * A mentor or a manager reads the card and never fills it in, and a disabled box says
	 * "something is wrong here" while lowering the contrast of what it holds. The row is the
	 * shape the profile table already has (phase two of the type review, 1.94.6).
	 *
	 * @param string $id    Control id, kept as the row's id so links to a field still land.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Stored value.
	 * @param string $type  Field type.
	 */
	private static function render_read_only( $id, array $spec, $value, $type ) {
		$text = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( 'checkbox' === $type ) {
			$text = self::is_ticked( $value ) ? __( 'Yes', 'wpcredits-program-manager' ) : __( 'No', 'wpcredits-program-manager' );
		}

		if ( '' === $text ) {
			$html = '<span class="wpcpm-field__value wpcpm-field__value--empty">' . esc_html__( 'Not filled in', 'wpcredits-program-manager' ) . '</span>';
		} elseif ( 'url' === $type ) {
			// Airtable's url columns hold schemeless addresses ("example.org/me"), and `esc_url()`
			// alone leaves one relative - a link that stays inside this site instead of leaving it.
			$html = sprintf( '<span class="wpcpm-field__value"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></span>', esc_url( WPCPM_Field_Value::clean_url( $text ) ), esc_html( $text ) );
		} elseif ( 'richtext' === $type ) {
			// The one richtext field holds what the student typed into a textarea, not markup: its
			// paragraphs are newlines, so `wp_kses_post()` alone - which keeps the tags a rich value
			// might carry - would run a multi-paragraph final project report into one block.
			$html = '<span class="wpcpm-field__value">' . nl2br( wp_kses_post( $text ) ) . '</span>';
		} else {
			$html = '<span class="wpcpm-field__value">' . nl2br( esc_html( $text ) ) . '</span>';
		}

		printf(
			'<p class="wpcpm-field wpcpm-field--read wpcpm-field--%1$s" id="%2$s"><span class="wpcpm-field__label">%3$s</span>%4$s</p>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_html( $spec['label'] ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts.
		);
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

		// Same id scheme as a single control: the fieldset stands in for one, so its hint points
		// at it the same way (phase two of the type review, 1.94.6).
		printf(
			'<fieldset class="wpcpm-report__teams"%1$s><legend class="screen-reader-text">%2$s</legend>',
			empty( $spec['help'] ) ? '' : ' aria-describedby="' . esc_attr( 'wpcpm-report-' . $key . '-hint' ) . '"',
			esc_html( $spec['label'] )
		);

		foreach ( $teams as $record_id => $team_name ) {
			printf(
				// The team's own icon, the same one the student's card and the mentor's table
				// show for it, so a team is recognisable here by the mark rather than only by
				// reading the name. `label_icon()` escapes what it builds and is decorative -
				// `aria-hidden` - because the name beside it already says which team this is.
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
		// key it was not sent - so clearing the last team would silently do nothing. This empty
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
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0;

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

			// A screenshot never arrives here: it is a file, and `store_images()` below writes
			// its cell. Skipped rather than cleaned, because a posted string named after a file
			// input would otherwise be sent to an attachment column, and Airtable answers that
			// with a 422 for the whole record - losing the other thirty-nine answers with it.
			if ( isset( $spec['type'] ) && 'image' === $spec['type'] ) {
				continue;
			}

			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}

			list( $ok, $value ) = self::clean( $posted[ $key ], $spec );

			// **"Rejected" and "cleared" cannot be the same answer.** Airtable empties a number
			// column with `null`, so `null` is a legitimate value to send - and a first draft used
			// it for "could not be understood" as well, which would have made an unreadable grade
			// silently erase the stored one. Hence the pair: whether to write, and what.
			if ( $ok ) {
				$cells[ $name ] = $value;
			} else {
				$rejected[] = $spec['label'];
			}
		}

		// The screenshots, which arrive as files rather than as posted values. Their cells join
		// the rest, so one PATCH carries the whole save: a second request for the pictures would
		// be a save that half succeeded whenever the base was slow.
		$images = self::store_images( $student_id, $fields, $cells );

		if ( empty( $cells ) ) {
			// The unreadable answer first, for the reason given at the other bounce below.
			if ( '' !== $images && empty( $rejected ) ) {
				self::bounce( $images );
			}

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

		// Four of these answers are also shown on the cards, from a copy the sync leaves behind -
		// so without this a student who chose their team saw *Not set* on their own card until the
		// next sync, with the answer sitting in Airtable the whole time.
		WPCPM_Students_Sync::apply_report( $student_id, $cells );

		// Everything readable was saved; anything that was not is named rather than dropped in
		// silence, which is what makes a rejected grade findable instead of mysterious.
		//
		// **One flash, and an unreadable answer wins it.** A screenshot that could not be used
		// leaves the file the student picked visibly absent from the question they picked it
		// for, while a grade typed as "eighty" leaves nothing on the page to see at all - so
		// naming only the screenshot would drop the rejection in silence, the outcome the
		// `$rejected` pair exists to prevent. The screenshot refusal is said on its own
		// whenever it is the only thing that went wrong, which is nearly always.
		if ( '' !== $images && empty( $rejected ) ) {
			self::bounce( $images );
		}

		self::bounce( empty( $rejected ) ? 'report-saved' : 'report-partly' );
	}


	/**
	 * The screenshots this request carried, keyed by the form key of the column they answer.
	 *
	 * One file input per question, all named `report_image[<key>]`, so PHP hands them over as
	 * five parallel arrays rather than as one array per file. Pivoted here, once, and every
	 * member cast on the way out - which is the only sanitizing an upload admits. What makes the
	 * bytes safe is `WPCPM_Image_Upload`, not a filter on this array.
	 *
	 * @return array<string, array{error: int, size: int, tmp_name: string, name: string}>
	 */
	private static function uploaded_images() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- `handle_save()` verifies the nonce before it calls this.
		if ( ! isset( $_FILES[ self::FILES_KEY ] ) || ! is_array( $_FILES[ self::FILES_KEY ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above for the nonce; every member is cast or sanitized on the lines below.
		$raw   = wp_unslash( $_FILES[ self::FILES_KEY ] );
		$names = ( isset( $raw['name'] ) && is_array( $raw['name'] ) ) ? $raw['name'] : array();
		$out   = array();

		foreach ( array_keys( $names ) as $key ) {
			$key   = (string) $key;
			$error = isset( $raw['error'][ $key ] ) ? (int) $raw['error'][ $key ] : UPLOAD_ERR_NO_FILE;

			// A question the student left alone. Every file input on the form posts, whether or
			// not it was filled in, so this is most of them on most saves.
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}

			$out[ $key ] = array(
				'error'    => $error,
				'size'     => isset( $raw['size'][ $key ] ) ? (int) $raw['size'][ $key ] : 0,
				'tmp_name' => isset( $raw['tmp_name'][ $key ] ) ? (string) $raw['tmp_name'][ $key ] : '',
				'name'     => isset( $raw['name'][ $key ] ) ? substr( sanitize_file_name( (string) $raw['name'][ $key ] ), 0, 200 ) : '',
			);
		}

		return $out;
	}

	/**
	 * Whether this path is a file PHP received on this request.
	 *
	 * `is_uploaded_file()` is what stops a posted string naming a file on disk from being read
	 * out of the filesystem and published as somebody's screenshot. It answers from PHP's own
	 * list, which is empty under CLI, where the suites run and where no `admin_post_` action can
	 * fire at all.
	 *
	 * @param string $path The temporary path the upload arrived at.
	 * @return bool
	 */
	private static function arrived_by_post( $path ) {
		if ( 'cli' === PHP_SAPI ) {
			return is_readable( $path );
		}

		return is_uploaded_file( $path );
	}

	/**
	 * Store the screenshots this save carried and put their cells in `$cells`.
	 *
	 * **Airtable is sent a URL, not the bytes.** An attachment column is written with
	 * `array( array( 'url' => ..., 'filename' => ... ) )` and Airtable fetches the file from that
	 * address itself, keeping its own copy - which is the same bargain `WPCPM_Sponsor_Logo`
	 * strikes for a logo, and the reason the Media Library copy has to be a public file. A
	 * screenshot is a picture of the student's own site, so that is a decision about their work
	 * rather than about them (design spec of 7 September 2026, sections 5 and 6.3).
	 *
	 * **One screenshot is one lesson's answer**, so a file that cannot be used refuses itself and
	 * nothing else. The sponsor logo refuses its pair together because two halves of one logo have
	 * to match; these ten pictures have nothing to do with each other.
	 *
	 * **Replace deletes the file it supersedes**, unlike the sponsor's logo, which leaves an old
	 * one in place because it may be linked from elsewhere. A screenshot answers one question and
	 * is not linked from anywhere but this card, so nothing is served by keeping it once the map
	 * no longer names it. The new file is stored and the map rewritten first; only then is the
	 * old id, read from the map itself, passed to `wp_delete_attachment()` - so a Replace that
	 * fails partway never deletes a file the map still names.
	 *
	 * @param int   $student_id Student user ID.
	 * @param array $fields     The track's field set.
	 * @param array $cells      Cells to be written, by reference.
	 * @return string '' when every file was stored, else the outcome flag to leave with.
	 */
	private static function store_images( $student_id, array $fields, array &$cells ) {
		$incoming = self::uploaded_images();

		if ( empty( $incoming ) ) {
			return '';
		}

		// The columns this track has, by form key: a file posted under any other key answers no
		// question on this form and is dropped without being read.
		$columns = array();

		foreach ( $fields as $name => $spec ) {
			if ( isset( $spec['type'] ) && 'image' === $spec['type'] ) {
				$columns[ self::key( $name ) ] = $name;
			}
		}

		$incoming = array_intersect_key( $incoming, $columns );

		if ( empty( $incoming ) ) {
			return '';
		}

		// Above the files on purpose, as the sponsor logo's ceiling is: a runaway script must be
		// refused before a megabyte is read into this process. The whole submission's files are
		// claimed at once, so a save that does not fit is refused before a byte of it is read
		// rather than halfway through.
		//
		// A file that turns out to be unusable has still spent its place, which is the price of
		// counting before reading. Twenty a day is a nuisance control rather than an allowance,
		// and a student who has burnt it on twenty bad exports has a different problem.
		if ( ! WPCPM_Ceiling::claim( self::CEILING_IMAGES . (int) $student_id, self::IMAGES_PER_DAY, DAY_IN_SECONDS, count( $incoming ) ) ) {
			return 'report-images-busy';
		}

		$stored     = self::images( $student_id );
		$refused    = false;
		$anything   = false;
		$superseded = array();

		foreach ( $incoming as $key => $file ) {
			$name = $columns[ $key ];

			if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
				$refused = true;
				continue;
			}

			$accepted = WPCPM_Image_Upload::accept(
				$file['tmp_name'],
				array(
					'name'   => $file['name'],
					'max_kb' => self::IMAGE_MAX_KB,
				)
			);

			if ( is_wp_error( $accepted ) ) {
				$refused = true;
				continue;
			}

			// Named and titled after the question rather than after the student: the file is
			// public, and a student's name in a public address is a thing to publish on purpose
			// or not at all. Generated rather than plain, so a stranger who can fetch one
			// screenshot's address cannot walk the rest of the cohort's screenshots by counting
			// from it.
			//
			// **Private, which is about the record rather than the file.** An `inherit`
			// attachment with no parent reads as published, so the unauthenticated
			// `wp/v2/media` listing would hand out every screenshot together with the
			// student's user ID and the lesson's name, and the attachment page would print
			// their display name beside the picture. The status hides the record; the
			// generated name hides the file, because a direct address under
			// `/wp-content/uploads/` is served whatever the status says - which is exactly
			// what the spec's assumption 6.3 needs, since Airtable fetches that address
			// itself. The sponsor application stores a stranger's logo the same way.
			$id = WPCPM_Image_Upload::store(
				$accepted,
				$fields[ $name ]['label'],
				(int) $student_id,
				$fields[ $name ]['label'],
				array(
					'private'        => true,
					'generated_name' => true,
				)
			);

			if ( is_wp_error( $id ) ) {
				$refused = true;
				continue;
			}

			$url = wp_get_attachment_url( (int) $id );

			// A stored file with no address is no use to Airtable, which fetches it, and none to
			// the card, which links it. The row goes rather than sitting in the Media Library
			// with nothing pointing at it.
			if ( ! is_string( $url ) || '' === $url ) {
				wp_delete_attachment( (int) $id, true );

				$refused = true;
				continue;
			}

			// The file this answer replaces, if any: only an id the map itself just held, and
			// only queued here - deleted below, after the map names the new one instead, so a
			// Replace that fails partway through this loop never deletes a file the map still
			// names.
			$was = isset( $stored[ $name ] ) ? (int) $stored[ $name ] : 0;

			if ( $was > 0 && $was !== (int) $id ) {
				$superseded[] = $was;
			}

			$stored[ $name ] = (int) $id;
			$anything        = true;

			// An attachment column is replaced whole, which is Airtable's only mode for one.
			$cells[ $name ] = array(
				array(
					'url'      => $url,
					'filename' => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
				),
			);
		}

		if ( $anything ) {
			update_user_meta( (int) $student_id, self::META_IMAGES, $stored );

			// Only now, with the new files stored and the map already pointing at them: a
			// student who reloads mid-save must never find a question pointing at nothing.
			foreach ( $superseded as $old_id ) {
				wp_delete_attachment( $old_id, true );
			}
		}

		return $refused ? 'report-image-refused' : '';
	}

	/**
	 * Take one screenshot off the record: off this site, and out of the program records.
	 *
	 * **The base is emptied first and the site's copy goes only if that worked.** The save wrote
	 * this site's public URL into the base, so there is no original to come back to; a file
	 * deleted here while that URL still stood in the base would leave the record pointing at a
	 * picture nobody can fetch again, and the sponsor logo's Remove is written the same way for
	 * the same reason.
	 */
	public static function handle_image_remove() {
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0;

		check_admin_referer( self::ACTION_REMOVE_IMAGE . '_' . $student_id );

		if ( ! self::user_can_edit( $student_id ) ) {
			wp_die( esc_html__( 'You cannot change that report form.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Mentor_Calls::student_record( $student_id );

		if ( '' === $record ) {
			self::bounce( 'report-no-record' );
		}

		$asked   = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$program = WPCPM_Students_Sync::get_program( $student_id );
		$fields  = self::fields( WPCPM_Program::track( isset( $program['program'] ) ? $program['program'] : '' ) );
		$stored  = self::images( $student_id );
		$column  = '';

		foreach ( $fields as $name => $spec ) {
			if ( isset( $spec['type'] ) && 'image' === $spec['type'] && self::key( $name ) === $asked ) {
				$column = $name;
				break;
			}
		}

		// A key naming no screenshot question on this student's own track, or a question this
		// site holds no file for. Either way there is nothing here to take back, and the base's
		// cell is not this button's to empty: the control only draws it over a file the site
		// stored itself.
		if ( '' === $column || empty( $stored[ $column ] ) ) {
			self::bounce( 'report-image-none' );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$written  = $airtable->update_records(
			$settings['reports_table'],
			array(
				array(
					'id'     => $record,
					'fields' => array( $column => array() ),
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			self::bounce( 'report-image-kept' );
		}

		$deleted = wp_delete_attachment( (int) $stored[ $column ], true );

		// The count the last sync left says the base holds a file for this question, and it no
		// longer does - the PATCH above went through whatever happened to the file. Without this
		// the control would go straight from the picture to "1 file on record in Airtable" and
		// stay there until a sync happened to run.
		WPCPM_Students_Sync::forget_report_file( $student_id, $column );

		self::forget( $record );

		// **A delete that did not happen keeps its map entry.** `wp_delete_attachment()` answers
		// false when the row will not go, and dropping the entry anyway would leave a file in the
		// uploads directory with nothing on this site naming it - unfindable, undeletable from
		// here, and still fetchable by anyone holding its address. Keeping the entry means the
		// question still shows the picture and still offers Remove, so the next press finishes
		// what this one started; the PATCH it repeats empties a cell that is already empty.
		if ( false === $deleted || null === $deleted ) {
			self::bounce( 'report-image-partly' );
		}

		unset( $stored[ $column ] );
		update_user_meta( (int) $student_id, self::META_IMAGES, $stored );

		self::bounce( 'report-image-removed' );
	}

	/**
	 * A submitted value, in the shape Airtable takes, or null if it cannot be used.
	 *
	 * The rules live in `WPCPM_Field_Value`, shared with the feedback forms. This keeps the pair
	 * the handler reads, and passes the form's own text cap so nothing a student can type got
	 * shorter when the rules moved.
	 *
	 * @param mixed $raw  Posted value.
	 * @param array $spec Field spec.
	 * @return array{0:bool,1:mixed} Whether to write it, and what to write.
	 */
	private static function clean( $raw, array $spec ) {
		$spec['max_text'] = self::MAX_TEXT;

		// This form calls a single select's list `options`, because that is what the control
		// prints; the shared rules call it `choices`, because that is what Airtable calls a
		// column's list. Translated here rather than renaming either: the word each of them
		// uses is the right word where it is used.
		if ( isset( $spec['options'] ) ) {
			$spec['choices'] = (array) $spec['options'];
		}

		$result = WPCPM_Field_Value::clean( $raw, $spec );

		return array( $result['ok'], $result['value'] );
	}


	/**
	 * A form key for an Airtable field name.
	 *
	 * Airtable names contain spaces, slashes and colons, none of which belong in a form key - and
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
			'report-saved'         => array( 'success', __( 'Your report form is saved, and your mentor can see it.', 'wpcredits-program-manager' ) ),
			'report-nothing'       => array( 'error', __( 'Nothing was submitted, so nothing changed.', 'wpcredits-program-manager' ) ),
			'report-no-record'     => array( 'error', __( 'Your record could not be found in the program data, so there is nothing to save to.', 'wpcredits-program-manager' ) ),
			'report-refused'       => array( 'error', __( 'The program records refused the change, so nothing was saved. Please try again.', 'wpcredits-program-manager' ) ),
			'report-partly'        => array( 'error', __( 'Saved, except for one or more answers that could not be read. A grade or an hours count takes digits, and a grade is between 0 and 100; a choice question takes one of its own options.', 'wpcredits-program-manager' ) ),
			'report-rejected'      => array( 'error', __( 'Nothing was saved: one or more answers could not be read. A grade or an hours count takes digits, and a grade is between 0 and 100; a choice question takes one of its own options.', 'wpcredits-program-manager' ) ),
			// The screenshots. Each says what to do next, because every one of these is
			// something the student can act on rather than something that has gone wrong.
			'report-image-refused' => array(
				'error',
				sprintf(
					/* translators: 1: the least width in pixels, 2: the longest side in pixels, 3: the size ceiling in kilobytes. */
					__( 'Saved, except for one or more screenshots. A screenshot has to be a PNG, JPEG or WebP picture, at least %1$d pixels wide, with no side longer than %2$d pixels, and no more than %3$d KB. Export it again and try that one on its own.', 'wpcredits-program-manager' ),
					WPCPM_Image_Upload::MIN_WIDTH,
					WPCPM_Image_Upload::MAX_SIDE,
					self::IMAGE_MAX_KB
				),
			),
			'report-images-busy'   => array(
				'error',
				sprintf(
					/* translators: %d: how many screenshots a student may upload in a day. */
					__( 'Your answers were saved, but the screenshots were not: this site takes %d screenshot uploads per student per day, and today\'s are used up. They will be accepted again tomorrow.', 'wpcredits-program-manager' ),
					self::IMAGES_PER_DAY
				),
			),
			'report-image-removed' => array( 'success', __( 'That screenshot is removed, here and from the program records.', 'wpcredits-program-manager' ) ),
			'report-image-partly'  => array( 'error', __( 'That screenshot is removed from the program records, but this site could not delete its own copy, so it is still shown here. Press Remove again.', 'wpcredits-program-manager' ) ),
			'report-image-kept'    => array( 'error', __( 'The screenshot was left in place: the program records could not be told to drop it. Please try again.', 'wpcredits-program-manager' ) ),
			'report-image-none'    => array( 'error', __( 'There is no screenshot of yours on that question, so there was nothing to remove.', 'wpcredits-program-manager' ) ),
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
