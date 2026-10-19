<?php
/**
 * The Education Hub's content: the six programs of the program index, and the seven student steps
 * and four institution levels of the pathway, every text translated and every link a target.
 *
 * In code rather than in the editor, as the design note's 7.4 decides: the two blocks print the same
 * texts with and without JavaScript, and a translator sees each one whole. The texts the patterns'
 * core blocks hold (the section heads, the doors, the field-of-study flow, the closing band) are the
 * patterns' own and editable in the Site Editor.
 *
 * **A benefit no public page confirms is marked planned** (the design note's 6.11): its `planned` is
 * true, and the blocks print "(planned)" after it. The mark comes off here, in a theme release, the
 * day a public page confirms the item.
 *
 * A link's target is an outside address, an in-page anchor, or one of the site's own pages, named by
 * `institution_application` or `sponsor_application` and read when the block renders
 * (`wpcredits_hub_url()`).
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a link to one of the site's own pages goes while that page is not published or the plugin is off. */
const WPCREDITS_HUB_HANDBOOK = 'https://make.wordpress.org/community/handbook/education/credits/';

/**
 * One benefit: its text and whether it is only planned.
 *
 * @param string $text    The item, translated.
 * @param bool   $planned Whether no public page confirms it yet.
 * @return array{text: string, planned: bool}
 */
function wpcredits_hub_item( $text, $planned = false ) {
	return array(
		'text'    => (string) $text,
		'planned' => (bool) $planned,
	);
}

/**
 * One course of a program, for the band of its courses under the index: its name, its public page,
 * the short line above its name (its hours where its page gives them, who it is for where it does
 * not), and one line on what it asks.
 *
 * @param string $name    Its name, translated.
 * @param string $eyebrow The line above its name, translated.
 * @param string $line    What it asks, a sentence, translated.
 * @param string $url     Its public page.
 * @return array{name: string, eyebrow: string, line: string, url: string}
 */
function wpcredits_hub_course( $name, $eyebrow, $line, $url ) {
	return array(
		'name'    => (string) $name,
		'eyebrow' => (string) $eyebrow,
		'line'    => (string) $line,
		'url'     => (string) $url,
	);
}

/**
 * One link: its label and its target.
 *
 * @param string $label  The label, translated.
 * @param string $target An address, an anchor, `institution_application` or `sponsor_application`.
 * @return array{label: string, target: string}
 */
function wpcredits_hub_link( $label, $target ) {
	return array(
		'label'  => (string) $label,
		'target' => (string) $target,
	);
}

/**
 * Where a link's target goes: the site's institution or sponsorship application while its page is
 * published and the plugin can say where it is, the WordPress Credits handbook otherwise; any other
 * target as it is.
 *
 * @param string $target A link's target.
 * @return string An address, not escaped.
 */
function wpcredits_hub_url( $target ) {
	$pages = array(
		'institution_application' => 'WPCPM_Institution_Application',
		'sponsor_application'     => 'WPCPM_Sponsor_Application',
	);

	if ( ! isset( $pages[ $target ] ) ) {
		return (string) $target;
	}

	$class = $pages[ $target ];
	$url   = class_exists( $class ) && method_exists( $class, 'page_url' ) ? (string) call_user_func( array( $class, 'page_url' ) ) : '';

	return '' !== $url ? $url : WPCREDITS_HUB_HANDBOOK;
}

/**
 * The six programs, in the order the index lists them.
 *
 * @return array[] Each: `slug`, `name`, `audiences` (`student`, `faculty`, `career`), `status`
 *                 (`live` or `development`), `step`, `what`, `time`, `gets` (items), `courses` (the
 *                 band of its courses under the index, WordPress Credits' alone) and `links`.
 */
