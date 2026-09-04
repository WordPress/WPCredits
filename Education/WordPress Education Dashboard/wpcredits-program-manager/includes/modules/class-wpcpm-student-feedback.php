<?php
/**
 * The feedback surveys, on the Student Report Card.
 *
 * Four short forms rather than one long one, because they are asked at four different moments and
 * the point of them is to be comparable *over time*: three questions repeat word for word in Forms
 * 1, 2 and 3 - overall experience, confidence contributing, and how much the mentor's support is
 * helping - so the same student's answers can be plotted across the program, and mentor support
 * correlated against belonging and intent to keep contributing. Those three are the anchors, and
 * they are the reason each stage is its own form: merging them would leave one answer per student
 * per question and nothing to compare.
 *
 * The question set comes from the July 2026 analysis of 242 responses, in issue #123. What that
 * write-up settles, and what is therefore encoded here rather than left to whoever fills the form
 * in next:
 *
 * - **Conditional follow-ups.** Two questions are only worth asking when the answer above them was
 *   poor - "what slowed you down" after a low *how easy was it to get started*, and "what is making
 *   the hours hard to reach" after an unsure or no. Asked of everybody they collect mostly blanks;
 *   asked of the people who had trouble they collect the reason.
 * - **Questions that were retired** because they duplicated their neighbour and returned the most
 *   empty answers, so they are absent here rather than carried over: onboarding-smoother, project
 *   satisfaction, mentor contact frequency, and the two backward-looking "why not" questions.
 * - **The permissions block is separate**, at the end of Form 3, and says so. Sharing a quote
 *   publicly and being contacted about opportunities are not feedback, and a student who does not
 *   want either must not feel that saying so colours the answers above.
 *
 * **One Airtable record per student, not per submission.** The Feedback table is one row of
 * `F1 - …`, `F2 - …`, `F3 - …` and `F4 - …` columns, so a stage fills in its own columns on the
 * student's row and leaves the rest alone. That is also what makes the record readable as a whole:
 * one row is one student's account of the program from start to finish.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Student feedback surveys.
 */
class WPCPM_Student_Feedback {

	/** Save handler. */
	const ACTION_SAVE = 'wpcpm_save_feedback';

	/** Transient prefix for one student's feedback record. */
	const CACHE_PREFIX = 'wpcpm_feedback_';

	/** How long a fetched record is reused, in seconds. */
	const CACHE_TTL = 300;

	/** Where the student's own feedback record ID is remembered, so it is looked up once. */
	const META_RECORD = 'wpcpm_feedback_record';

	/**
	 * User meta: the institution `META_RECORD` was resolved against, or '' when there was none.
	 *
	 * The stamp is only as good as what the site knew when it was written. Every student who
	 * ever opened the feedback section before `preferred()` existed carries a stamp chosen by
	 * "the first row Airtable returned", and for the fifty duplicated addresses that is a coin
	 * toss that `preferred()` never got to correct, because the stamp is read first. So a
	 * withdrawal typed by a student landed on the other school's row while their own school's
	 * report went on reading the row that said yes. Recording *what the choice was made with*
	 * lets the lookup run again exactly when that knowledge has changed, once, rather than on
	 * every render or never.
	 */
	const META_RECORD_PLACEMENT = 'wpcpm_feedback_record_for';

	/**
	 * Where a student's own answers in the permissions box are stamped: what they said, the
	 * wording they were shown, and when.
	 *
	 * Airtable stays the authority on what the semester report may print, and it is re-read on
	 * every render so a withdrawal takes effect at once. This is the site's own record of the
	 * moment consent was given, which a row that has since been edited cannot supply: the
	 * question a student answered in March is not necessarily the question the form asks today.
	 */
	const META_REPORT_PERMISSIONS = 'wpcpm_report_permissions';

