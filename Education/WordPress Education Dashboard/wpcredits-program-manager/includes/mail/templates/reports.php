<?php
/**
 * The Semester reports area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Semester_Report_Screen` wrote before 1.122.21, under the
 * same translatable string, but one: the draft's count of students still in progress, a sentence
 * `_n()` used to word whole, is a value now (`{students_in_progress}`), the counted words and their
 * verb, so the rest of the sentence can be edited.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The semester a preview and a test name: the one running now.
$wpcpm_semester = array(
	'means'    => __( 'The semester the report covers, such as "January to June 2026". The email needs it.', 'wpcredits-program-manager' ),
	'example'  => static function () {
		return WPCPM_Cohort::label( WPCPM_Cohort::current() );
	},
	'required' => true,
);

return array(
	'report-drafted'  => array(
		'trigger'      => __( 'When the daily job drafts a semester report for an institution: to the addresses in Who reviews reports, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: institution name. */
			__( 'Semester report drafted: %s', 'wpcredits-program-manager' ),
			'{institution_name}'
		),
		'body'         => implode(
			"\n\n",
			array(
				sprintf(
					/* translators: 1: institution name, 2: a semester, e.g. "January to June 2026". Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
					__( 'A semester report has been drafted for %1$s, covering %2$s. It is waiting for an Administrator to review and approve it; the institution cannot see it until then.', 'wpcredits-program-manager' ),
					'{institution_name}',
					'{semester}'
				),
				sprintf(
					/* translators: %s: how many students, counted with the verb that goes with the number, such as "3 students in this semester were". The site leaves this sentence out when there were none. */
					__( '%s still in progress when the draft was written.', 'wpcredits-program-manager' ),
					'{students_in_progress}'
				),
				/* translators: The line above the link to the draft. Keep the colon at the end; the site leaves this line out with the link under it when the Institution Dashboard page is not published. */
				__( 'Review it here:', 'wpcredits-program-manager' ) . "\n{review_link}",
			)
		) . "\n",
		'placeholders' => array(
			'institution_name'     => array(
				'means'    => __( 'The institution\'s name; its Airtable record when the index holds no name for it.', 'wpcredits-program-manager' ),
				'example'  => 'Example University',
				'required' => false,
			),
			'semester'             => $wpcpm_semester,
			'students_in_progress' => array(
				'means'    => __( 'How many of the semester\'s students were still in progress when the draft was written, counted with the verb that goes with the number: "3 students in this semester were". Empty when none were, which leaves its sentence out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return sprintf(
						/* translators: %s: a number of students. */
						_n( '%s student in this semester was', '%s students in this semester were', 2, 'wpcredits-program-manager' ),
						number_format_i18n( 2 )
					);
				},
				'required' => false,
			),
			'review_link'          => array(
				'means'    => __( 'The draft on the Institution Dashboard, as an Administrator reviews it. Empty, while that page is not published, leaves its lines out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Semester_Report_Screen::report_url( WPCPM_Cohort::current() );
				},
				'required' => false,
			),
		),
	),
	'report-approved' => array(
		'trigger'      => __( 'When an Administrator approves an institution\'s semester report: to each of the institution\'s accounts.', 'wpcredits-program-manager' ),
		'subject'      => __( 'Your semester report is ready', 'wpcredits-program-manager' ),
		'body'         => sprintf(
			/* translators: %s: a semester, e.g. "January to June 2026". Two sentences: the site reads a full stop followed by a space as the end of a sentence. */
			__( 'The semester report on %s has been approved by the WordPress Credits program. You can read it on your Institution Dashboard and download it as a PDF.', 'wpcredits-program-manager' ),
			'{semester}'
		) . "\n\n{report_link}\n",
		'placeholders' => array(
			'semester'    => $wpcpm_semester,
			'report_link' => array(
				'means'    => __( 'The report on the Institution Dashboard. Empty, while that page is not published, leaves its line out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Semester_Report_Screen::report_url( WPCPM_Cohort::current() );
				},
				'required' => false,
			),
		),
	),
	'report-consent'  => array(
		'trigger'      => __( 'When an Administrator asks the students of a semester report whether it may mention them; those one press cannot reach are written to a few minutes later.', 'wpcredits-program-manager' ),
		'subject'      => __( 'May your institution mention you in its semester report?', 'wpcredits-program-manager' ),
		'body'         => implode(
			"\n\n",
			array(
				sprintf(
					/* translators: %s: a semester, e.g. "January to June 2026". */
					__( 'Your institution is writing its report on %s.', 'wpcredits-program-manager' ),
					'{semester}'
				),
				__( 'Two questions on your own form decide what it may say about you: whether your institution may list you in the report, and whether it may quote the feedback you wrote. Both are yours to answer, and either answer is fine. Nothing you wrote is shown to your institution until you have said it may be.', 'wpcredits-program-manager' ),
				/* translators: The line above the address of the student's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
				__( 'Your answers live on your dashboard:', 'wpcredits-program-manager' ) . "\n{dashboard_link}",
				__( 'If you would rather not answer, you do not have to. You will not be asked about this report again.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => array(
			'semester'       => $wpcpm_semester,
			'dashboard_link' => array(
				'means'    => __( 'The student\'s Student Report Card, where the two questions are, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
				},
				'required' => false,
			),
		),
	),
);