function wpcredits_hub_programs() {
	return array(
		array(
			'slug'      => 'campus-connect',
			'name'      => __( 'Campus Connect', 'wpcredits-theme' ),
			'audiences' => array( 'student' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 1 · Attendee', 'wpcredits-theme' ),
			'what'      => __( 'A half or full-day hands-on WordPress workshop on campus. No experience needed, and anyone connected to the institution can organize one.', 'wpcredits-theme' ),
			'time'      => __( 'Half or full day', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Find or host an event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
			),
		),
		array(
			'slug'      => 'wordpress-credits',
			'name'      => __( 'WordPress Credits', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'career' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its two steps and their names. */
			'step'      => __( 'Steps 3 and 4 · Contributor, Graduate', 'wpcredits-theme' ),
			'what'      => __( 'Mentored work on the WordPress open source project, matched to your field of study. Ends with a presentation and a Foundation certificate.', 'wpcredits-theme' ),
			'time'      => __( '50h or 150h+, about a semester', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate', 'wpcredits-theme' ) ),
				/* translators: A benefit a program gives, a noun: a digital badge. */
				wpcredits_hub_item( __( 'Badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( _x( 'Credit', 'academic credit a program gives, a noun, as in credit toward a degree', 'wpcredits-theme' ) ),
			),
			// The band of its courses under the index (a program lead and a program team member, 15 Oct 2026;
			// the owner, 16 Oct 2026): each says what the course's public Learn page says. The hours are Intro
			// to Contribution's page's, and Foundations' are the owner's (16 Oct 2026: the base course, 150
			// hours or more), what that format suits being the program page's; the tracks' pages give no
			// hours, so they say who they are for. The Translation track was added on 19 Oct 2026, from a
			// program lead's comment in the hub's content review: its page is the course's own, and its line
			// is that page's description in the band's short style.
			'courses'   => array(
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Intro to Contribution', 'wpcredits-theme' ),
					/* translators: The line above a course's name: how long it takes. */
					__( '50 hours', 'wpcredits-theme' ),
					__( 'One guided contribution project: a first look at open source, for a shorter course or an elective.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress, its base course; not foundations as organizations. */
					__( 'Foundations', 'wpcredits-theme' ),
					/* translators: The line above a course's name: how long it takes. */
					__( '150 hours or more', 'wpcredits-theme' ),
					__( 'Your own site and a contribution that ships, for an internship, a capstone, or a full semester.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-foundations/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Developer track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For developers', 'wpcredits-theme' ),
					__( 'WordPress developer training and patch testing practice, toward a contribution that ships.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-developer-track/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Designer track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For designers', 'wpcredits-theme' ),
					__( 'A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-designer-track/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Translation track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For translators', 'wpcredits-theme' ),
					__( 'Translation skills and real work with the Polyglots team: translate.wordpress.org and GlotPress, locale glossaries, software strings, and Learn WordPress lessons.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-translation-track/'
				),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Compare the five courses', 'wpcredits-theme' ), '#wordpress-credits-courses' ),
				wpcredits_hub_link( __( 'About the program', 'wpcredits-theme' ), 'https://wordpress.org/education/credits/' ),
				wpcredits_hub_link( __( 'Impact dashboard', 'wpcredits-theme' ), 'https://wordpress.github.io/WPCredits/' ),
			),
		),
		array(
			'slug'      => 'facilitator-training',
			'name'      => __( 'Facilitator Training', 'wpcredits-theme' ),
			'audiences' => array( 'faculty' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 6 · Community Leader', 'wpcredits-theme' ),
			'what'      => __( 'Free, self-guided courses plus step-by-step facilitation guides with timed agendas and activity sheets, so faculty can teach WordPress themselves.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Ready-to-run sessions', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Facilitator listing', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Facilitation guides', 'wpcredits-theme' ), 'https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/' ),
				wpcredits_hub_link( __( 'Courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'learn-wordpress',
			'name'      => __( 'Learn WordPress', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'faculty' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: anywhere along it. */
			'step'      => __( 'Any step', 'wpcredits-theme' ),
			'what'      => __( 'Free courses, lessons, and online workshops for every skill level, from building a first site to developing blocks.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Course completions on your profile', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Browse courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'ai-leaders',
			'name'      => __( 'AI Leaders', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'live',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 7 · Credentialed', 'wpcredits-theme' ),
			'what'      => __( 'The first Foundation-backed micro-credential. Piloted in 2026 with students from Illinois and Louisiana.', 'wpcredits-theme' ),
			'time'      => __( 'Cohort-based', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Micro-credential', 'wpcredits-theme' ) ),
				/* translators: The amount is in US dollars. */
				wpcredits_hub_item( __( '$1,000 for completers', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://ai-leaders.org/' ),
			),
		),
		array(
			'slug'      => 'wordpress-builder-certification',
			'name'      => __( 'WordPress Builder Certification', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'development',
			/* translators: Where a program sits on the pathway: its step and the step's name. */
			'step'      => __( 'Step 7 · Credentialed', 'wpcredits-theme' ),
			'what'      => __( 'A certification exam validating the skills to build and run a personal or company website on WordPress.', 'wpcredits-theme' ),
			/* translators: A program's time on its card, a noun: an exam to sit, not a course to attend. */
			'time'      => __( 'Exam', 'wpcredits-theme' ),
			// Not marked: the card's "In development" tag already says it is not offered yet.
			'gets'      => array(
				wpcredits_hub_item( __( 'Industry certification', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://wordpresscertifications.com/wordpress-builder-certification/' ),
			),
		),
	);
}

/**
 * The seven student steps, from the first workshop to the first job.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `earned` (the detail's sentence),
 *                 `items` (what it unlocks), `recognition` and `next`, a link.
 */
function wpcredits_hub_steps() {
	return array(
		array(
			'name'        => __( 'Attendee', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Attend a Campus Connect event', 'wpcredits-theme' ),
			'earned'      => __( 'Attend a Campus Connect event.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A seeded WordPress.org profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => __( 'Recognized co-curricular activity or event credit, where the school offers it.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Find a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
		),
		array(
			'name'        => __( 'Builder', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Launch a live site with real content', 'wpcredits-theme' ),
			'earned'      => __( 'Launch a live site with real content.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'A portfolio piece that can be assessed within an existing course.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Learn to build a site', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
		),
		array(
			'name'        => __( 'Contributor', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Get your first contribution accepted', 'wpcredits-theme' ),
			'earned'      => __( 'Get your first contribution accepted.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Contribution badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Make team channels', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Mentor matching', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Retention support starts here', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Open source work logged toward course or program requirements.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Start WordPress Credits', 'wpcredits-theme' ), '#program-wordpress-credits' ),
		),
		array(
			'name'        => _x( 'Graduate', 'a step on the student pathway, a noun: someone who has completed a track', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a WordPress Credits track', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a WordPress Credits track.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Foundation certificate', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Digital badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Verified hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Alumni directory listing', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'WordCamp ticket support', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Credit-bearing.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'See the courses', 'wpcredits-theme' ), '#wordpress-credits-courses' ),
		),
		array(
			'name'        => __( 'Specialist', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a second or third program', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a second or third program.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Deeper specialization', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Scoped project work', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority mentor matching', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A specialist badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'Credit-bearing as an advanced elective, independent study, practicum, or capstone.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Explore programs', 'wpcredits-theme' ), '#programs' ),
		),
		array(
			'name'        => __( 'Community Leader', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Facilitate, mentor, organize, or speak', 'wpcredits-theme' ),
			'earned'      => __( 'Facilitate, mentor, organize, or speak.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Listing in the facilitator and speaker directory', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Discounted or free WordCamp access', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority on paid teaching leads', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Organizing support', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => __( 'A role the school already records: teaching assistant, peer mentor, or student organization leader.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'        => __( 'Credentialed', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Earn a micro-credential', 'wpcredits-theme' ),
			'earned'      => __( 'Earn a micro-credential.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'An Open to Work listing on jobs.wordpress.net', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Credentials shown on your profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
			),
			'recognition' => __( 'A stackable credential accepted toward a degree, certificate, or transcript notation.', 'wpcredits-theme' ),
			'next'        => wpcredits_hub_link( __( 'Where this leads: WordPress Jobs', 'wpcredits-theme' ), 'https://jobs.wordpress.net/' ),
		),
	);
}

/**
 * The four institution partner levels.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `reached` (the detail's sentence),
 *                 `items` (what it gets) and `next`, a link.
 */
function wpcredits_hub_levels() {
	return array(
		array(
			'name'    => __( 'Participating', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Host one Campus Connect event', 'wpcredits-theme' ),
			'reached' => __( 'Host one Campus Connect event.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A listing on the WordPress education page', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A logo kit', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Request a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/organize/' ),
		),
		array(
			'name'    => _x( 'Partner', 'a level on the institution pathway, a noun: an institution that has signed a partnership agreement', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Sign a partnership agreement and complete one WordPress Credits group', 'wpcredits-theme' ),
			'reached' => __( 'Sign a partnership agreement and complete one WordPress Credits group.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A partner badge for your site', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An Institution Dashboard', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An auto-generated impact report each semester', 'wpcredits-theme' ) ),
			),
			'next'    => wpcredits_hub_link( __( 'Partner as an institution', 'wpcredits-theme' ), 'institution_application' ),
		),
		array(
			'name'    => __( 'Established', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams', 'wpcredits-theme' ),
			'reached' => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'Named regional support', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A WordCamp speaking slot', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Co-marketing', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'    => __( 'Flagship', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students', 'wpcredits-theme' ),
			'reached' => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A case study', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A Foundation announcement', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Input on the program\'s direction', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Event hosting', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Talk to the program team', 'wpcredits-theme' ), 'institution_application' ),
		),
	);
}

/**
 * A list of benefits, one to a line, each planned one followed by "(planned)" in its own span, kept
 * on the item's line by a no-break space so it never wraps alone.
 *
 * @param array[] $items Items, as `wpcredits_hub_item()` makes them.
 * @return string Escaped markup.
 */
function wpcredits_hub_items_html( array $items ) {
	$html = '<ul class="wpc-hub-items">';

	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item['text'] );

		if ( ! empty( $item['planned'] ) ) {
			$html .= '&nbsp;<span class="wpc-hub-planned">' . esc_html_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' ) . '</span>';
		}

		$html .= '</li>';
	}

	return $html . '</ul>';
}
