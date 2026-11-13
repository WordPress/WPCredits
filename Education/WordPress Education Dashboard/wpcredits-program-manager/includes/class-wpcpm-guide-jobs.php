<?php
/**
 * The jobs in the Administrator guide that a place on the dashboards links to.
 *
 * An Administrator who looks at a card that waits for a decision can press one small link, How to do
 * this, to reach that job's numbered steps in the guide. This class holds the list (a key, the id of the
 * job's heading in the built guide and the heading's words, which bin/test-guide-jobs.php checks against
 * docs/build/administrators.html) and draws the link. It finds the guide the way the Settings screen's
 * section intros do (`WPCPM_Settings_Screen::settings_guide()`): a guide that is not published gives no
 * link, and the card says nothing of it.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The guide's jobs by key, and the link to each.
 */
class WPCPM_Guide_Jobs {

	/**
	 * Key => the job's heading id in the built guide and its words, exactly.
	 */
	const JOBS = array(
		'institution-application' => array(
			'anchor' => 'a-school-applied',
			'title'  => 'A school applied',
		),
		'institution-agreement'   => array(
			'anchor' => 'a-signed-collaboration-agreement-arrived',
			'title'  => 'A signed Collaboration Agreement arrived',
		),
		'semester-report'         => array(
			'anchor' => 'a-semester-report-draft-review-approve-reopen',
			'title'  => 'A semester report: draft, review, approve, reopen',
		),
		'institution-requests'    => array(
			'anchor' => 'a-school-s-other-requests-a-student-to-add-a-mentor-wanted',
			'title'  => "A school's other requests (a student to add, a mentor wanted)",
		),
		'sponsor-application'     => array(
			'anchor' => 'a-company-applied-to-sponsor',
			'title'  => 'A company applied to sponsor',
		),
		'sponsor-post'            => array(
			'anchor' => 'a-sponsor-sent-a-post-for-review',
			'title'  => 'A sponsor sent a post for review',
		),
		'sponsor-offer'           => array(
			'anchor' => 'a-sponsor-sent-an-offer-for-review',
			'title'  => 'A sponsor sent an offer for review',
		),
		'sponsor-agreement'       => array(
			'anchor' => 'a-sponsor-uploaded-its-signed-agreement',
			'title'  => 'A sponsor uploaded its signed agreement',
		),
		'sponsor-event'           => array(
			'anchor' => 'a-sponsor-planned-an-event',
			'title'  => 'A sponsor planned an event',
		),
		'sponsor-report'          => array(
			'anchor' => 'a-sponsor-s-annual-report',
			'title'  => "A sponsor's annual report",
		),
		'cohorts'                 => array(
			'anchor' => 'read-the-cohort-timeline-do-we-need-more-mentors',
			'title'  => 'Read the cohort timeline: do we need more mentors?',
		),
		'duplicates'              => array(
			'anchor' => 'a-student-has-duplicate-rows',
			'title'  => 'A student has duplicate rows',
		),
		'quiet-students'          => array(
			'anchor' => 'students-who-have-gone-quiet',
			'title'  => 'Students who have gone quiet',
		),
		'syncs'                   => array(
			'anchor' => 'run-a-sync-by-hand-and-read-its-report',
			'title'  => 'Run a sync by hand and read its report',
		),
		'mentor-application'      => array(
			'anchor' => 'a-mentor-applied',
			'title'  => 'A mentor applied',
		),
		'student-registration'    => array(
			'anchor' => 'a-student-registered',
			'title'  => 'A student registered',
		),
		'student-interest'        => array(
			'anchor' => 'a-student-is-interested-in-a-school-that-is-not-in-the-program',
			'title'  => 'A student is interested in a school that is not in the program',
		),
	);

	/**
	 * The guide's address, looked up once a request: a dashboard draws a dozen links, and each would
	 * otherwise ask the database for the same page. False until the first lookup, then the address, or ''
	 * for a site with no published guide.
	 *
	 * @var string|false
	 */
	private static $guide_url = false;

	/**
	 * Forget the address looked up, so the next link asks again. For a suite that changes the page.
	 */
	public static function forget() {
		self::$guide_url = false;
	}

	/**
	 * The guide's address at one job.
	 *
	 * @param string $key A key of `JOBS`.
	 * @return string The address with the job's anchor; '' for an unknown key or when the site has
	 *                no published guide.
	 */
	public static function url( $key ) {
		$key = (string) $key;

		if ( ! isset( self::JOBS[ $key ] ) ) {
			return '';
		}

		if ( false === self::$guide_url ) {
			$guide           = WPCPM_Settings_Screen::settings_guide();
			self::$guide_url = is_array( $guide ) && ! empty( $guide['url'] ) ? (string) $guide['url'] : '';
		}

		return '' === self::$guide_url ? '' : self::$guide_url . '#' . self::JOBS[ $key ]['anchor'];
	}

	/**
	 * The small link from a card to its job: How to do this, for an Administrator only.
	 *
	 * @param string $key   A key of `JOBS`.
	 * @param string $about The card's title, which a screen reader reads after the words.
	 * @return string The link's markup, escaped; '' for a reader who cannot manage the program, an
	 *                unknown key, or a site with no published guide.
	 */
	public static function link( $key, $about = '' ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return '';
		}

		$url = self::url( $key );

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="wpcpm-job-link" href="%1$s">%2$s%3$s</a>',
			esc_url( $url ),
			esc_html__( 'How to do this', 'wpcredits-program-manager' ),
			'' === (string) $about ? '' : '<span class="screen-reader-text">: ' . esc_html( (string) $about ) . '</span>'
		);
	}
}