	/** Longest a free-text answer may be. */
	const MAX_TEXT = 5000;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}


	/*
	 * The forms
	 * --------------------------------------------------------------------
	 */

	/**
	 * The three anchor questions, as they are named in each stage's columns.
	 *
	 * Spelled out per form rather than generated, because the column names are not consistent:
	 * Form 1's were created without the space after `F1`, and a name assembled from a prefix would
	 * write to a column that does not exist. Airtable answers a bad field name with an error for
	 * the whole record, so all three stages would fail to save.
	 *
	 * @param string $prefix Column prefix for this stage.
	 * @return array<string, array>
	 */
	private static function anchors( $prefix ) {
		$columns = array(
			'f1' => array(
				'F1- Overall experience so far',
				'F1- How confident do you feel contributing?',
				"F1 - How well is your mentor's support helping you make progress?",
			),
			'f2' => array(
				'F2 - Overall experience so far',
				'F2 - How confident do you feel contributing?',
				"F2 - How well is your mentor's support helping you make progress?",
			),
			'f3' => array(
				'F3 - Overall experience so far',
				'F3 - How confident do you feel contributing?',
				"F3 - How well is your mentor's support helping you make progress?",
			),
		);

		$labels = array(
			__( 'Your overall experience so far', 'wpcredits-program-manager' ),
			__( 'How confident do you feel contributing?', 'wpcredits-program-manager' ),
			__( "How well is your mentor's support helping you make progress?", 'wpcredits-program-manager' ),
		);

		$ends = array(
			array( __( 'Poor', 'wpcredits-program-manager' ), __( 'Excellent', 'wpcredits-program-manager' ) ),
			array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'Very confident', 'wpcredits-program-manager' ) ),
			array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'A great deal', 'wpcredits-program-manager' ) ),
		);

		$fields = array();

		foreach ( $columns[ $prefix ] as $i => $name ) {
			$fields[ $name ] = array(
				'label'  => $labels[ $i ],
				'type'   => 'rating',
				'max'    => 5,
				'ends'   => $ends[ $i ],
				'anchor' => true,
			);
		}

		return $fields;
	}

	/**
	 * The four forms.
	 *
	 * @return array<string, array>
	 */
	public static function forms() {
		$forms = array();

		$forms['f1'] = array(
			'title'  => __( 'Getting started', 'wpcredits-program-manager' ),
			'note'   => __( 'For once you have chosen your contribution team and project. It takes a couple of minutes.', 'wpcredits-program-manager' ),
			'mentor' => 'F1 - Mentor',
			'fields' => self::anchors( 'f1' ) + array(
				'F1 - How easy was it to get started?'                     => array(
					'label' => __( 'How easy was it to get started?', 'wpcredits-program-manager' ),
					'type'  => 'rating',
					'max'   => 5,
					'ends'  => array( __( 'Very hard', 'wpcredits-program-manager' ), __( 'Very easy', 'wpcredits-program-manager' ) ),
				),
				'F1 - How clear was choosing your contribution and project?' => array(
					'label'   => __( 'How clear was choosing your contribution and project?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Very clear', 'Clear enough', 'Neutral', 'Somewhat unclear', 'Very unclear' ),
				),
				// The conditional. Asked of everybody it is mostly blank; asked of the people who
				// found it hard or unclear it is the reason why.
				'F1* - What specifically slowed you down or was unclear?'  => array(
					'label' => __( 'What specifically slowed you down or was unclear?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
					'when'  => array(
						array(
							'field'  => 'F1 - How easy was it to get started?',
							'values' => array( '1', '2' ),
						),
						array(
							'field'  => 'F1 - How clear was choosing your contribution and project?',
							'values' => array( 'Somewhat unclear', 'Very unclear' ),
						),
					),
				),
				'F1 - What helped you most in getting started?'            => array(
					'label' => __( 'What helped you most in getting started?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F1 - What was hardest or most confusing?'                 => array(
					'label' => __( 'What was hardest or most confusing?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F1 - Are the course materials available in a language you are comfortable working in?' => array(
					'label'   => __( 'Are the course materials available in a language you are comfortable working in?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Yes', 'Partly', 'No' ),
				),
			),
		);

		$forms['f2'] = array(
			'title'  => __( 'Half way', 'wpcredits-program-manager' ),
			'note'   => __( 'For the middle of your project, while there is still time to act on what you say.', 'wpcredits-program-manager' ),
			'mentor' => 'F2 - Mentor',
			'fields' => self::anchors( 'f2' ) + array(
				'F2 - Do you feel part of the WordPress community?'        => array(
					'label' => __( 'Do you feel part of the WordPress community?', 'wpcredits-program-manager' ),
					'type'  => 'rating',
					'max'   => 5,
					'ends'  => array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'Very much', 'wpcredits-program-manager' ) ),
				),
				'F2 - What is making you feel part of the community, or what is missing?' => array(
					'label' => __( 'What is making you feel part of the community, or what is missing?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				"F2 - What was most helpful about your mentor's support?"  => array(
					'label' => __( "What was most helpful about your mentor's support?", 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				"F2 - What could your mentor's support do better?"         => array(
					'label' => __( "What could your mentor's support do better?", 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F2 - Do the required hours feel achievable?'              => array(
					'label'   => __( 'Do the required hours feel achievable?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Yes', 'Unsure', 'No' ),
				),
				'F2* - What is making the hours hard to reach?'            => array(
					'label' => __( 'What is making the hours hard to reach?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
					'when'  => array(
						array(
							'field'  => 'F2 - Do the required hours feel achievable?',
							'values' => array( 'Unsure', 'No' ),
						),
					),
				),
			),
		);

		$forms['f3'] = array(
			'title'  => __( 'Finishing up', 'wpcredits-program-manager' ),
			'note'   => __( 'For the end of the program, on either track.', 'wpcredits-program-manager' ),
			'mentor' => 'F3 - Mentor',
			'fields' => self::anchors( 'f3' ) + array(
				'F3 - How impactful do you feel your contributions were?'  => array(
					'label' => __( 'How impactful do you feel your contributions were?', 'wpcredits-program-manager' ),
					'type'  => 'rating',
					'max'   => 5,
					'ends'  => array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'Very impactful', 'wpcredits-program-manager' ) ),
				),
				'F3 - Do you feel part of the WordPress community?'        => array(
					'label' => __( 'Do you feel part of the WordPress community?', 'wpcredits-program-manager' ),
					'type'  => 'rating',
					'max'   => 5,
					'ends'  => array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'Very much', 'wpcredits-program-manager' ) ),
				),
				'F3 - What made you feel part of the community, or what was missing?' => array(
					'label' => __( 'What made you feel part of the community, or what was missing?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F3 - How likely are you to recommend WP Credits to another student?' => array(
					'label'   => __( 'How likely are you to recommend WP Credits to another student?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Likely', 'Neither likely nor unlikely', 'Unlikely' ),
				),
				// Forward looking on purpose: the question it replaced asked why *not*, and came
				// back empty about 40% of the time.
				'F3 - What would have made you more likely to recommend?'  => array(
					'label' => __( 'What would have made you more likely to recommend?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F3 - How likely are you to keep contributing to WordPress?' => array(
					'label'   => __( 'How likely are you to keep contributing to WordPress?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Likely', 'Neither likely nor unlikely', 'Unlikely' ),
				),
				'F3 - How much did your mentor influence your intention to keep contributing to WordPress after the program?' => array(
					'label' => __( 'How much did your mentor influence your intention to keep contributing?', 'wpcredits-program-manager' ),
					'type'  => 'rating',
					'max'   => 5,
					'ends'  => array( __( 'Not at all', 'wpcredits-program-manager' ), __( 'A great deal', 'wpcredits-program-manager' ) ),
				),
				'F3 - What would make you keep contributing?'              => array(
					'label' => __( 'What would make you keep contributing?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F3 - One example of a contribution you are proud of'      => array(
					'label' => __( 'One example of a contribution you are proud of', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F3 - New skills, knowledge or experiences you gained'     => array(
					'label' => __( 'New skills, knowledge or experiences you gained', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F3 - One main change that would improve the program'      => array(
					'label' => __( 'One main change that would improve the program', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				// Optional, and fenced off from the feedback above it: these are about what may
				// be done with a student's name, not about how the program went.
				'F3 - May we share a quote about your experience publicly? If so, please share your thoughts below' => array(
					'label' => __( 'May we share a quote about your experience publicly? If so, write it here.', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
					'group' => 'permissions',
				),
				'F3 - Yes, you can contact me about WordPress events, learning and skill building opportunities, and career opportunities.' => array(
					'label' => __( 'Yes, you can contact me about WordPress events, learning and skill building opportunities, and career opportunities.', 'wpcredits-program-manager' ),
					'type'  => 'checkbox',
					'group' => 'permissions',
				),
				// The institution's semester report. Asked in the second person and about *this
				// student*, because that is what is being decided: not whether the program may
				// describe itself, but whether one named person appears in a document their
				// university sends out. Nothing else on this page leaves the program, so these
				// two carry the whole weight of that difference, and `handle_save()` refuses to
				// write them for anybody but the student themselves.
				//
				// The choices are the base's, byte for byte: `update_records()` sends no
				// `typecast`, so a choice spelled any other way is a 422 for the whole record and
				// the student is told their answers could not be sent.
				'F3 - Report: my institution may list me in its semester report' => array(
					'label'   => __( 'May your institution list you in its semester report?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Yes, with my name', 'Yes, by my blog address only', 'No' ),
					'group'   => 'permissions',
				),
				'F3 - Report: my institution may quote my feedback in its semester report' => array(
					'label'   => __( 'May your institution quote your feedback in its semester report?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Yes, with my name', 'Yes, without my name', 'No' ),
					'group'   => 'permissions',
				),
			),
		);

		$forms['f4'] = array(
			'title'  => __( 'Leaving the program', 'wpcredits-program-manager' ),
			'note'   => __( 'Four questions, for anyone who did not finish. This is the group we hear from least, and it is the one we most need to hear from.', 'wpcredits-program-manager' ),
			'fields' => array(
				'F4 - How far did you get?'                                => array(
					'label'   => __( 'How far did you get?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Never started', 'Started the course', 'Chose a contribution', 'Started contributing' ),
				),
				'F4 - What stopped you?'                                   => array(
					'label'   => __( 'What stopped you?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Did not know where to start', 'No time', 'Not what I expected', 'Not enough support', 'Not relevant to my studies', 'Technical problems', 'Other' ),
				),
				'F4 - What could we have done differently?'                => array(
					'label' => __( 'What could we have done differently?', 'wpcredits-program-manager' ),
					'type'  => 'textarea',
				),
				'F4 - Would you consider coming back?'                     => array(
					'label'   => __( 'Would you consider coming back?', 'wpcredits-program-manager' ),
					'type'    => 'select',
					'choices' => array( 'Yes', 'Maybe', 'No' ),
				),
			),
		);

		/**
		 * Filter the feedback forms.
		 *
		 * @param array $forms Form key => definition.
		 */
		return (array) apply_filters( 'wpcpm_feedback_forms', $forms );
	}

	/**
	 * Which forms this student is shown.
	 *
	 * The stages are what the forms are *for*, so a student who has left is asked why rather than
	 * how their project is going, and a student still on the program is not handed an exit survey.
	 * Everything they are still eligible for stays open, though: the three stage forms are offered
	 * together rather than unlocked one at a time, because a form that appears only during the week
	 * somebody happens to log in is a form nobody fills in.
	 *
	 * @param array $program The student's cached program row.
	 * @return string[] Form keys, in order.
	 */
	public static function forms_for( array $program ) {
		$status = isset( $program['program'] ) ? (string) $program['program'] : '';
		$left   = ! empty( $program['is_past'] ) && ! WPCPM_Program::is_track( $status ) && ! self::is_graduate( $status );

		return $left ? array( 'f4' ) : array( 'f1', 'f2', 'f3' );
	}

	/**
	 * Whether a finished status means they completed the program.
	 *
	 * Matched loosely, because this decides which survey somebody is asked and the statuses are
	 * configurable: anything that is not recognisably a graduation is treated as leaving early,
	 * which asks the gentler set of questions and never hands a graduate an exit survey.
	 *
	 * @param string $status Airtable status.
	 * @return bool
	 */
	private static function is_graduate( $status ) {
		return (bool) preg_match( '/graduat|complet|finish|pass/i', (string) $status );
	}


	/*
	 * The record
	 * --------------------------------------------------------------------
	 */

	/**
	 * This student's row in the Feedback table.
	 *
	 * Matched on **email**, and remembered on the user afterwards so it is one lookup per student
	 * ever rather than one per page. Email is what the survey forms have always been keyed on, and
	 * it is what the existing 771 rows hold; the table's `Students` link would be a better key and
	 * is the open question in #123, but nothing populates it today, so matching on it would create
	 * a second row for every student who has already answered a survey.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 * @param bool    $create  Whether to create the row when there is none.
	 * @return string|WP_Error Record ID.
	 */
	public static function record_for( WP_User $student, array $program, $create = false ) {
		// Which institution the site says this student belongs to, and only when it is a record
		// ID: the stamp is an Institutions record and the link column takes record IDs, so a name
		// that slipped in would be refused for the whole write.
		$institution = trim( (string) get_user_meta( $student->ID, WPCPM_Students_Sync::META_INSTITUTION, true ) );
		$institution = WPCPM_Mentors_Sync::is_record_id( $institution ) ? $institution : '';

		$known = trim( (string) get_user_meta( $student->ID, self::META_RECORD, true ) );

		// **The stamp is believed only when it was made with what the site knows now.** A
		// student the site cannot place has nothing to prefer between rows, so their stamp
		// stands; a placed student's stamp stands when it was resolved against that same
		// institution. Anything else - a stamp from before the placement was known, or from a
		// different placement - is the coin toss described on `META_RECORD_PLACEMENT`, and it is
		// resolved again, once, and re-stamped, so the cost is one read per change of knowledge
		// and not one per render.
		if ( '' !== $known ) {
			$resolved_for = trim( (string) get_user_meta( $student->ID, self::META_RECORD_PLACEMENT, true ) );

			if ( '' === $institution || $resolved_for === $institution ) {
				return $known;
			}
		}

		$email = isset( $program['email'] ) ? trim( (string) $program['email'] ) : '';
		$email = '' !== $email ? $email : trim( (string) $student->user_email );

		if ( '' === $email ) {
			return new WP_Error( 'wpcpm_feedback_no_email', __( 'Your record has no email address, so your feedback could not be matched to it.', 'wpcredits-program-manager' ) );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );

		$page = $airtable->fetch_page(
			$settings['feedback_table'],
			array(
				// Case-insensitively: an address typed into a survey form and one held on the
				// roster differ by case often enough to matter, and a miss here does not read as a
				// miss - it silently starts a second row.
				'formula' => sprintf( 'LOWER({Email}) = LOWER(%s)', self::quote( $email ) ),
				// `Institution` comes back in the same read rather than a second one, because the
				// answer to "which of these rows is this student's" is on it. See `preferred()`.
				'fields'  => array( 'Email', 'Institution' ),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$record = self::preferred( $page['records'], $institution );

		if ( '' !== $record ) {
			update_user_meta( $student->ID, self::META_RECORD, $record );
			update_user_meta( $student->ID, self::META_RECORD_PLACEMENT, $institution );

			return $record;
		}

		// The address has no row at all any more, but a stamp says it once did. The row was
		// deleted in the base; the stamp is not evidence of anything now, and creating a fresh
		// row below is the right answer for a student who came to write.
		if ( '' !== $known && ! $create ) {
			return new WP_Error( 'wpcpm_feedback_none', __( 'You have no feedback record yet.', 'wpcredits-program-manager' ) );
		}

		if ( ! $create ) {
			return new WP_Error( 'wpcpm_feedback_none', __( 'You have no feedback record yet.', 'wpcredits-program-manager' ) );
		}

		$cells = array(
			'Name'  => isset( $program['name'] ) && '' !== $program['name'] ? (string) $program['name'] : $student->display_name,
			'Email' => $email,
		);

		// The track, where it is one. A finished student's status is a state rather than a course,
		// and writing "Graduate" into a column whose choices are the two courses would be refused.
		if ( ! empty( $program['program'] ) && WPCPM_Program::is_track( $program['program'] ) ) {
			$cells['Course'] = (string) $program['program'];
		}

		// The institution, on the row this student's answers will live on. It is linked on all 834
		// existing rows, and the semester report fetches Feedback by address and then keeps only
		// the rows whose link is empty or names the institution it is writing about: a new row
		// with no link would be read by every institution rather than by none, and a consent
		// given on it would be printed by a school this student never attended.
		if ( '' !== $institution ) {
			$cells['Institution'] = array( $institution );
		}

		$created = $airtable->create_records( $settings['feedback_table'], array( array( 'fields' => $cells ) ) );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created[0] ) ) {
			return new WP_Error( 'wpcpm_feedback_not_created', __( 'Your feedback record could not be created.', 'wpcredits-program-manager' ) );
		}

		update_user_meta( $student->ID, self::META_RECORD, (string) $created[0] );
		update_user_meta( $student->ID, self::META_RECORD_PLACEMENT, $institution );

		return (string) $created[0];
	}

	/**
	 * Which of several Feedback rows for one address is this student's.
	 *
	 * About fifty addresses have more than one row in the table, so "the first one Airtable
	 * returned" is a coin toss, and until now it decided which row a student's answers went to.
	 * That is survivable for feedback and not survivable for consent: the semester report fetches
	 * Feedback by address and keeps only the rows whose `Institution` is empty or names the
	 * institution it is writing about, so a "Yes, with my name" sitting on a row linked to a
	 * different school is never read, and the student is left out of a report they agreed to
	 * appear in without anybody being told why.
	 *
	 * So: this institution's row, else one with no link at all, else the first. The middle rung is
	 * there because the report keeps unlinked rows too, and a row linked to *another* school is the
	 * one row it is certain to throw away.
	 *
	 * A student the site cannot place keeps the old answer, the first row: without an institution
	 * there is nothing to prefer, and no report fetches them by an address it does not hold.
	 *
	 * @param array  $records     Records from the address lookup, as Airtable returned them.
	 * @param string $institution Institutions record ID this student belongs to, or ''.
	 * @return string Record ID, or '' when there are no rows at all.
	 */
	private static function preferred( array $records, $institution ) {
		$first    = '';
		$unlinked = '';

		foreach ( $records as $record ) {
			if ( empty( $record['id'] ) ) {
				continue;
			}

			$id = (string) $record['id'];

			if ( '' === $first ) {
				$first = $id;
			}

			$linked = isset( $record['fields']['Institution'] )
				? array_map( 'strval', (array) $record['fields']['Institution'] )
				: array();

			if ( '' !== $institution && in_array( $institution, $linked, true ) ) {
				return $id;
			}

			if ( '' === $unlinked && empty( $linked ) ) {
				$unlinked = $id;
			}
		}

		return ( '' !== $institution && '' !== $unlinked ) ? $unlinked : $first;
	}

	/**
	 * The answers already on a record.
	 *
	 * @param string $record Airtable record ID.
	 * @return array|WP_Error Field name => value.
	 */
	public static function values( $record ) {
		$record = trim( (string) $record );

		if ( '' === $record ) {
			return array();
		}

		$key    = self::CACHE_PREFIX . md5( $record );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->get_record( $settings['feedback_table'], $record );

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
		delete_transient( self::CACHE_PREFIX . md5( (string) $record ) );
	}

	/**
	 * A stable form key for an Airtable column name.
	 *
	 * The same scheme the report form uses, and for the same reason: these names contain spaces,
	 * apostrophes, asterisks and question marks, and one of them is 108 characters long.
	 *
	 * @param string $name Airtable field name.
	 * @return string
	 */
	public static function key( $name ) {
		return 'f' . substr( md5( (string) $name ), 0, 12 );
	}

	/**
	 * The nonce field's name for one form.
	 *
	 * @param string $form_key Form key.
	 * @return string
	 */
	private static function nonce_name( $form_key ) {
		return '_wpcpm_feedback_' . sanitize_key( $form_key );
	}

	/**
	 * Quote a string for a formula.
	 *
	 * @param string $value Literal value.
	 * @return string
	 */
	private static function quote( $value ) {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $value ) . "'";
	}

	/**
	 * Whether the person on this request is the student themselves.
	 *
	 * Deliberately not a capability. `WPCPM_Student_Report_Form::user_can_edit()` answers "may
	 * this page be filled in", and a program manager passes it; the permissions box asks a
	 * different question, "is this person's own name being given away", and no capability can
	 * stand in for that. Both the form and the handler ask this one so they cannot disagree.
	 *
	 * @param int $student_id The student the form is about.
	 * @return bool
	 */
	private static function is_self( $student_id ) {
		$student_id = (int) $student_id;

		return $student_id > 0 && get_current_user_id() === $student_id;
	}


	/*
	 * Rendering
	 * --------------------------------------------------------------------
	 */

	/**
	 * Every form this student is asked, each in a disclosure of its own.
	 *
	 * **One Airtable read for all of them**, because they are four views of one record - so
	 * opening the section costs what opening one form costs, and the answers a student already gave
	 * are in the boxes rather than asked for twice.
	 *
	 * @param WP_User $student The student.
	 * @param array   $program Their cached program row.
	 */
	public static function render( WP_User $student, array $program ) {
		$keys = self::forms_for( $program );

		if ( empty( $keys ) ) {
			return;
		}

		$forms  = self::forms();
		$can    = WPCPM_Student_Report_Form::user_can_edit( $student->ID );
		$record = self::record_for( $student, $program );
		$values = is_wp_error( $record ) ? array() : self::values( $record );

		// `user_can_edit()` is true for a program manager as well as for the student, and one box
		// on this page turns on which of the two is reading: a permission is only a permission
		// when the person it is about gave it. So who the reader is travels down with `$can`
		// rather than being asked again three functions lower, where it would be easy to forget.
		$is_self = self::is_self( $student->ID );

		// A record that could not be read is not the same as a student who has not answered yet.
		// The forms still render - they are blank either way - but a failed read must not be shown
		// as "you have answered nothing", which invites somebody to type it all in again over
		// answers that are already there.
		$unread = is_wp_error( $values );
		$values = $unread ? array() : $values;

		self::render_message();

		// A heading of its own, because these are a different errand from the report above them.
		// It is an <h4> under the section's <h3>, not another <h3>: they belong to the Report form
		// section, and a heading outline that says otherwise is wrong to a screen reader even
		// where it looks right on screen.
		printf(
			'<h4 class="wpcpm-student__heading wpcpm-feedback__title">%s</h4>',
			esc_html__( 'Feedback forms', 'wpcredits-program-manager' )
		);

		// Said once, under the heading. They sit in the same section as the report form and look
		// the same, so without this a student would reasonably assume these count towards their
		// credits - and answer them the way somebody answers a marked question.
		//
		// **The exception is named here rather than left to be discovered.** The permissions box
		// at the end of the last form is the one place an answer can reach the institution, and a
		// promise that is true everywhere except in one box is not a promise a student can act on.
		printf(
			'<p class="wpcpm-student__note wpcpm-feedback__intro">%s</p>',
			esc_html__( 'The forms below are feedback about the program itself. They are not part of your report, they are not marked, and your institution does not see them, so please say what you actually think. The one exception is the permissions box at the end of the last form: it asks what your institution may put in its own semester report, and nothing from it is shared unless you say yes.', 'wpcredits-program-manager' )
		);

		if ( $unread ) {
			printf(
				'<p class="wpcpm-student__note wpcpm-report__error">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the reason the record could not be read. */
						__( 'Your earlier answers could not be loaded just now: %s Anything you have already sent is safe - try reloading the page before filling these in again.', 'wpcredits-program-manager' ),
						$values instanceof WP_Error ? $values->get_error_message() : ''
					)
				)
			);
		}

		// **One stage at a time.** A form appears once the one before it is finished, so the answers
		// arrive in the order they are asked about rather than all at the end - which is the whole
		// point of asking the same three questions three times. See `unlocked()`.
		$unlocked = self::unlocked( $keys, $forms, $values );

		foreach ( $unlocked as $key ) {
			if ( isset( $forms[ $key ] ) ) {
				self::render_form( $key, $forms[ $key ], $student, $values, $can, $is_self );
			}
		}

		// Said only when there is something still to come, and only to the person who can act on it:
		// a form that is simply absent looks like a form that was taken away.
		if ( $can && count( $unlocked ) < count( $keys ) ) {
			printf(
				'<p class="wpcpm-student__note wpcpm-feedback__locked">%s</p>',
				esc_html__( 'The next form appears once you have answered everything in this one. There is no rush - they are asked at different points in the program.', 'wpcredits-program-manager' )
			);
		}
	}

	/**
	 * Which of a student's forms are open to them.
	 *
	 * Each stage waits for the one before it to be finished. The surveys are meant to be answered
	 * *at* each stage - the three repeated questions only mean something if the answers are months
	 * apart - and a student who opens all three on their last day gives three copies of one opinion.
	 *
	 * **A form that has already been started is never taken away.** Answers given before this rule
	 * existed, or given in a different order, would otherwise be stranded behind a form somebody had
	 * left incomplete, with no way to reach them and nothing on screen to say why.
	 *
	 * @param string[] $keys   The forms this student is eligible for, in order.
	 * @param array    $forms  All form definitions.
	 * @param array    $values Answers on the record.
	 * @return string[]
	 */
	public static function unlocked( array $keys, array $forms, array $values ) {
		$open    = array();
		$blocked = false;

		foreach ( $keys as $key ) {
			if ( ! isset( $forms[ $key ] ) ) {
				continue;
			}

			$form = $forms[ $key ];

			if ( ! $blocked || self::answered( $form, $values ) > 0 ) {
				$open[] = $key;
			}

			if ( ! self::is_complete( $form, $values ) ) {
				$blocked = true;
			}
		}

		return $open;
	}

	/**
	 * One form.
	 *
	 * @param string  $key     Form key.
	 * @param array   $form    Form definition.
	 * @param WP_User $student The student.
	 * @param array   $values  Answers already on the record.
	 * @param bool    $can     Whether it may be filled in.
	 * @param bool    $is_self Whether the reader is the student themselves.
	 */
	private static function render_form( $key, array $form, WP_User $student, array $values, $can, $is_self ) {
		list( $answered, $asking ) = self::progress( $form, $values );

		printf(
			'<details class="wpcpm-report__disclosure wpcpm-feedback" id="wpcpm-feedback-%1$s">',
			esc_attr( $key )
		);

		// The count says what is done, not how much there is to do: "6 of 9 answered" is progress,
		// while the field count the report form used to carry read as a list of chores. It counts
		// **the questions being asked**, so a finished form says "9 of 9" rather than sitting a
		// question short for a follow-up nobody triggered - which now decides whether the next form
		// appears, and would be an unfixable "not finished" if it counted the wrong total.
		printf(
			'<summary class="wpcpm-report__toggle">%1$s%2$s</summary>',
			esc_html( $form['title'] ),
			$answered
				? sprintf(
					' <span class="wpcpm-report__count">%s</span>',
					esc_html(
						$answered >= $asking
							? __( 'all answered', 'wpcredits-program-manager' )
							: sprintf(
								/* translators: 1: number of questions answered, 2: number of questions being asked. */
								__( '%1$s of %2$s answered', 'wpcredits-program-manager' ),
								number_format_i18n( $answered ),
								number_format_i18n( $asking )
							)
					)
				)
				: ''
		);

		echo '<div class="wpcpm-report__body">';

		printf( '<p class="wpcpm-student__note">%s</p>', esc_html( $form['note'] ) );

		if ( $can ) {
			printf(
				'<form class="wpcpm-report wpcpm-feedback__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-feedback>',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Sending…', 'wpcredits-program-manager' )
			);

			// A nonce field of its own name per form, not the default `_wpnonce`. The name is also
			// the input's `id`, and three surveys plus the report and hours forms on one page
			// would otherwise put five elements with `id="_wpnonce"` in the document - which is
			// invalid, and makes `getElementById` on this page a coin toss.
			wp_nonce_field( self::ACTION_SAVE . '_' . (int) $student->ID . '_' . $key, self::nonce_name( $key ) );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
			printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );
			printf( '<input type="hidden" name="form" value="%s" />', esc_attr( $key ) );
		} else {
			printf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html__( 'This is the student\'s own feedback, so it is shown here but not editable.', 'wpcredits-program-manager' )
			);

			echo '<div class="wpcpm-report wpcpm-feedback__form wpcpm-report--readonly">';
		}

		$anchors = array_filter(
			$form['fields'],
			static function ( $spec ) {
				return ! empty( $spec['anchor'] );
			}
		);

		if ( ! empty( $anchors ) ) {
			printf(
				'<fieldset class="wpcpm-report__group wpcpm-feedback__group"><legend>%1$s</legend><p class="wpcpm-report__note">%2$s</p>',
				esc_html__( 'The same three, every time', 'wpcredits-program-manager' ),
				esc_html__( 'Asked at each stage on the same scale, so your answers can be read as a line rather than as three separate snapshots.', 'wpcredits-program-manager' )
			);

			foreach ( $anchors as $name => $spec ) {
				self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : null, $can );
			}

			echo '</fieldset>';
		}

		$rest        = array_diff_key( $form['fields'], $anchors );
		$permissions = array();

		printf(
			'<fieldset class="wpcpm-report__group wpcpm-feedback__group"><legend>%s</legend>',
			esc_html__( 'This stage', 'wpcredits-program-manager' )
		);

		foreach ( $rest as $name => $spec ) {
			if ( isset( $spec['group'] ) && 'permissions' === $spec['group'] ) {
				$permissions[ $name ] = $spec;
				continue;
			}

			self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : null, $can );
		}

		echo '</fieldset>';

		if ( ! empty( $permissions ) ) {
			// Its own box, its own heading, and a sentence saying it is optional. These are all
			// about what may be done with somebody's name, and a student declining them must not
			// feel it reflects on the answers above.
			printf(
				'<fieldset class="wpcpm-report__group wpcpm-feedback__group wpcpm-feedback__group--permissions"><legend>%1$s</legend><p class="wpcpm-report__note">%2$s</p>',
				esc_html__( 'Permissions', 'wpcredits-program-manager' ),
				esc_html__( 'Optional, and separate from the feedback above. These are the only answers on this page that anyone outside the program sees, and only if you say yes. Leaving them blank changes nothing about your report or your place on the program.', 'wpcredits-program-manager' )
			);

			// **Read-only for anybody but the student**, said out loud rather than left as a row of
			// greyed-out boxes nobody can explain. A manager can fill in the rest of this form on a
			// student's behalf, and that is useful; a manager choosing "Yes, with my name" here
			// would be a consent nobody gave, recorded under the student's name in a document their
			// university prints. `handle_save()` refuses the write as well, because a disabled
			// control is a courtesy to the browser and not a rule.
			if ( $can && ! $is_self ) {
				printf(
					'<p class="wpcpm-report__note">%s</p>',
					esc_html__( 'Only the student can answer these. Their answers are shown here so you can see what they said.', 'wpcredits-program-manager' )
				);
			}

			foreach ( $permissions as $name => $spec ) {
				self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : null, $can && $is_self );
			}

			echo '</fieldset>';
		}

		if ( $can ) {
			printf(
				'<p class="wpcpm-report__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
				esc_html__( 'Save my answers', 'wpcredits-program-manager' )
			);
		}

		echo $can ? '</form>' : '</div>';
		echo '</div>';
		echo '</details>';
	}

	/**
	 * How many of a form's questions already have an answer.
	 *
	 * @param array $form   Form definition.
	 * @param array $values Answers on the record.
	 * @return int
	 */
	private static function answered( array $form, array $values ) {
		list( $done ) = self::progress( $form, $values );

		return $done;
	}

	/**
	 * How far through a form somebody is: answered, and how many there are to answer.
	 *
	 * **Only the questions that actually apply are counted**, on both sides of the count. A
	 * conditional follow-up nobody triggered is not a question this student has left blank, and a
	 * form stuck at "8 of 9" for a question that will never be asked reads as unfinished forever -
	 * which matters more since the next stage waits on this one being finished.
	 *
	 * The optional permissions are left out entirely. They say they are optional, and a student who
	 * declines both must not be told they have not finished.
	 *
	 * @param array $form   Form definition.
	 * @param array $values Answers on the record.
	 * @return array{0:int,1:int} Answered, and the number that apply.
	 */
	private static function progress( array $form, array $values ) {
		$done  = 0;
		$total = 0;

		foreach ( $form['fields'] as $name => $spec ) {
			if ( isset( $spec['group'] ) && 'permissions' === $spec['group'] ) {
				continue;
			}

			if ( ! self::applies( $spec, $values ) ) {
				continue;
			}

			++$total;

			if ( self::has_answer( isset( $values[ $name ] ) ? $values[ $name ] : null ) ) {
				++$done;
			}
		}

		return array( $done, $total );
	}

	/**
	 * Whether a stored value counts as an answer.
	 *
	 * @param mixed $value Value from the record.
	 * @return bool
	 */
	private static function has_answer( $value ) {
		if ( null === $value ) {
			return false;
		}

		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		// A ticked checkbox is `true`; an unticked one is absent. An empty string is what Airtable
		// keeps for a cleared text box, and is not an answer.
		if ( is_bool( $value ) ) {
			return true;
		}

		return is_scalar( $value ) && '' !== trim( (string) $value );
	}

	/**
	 * Whether a conditional question is being asked at all.
	 *
	 * The server's copy of the rule the script reads out of `data-wpcpm-when`. Both take the same
	 * definition from `forms()`, so they cannot disagree about what a poor answer is.
	 *
	 * @param array $spec   Field spec.
	 * @param array $values Answers on the record.
	 * @return bool
	 */
	private static function applies( array $spec, array $values ) {
		if ( empty( $spec['when'] ) ) {
			return true;
		}

		foreach ( $spec['when'] as $rule ) {
			$current = isset( $values[ $rule['field'] ] ) ? $values[ $rule['field'] ] : '';
			$current = is_scalar( $current ) ? (string) $current : '';

			// Compared as strings: a rating comes back from Airtable as a number and the rule is
			// written as `'1'`.
			if ( in_array( $current, array_map( 'strval', (array) $rule['values'] ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether every question a form is currently asking has been answered.
	 *
	 * @param array $form   Form definition.
	 * @param array $values Answers on the record.
	 * @return bool
	 */
	public static function is_complete( array $form, array $values ) {
		list( $done, $total ) = self::progress( $form, $values );

		return $total > 0 && $done === $total;
	}

	/**
	 * One question.
	 *
	 * @param string $name  Airtable field name.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Current answer.
	 * @param bool   $can   Whether it may be answered.
	 */
	private static function render_field( $name, array $spec, $value, $can ) {
		$key  = self::key( $name );
		$id   = 'wpcpm-feedback-' . $key;
		$type = isset( $spec['type'] ) ? $spec['type'] : 'textarea';
		$dis  = $can ? '' : ' disabled="disabled"';

		// The conditional questions carry their rule in the markup rather than in the script, so
		// the script is one rule reader instead of one branch per question - and so a rule changed
		// in `forms()` cannot go out of step with a copy of it in JavaScript.
		$conditional = '';

		if ( ! empty( $spec['when'] ) ) {
			$rules = array();

			foreach ( $spec['when'] as $rule ) {
				$rules[] = array(
					'field'  => self::key( $rule['field'] ),
					'values' => array_values( (array) $rule['values'] ),
				);
			}

			$conditional = sprintf( ' data-wpcpm-when="%s"', esc_attr( wp_json_encode( $rules ) ) );
		}

		printf(
			'<div class="wpcpm-field wpcpm-field--%1$s%2$s"%3$s>',
			esc_attr( $type ),
			$conditional ? ' wpcpm-field--conditional' : '',
			$conditional // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped above.
		);

		// A rating and a checkbox have no single control to point a `for` at - the rating is a row
		// of radios, and the checkbox's own label is the question. Both carry the accessible
		// grouping on the `<fieldset>` inside instead.
		if ( 'rating' === $type ) {
			printf( '<span class="wpcpm-field__label">%s</span>', esc_html( $spec['label'] ) );
			self::render_rating( $key, $spec, $value, $can );
		} elseif ( 'checkbox' === $type ) {
			printf(
				'<label class="wpcpm-feedback__consent"><input type="checkbox" name="feedback[%1$s]" value="1"%2$s%3$s /> <span>%4$s</span></label>',
				esc_attr( $key ),
				! empty( $value ) ? ' checked="checked"' : '',
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_html( $spec['label'] )
			);
		} elseif ( 'select' === $type ) {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $spec['label'] ) );
			printf(
				'<select id="%1$s" name="feedback[%2$s]" data-wpcpm-field="%2$s"%3$s>',
				esc_attr( $id ),
				esc_attr( $key ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);

			// A blank first option, selected when there is no answer: without one the browser
			// pre-selects the first real choice, and an untouched form would send an opinion
			// nobody expressed.
			printf(
				'<option value=""%1$s>%2$s</option>',
				( null === $value || '' === $value ) ? ' selected="selected"' : '',
				esc_html__( 'No answer', 'wpcredits-program-manager' )
			);

			foreach ( $spec['choices'] as $choice ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $choice ),
					selected( (string) $value, (string) $choice, false ),
					esc_html( $choice )
				);
			}

			echo '</select>';
		} else {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $spec['label'] ) );
			printf(
				'<textarea id="%1$s" name="feedback[%2$s]" rows="3" maxlength="%3$d"%4$s>%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				(int) self::MAX_TEXT,
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_textarea( is_scalar( $value ) ? (string) $value : '' )
			);
		}

		echo '</div>';
	}

	/**
	 * A one-to-five scale.
	 *
	 * Radio buttons rather than stars: they work without JavaScript, they are operable from the
	 * keyboard, a screen reader announces which of five is chosen, and the ends are labelled - so
	 * nobody has to guess whether 1 is the good end.
	 *
	 * @param string $key   Field key.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Current answer.
	 * @param bool   $can   Whether it may be answered.
	 */
	private static function render_rating( $key, array $spec, $value, $can ) {
		$max  = isset( $spec['max'] ) ? (int) $spec['max'] : 5;
		$ends = isset( $spec['ends'] ) ? $spec['ends'] : array( '', '' );

		// **Stars, because the column is a star column.** Every one of these is an Airtable `rating`
		// field with `icon: star`, so a row of numbered boxes here and five stars in the base are two
		// renderings of one answer - and the person reading the answers sees the stars.
		//
		// The star is decorative and the number is not: the glyph carries `aria-hidden`, and the
		// radio's accessible name comes from the text beside it. A screen reader announces "3 of 5",
		// which is the answer; "star" is how it happens to be drawn.
		$star = '<svg class="wpcpm-rating__star" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. '<path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.4-5.8-3-5.8 3 1.1-6.4L2.6 9.4l6.5-.9L12 2.6z" />'
			. '</svg>';

		printf(
			'<fieldset class="wpcpm-rating" data-wpcpm-field="%1$s"><legend class="screen-reader-text">%2$s</legend>',
			esc_attr( $key ),
			esc_html( $spec['label'] )
		);

		// Named low and high rather than left to `:first-of-type`. The low end is what the steps
		// start after, so it is the element the alignment rule has to hold on to, and a scale that
		// one day renders only one end must not have that rule land on the wrong label.
		if ( '' !== $ends[0] ) {
			printf( '<span class="wpcpm-rating__end wpcpm-rating__end--low">%s</span>', esc_html( $ends[0] ) );
		}

		for ( $i = 1; $i <= $max; $i++ ) {
			printf(
				'<label class="wpcpm-rating__step"><input type="radio" name="feedback[%1$s]" value="%2$d"%3$s%4$s />%5$s<span class="screen-reader-text">%6$s</span></label>',
				esc_attr( $key ),
				(int) $i,
				checked( (string) $value, (string) $i, false ),
				$can ? '' : ' disabled="disabled"',
				$star, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
				esc_html(
					sprintf(
						/* translators: 1: this step's number, 2: the highest number on the scale. */
						__( '%1$s of %2$s', 'wpcredits-program-manager' ),
						number_format_i18n( $i ),
						number_format_i18n( $max )
					)
				)
			);
		}

		if ( '' !== $ends[1] ) {
			printf( '<span class="wpcpm-rating__end wpcpm-rating__end--high">%s</span>', esc_html( $ends[1] ) );
		}

		echo '</fieldset>';
	}

	/**
	 * The outcome of the last send, if there is one.
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
	 * Write one form's answers to Airtable.
	 */
	public static function handle_save() {
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0;
		$form_key   = isset( $_POST['form'] ) ? sanitize_key( wp_unslash( $_POST['form'] ) ) : '';

		check_admin_referer( self::ACTION_SAVE . '_' . $student_id . '_' . $form_key, self::nonce_name( $form_key ) );

		if ( ! WPCPM_Student_Report_Form::user_can_edit( $student_id ) ) {
			wp_die( esc_html__( 'You cannot fill in that feedback form.', 'wpcredits-program-manager' ), 403 );
		}

		$forms = self::forms();

		if ( ! isset( $forms[ $form_key ] ) ) {
			self::bounce( 'feedback-unknown', $form_key );
		}

		$student = get_user_by( 'id', $student_id );

		if ( ! $student instanceof WP_User ) {
			self::bounce( 'feedback-failed', $form_key );
		}

		$program = WPCPM_Students_Sync::get_program( $student_id );
		$form    = $forms[ $form_key ];

		$posted = isset( $_POST['feedback'] ) && is_array( $_POST['feedback'] )
			? wp_unslash( $_POST['feedback'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is validated by type below.
			: array();

		// The permissions box is the student's own, and `user_can_edit()` above is not: it passes
		// program managers too, and it is right to, because a manager typing up a paper survey on
		// a student's behalf is a real errand. Consent is the one thing that errand cannot cover.
		$is_self = self::is_self( $student_id );
		$cells   = array();
		$dropped = 0;

		foreach ( $form['fields'] as $name => $spec ) {
			$key = self::key( $name );

			// **Never written by anybody but the student.** A manager choosing "Yes, with my name"
			// would put a name into a document a university sends out on a permission its owner
			// never gave, and nothing downstream could tell that consent from a real one. The cell
			// is dropped and the rest of the form still saves: refusing the whole submission would
			// lose answers that were legitimately typed in, and teach people to work around this.
			//
			// Skipped before the checkbox rule below, not after it: an absent checkbox reads as
			// "unticked", so a manager saving a form they were shown with the box disabled would
			// otherwise withdraw a consent the student had given.
			if ( ! $is_self && isset( $spec['group'] ) && 'permissions' === $spec['group'] ) {
				if ( isset( $posted[ $key ] ) ) {
					++$dropped;
				}

				continue;
			}

			// A checkbox posts nothing when it is unticked, so it is read whether or not it is
			// there - otherwise consent could be given and never withdrawn.
			if ( ! isset( $posted[ $key ] ) && 'checkbox' !== ( isset( $spec['type'] ) ? $spec['type'] : '' ) ) {
				continue;
			}

			list( $ok, $value ) = self::clean( isset( $posted[ $key ] ) ? $posted[ $key ] : '', $spec );

			if ( $ok ) {
				$cells[ $name ] = $value;
			}
		}

		if ( empty( $cells ) ) {
			// A post carrying nothing but somebody else's permissions is not an empty form, and
			// saying "nothing was filled in" would hide the one thing worth knowing about it.
			self::bounce( $dropped ? 'feedback-not-yours' : 'feedback-nothing', $form_key );
		}

		// Only now: an empty submission should not create a row for somebody who has not answered.
		$record = self::record_for( $student, $program, true );

		if ( is_wp_error( $record ) ) {
			self::bounce( 'feedback-failed', $form_key );
		}

		// Which mentor they had at *this* stage. A student can change mentor between forms, and
		// the whole point of asking about mentor support three times is being able to tell whose
		// support each answer is about.
		if ( ! empty( $form['mentor'] ) ) {
			$mentor = WPCPM_Students_Sync::get_mentor( $student_id );

			if ( ! empty( $mentor['record_id'] ) && WPCPM_Mentors_Sync::is_record_id( $mentor['record_id'] ) ) {
				$cells[ $form['mentor'] ] = array( (string) $mentor['record_id'] );
			}
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->update_records(
			$settings['feedback_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::bounce( 'feedback-refused', $form_key );
		}

		self::forget( $record );

		// Only the student reaches this: `$cells` cannot hold a permissions field otherwise.
		if ( $is_self ) {
			self::stamp_permissions( $student_id, $form, $cells );
		}

		self::bounce( $dropped ? 'feedback-not-yours' : 'feedback-saved', $form_key );
	}

	/**
	 * Record what a student released, in the wording they were shown, and when.
	 *
	 * Airtable remains the authority: the semester report re-reads these columns on every render
	 * so a withdrawal takes effect at once, and nothing reads this stamp to decide what may be
	 * printed. What it is for is the question Airtable cannot answer six months later - *what were
	 * they asked* - because the row holds today's answer under today's column name, and a question
	 * that has since been reworded leaves no trace at all.
	 *
	 * Every field in the permissions box is stamped, not only the two the report reads: they are
	 * one box, answered in one breath, and half a record of what somebody agreed to is worse than
	 * none. The stamp is replaced rather than merged, because the box is on one form and one save
	 * carries all of it - a merge could only ever leave an old answer sitting under a new time.
	 *
	 * @param int   $student_id The student.
	 * @param array $form       The form definition that was shown.
	 * @param array $cells      What is being written to Airtable, by column name.
	 */
	private static function stamp_permissions( $student_id, array $form, array $cells ) {
		$answers = array();
		$wording = array();

		foreach ( $form['fields'] as $name => $spec ) {
			if ( ! isset( $spec['group'] ) || 'permissions' !== $spec['group'] || ! array_key_exists( $name, $cells ) ) {
				continue;
			}

			$answers[ $name ] = $cells[ $name ];
			$wording[ $name ] = isset( $spec['label'] ) ? (string) $spec['label'] : '';
		}

		if ( empty( $answers ) ) {
			return;
		}

		update_user_meta(
			$student_id,
			self::META_REPORT_PERMISSIONS,
			array(
				'v'       => 1,
				'time'    => time(),
				'answers' => $answers,
				'wording' => $wording,
			)
		);

		self::forget_report_cache( $student_id );
	}

	/**
	 * Drop the semester report's cached reads for this student's institution.
	 *
	 * The report caches what it read from Airtable for five minutes per institution, which is right
	 * for a page somebody is editing and wrong for a withdrawal: a student who has just said "No"
	 * would go on being listed, by name, for as long as the cache stood, on the one screen where
	 * being listed is the whole point.
	 *
	 * Through the report's own `forget()` rather than by deleting a transient, because the key
	 * includes the set of addresses the read covered and cannot be worked out from one student.
	 * Guarded with `class_exists()` because the report is a separate module: a checkout without it
	 * must still be able to save a survey.
	 *
	 * @param int $student_id The student whose answers changed.
	 */
	private static function forget_report_cache( $student_id ) {
		if ( ! class_exists( 'WPCPM_Semester_Report' ) ) {
			return;
		}

		$institution = trim( (string) get_user_meta( $student_id, WPCPM_Students_Sync::META_INSTITUTION, true ) );

		if ( '' === $institution ) {
			return;
		}

		WPCPM_Semester_Report::forget( $institution );
	}

	/**
	 * A submitted answer, in the shape Airtable takes, or a refusal.
	 *
	 * The rules live in `WPCPM_Field_Value`, shared with the Student Report Card form. A field
	 * with no type is a long answer here, where the report form's is a single line, so the
	 * default is filled in before the shared rules see it - the `wpcpm_feedback_forms` filter
	 * can add a field without one. The form's own text cap goes along for the same reason.
	 *
	 * @param mixed $raw  Posted value.
	 * @param array $spec Field spec.
	 * @return array{0:bool,1:mixed}
	 */
	private static function clean( $raw, array $spec ) {
		if ( ! isset( $spec['type'] ) ) {
			$spec['type'] = 'textarea';
		}

		$spec['max_text'] = self::MAX_TEXT;

		$result = WPCPM_Field_Value::clean( $raw, $spec );

		return array( $result['ok'], $result['value'] );
	}

	/**
	 * Send them back to the form they were filling in.
	 *
	 * @param string $status   Flash status.
	 * @param string $form_key Which form, so the page reopens at it.
	 */
	private static function bounce( $status, $form_key = '' ) {
		WPCPM_Flash::set( 'feedback', $status );

		$page = WPCPM_Students_Dashboard::page_url();
		$page = ( '' !== $page ) ? $page : home_url( '/' );

		wp_safe_redirect( $page . ( '' !== $form_key ? '#wpcpm-feedback-' . $form_key : '#wpcpm-report-form' ) );
		exit;
	}

	/**
	 * The flash status from the last send.
	 *
	 * @return string
	 */
	public static function status() {
		return sanitize_key( (string) WPCPM_Flash::take( 'feedback' ) );
	}

	/**
	 * The message for a status.
	 *
	 * @param string $status Flash status.
	 * @return array{0:string,1:string}|array Empty when there is nothing to say.
	 */
	public static function message( $status ) {
		$messages = array(
			'feedback-saved'     => array( 'success', __( 'Thank you - your answers have been sent. You can change them at any time.', 'wpcredits-program-manager' ) ),
			'feedback-nothing'   => array( 'error', __( 'Nothing was filled in, so nothing was sent.', 'wpcredits-program-manager' ) ),
			'feedback-refused'   => array( 'error', __( 'Airtable would not take your answers. Nothing has been lost - try again in a moment.', 'wpcredits-program-manager' ) ),
			'feedback-failed'    => array( 'error', __( 'Your feedback could not be matched to your program record, so nothing was sent.', 'wpcredits-program-manager' ) ),
			'feedback-unknown'   => array( 'error', __( 'That form does not exist.', 'wpcredits-program-manager' ) ),
			// Seen by a manager, and it names the rule rather than the failure: the answers were
			// not refused because something went wrong, but because they are not this reader's to
			// give. Anything else on the same submission was saved.
			'feedback-not-yours' => array( 'error', __( 'The permission answers are the student\'s own to give, so they were not saved. Anything else on the form was.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
	}
}
