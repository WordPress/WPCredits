<?php
/**
 * What the Overview reads of the program, stood in, for the suites that draw it.
 *
 * The Overview reads what waits for a decision and when each sync ran through the Administrator
 * Dashboard's cards (`WPCPM_Administrators_Cards::collect()`, which reads `health()` with the rest),
 * and the cards read through the classes that own the data. bin/test-overview.php draws the screen
 * in the worlds its checks need, and bin/test-admin-menu.php draws it through the real admin class
 * with the real tools, for its title and its words. Both stand the same owners in, here, so a class
 * the cards come to read fails both suites in one place rather than in two copies of it.
 *
 * Every stand-in answers from `$GLOBALS['overview_reads']`, and a key a suite leaves out answers what
 * a new site holds: nothing waiting, no sync running, no scan yet. Named for this file rather than for
 * the screen, since a suite's own `$overview` at the top of its script is that same global. The keys:
 *
 * - `applications`: how many institution applications wait, all of them open;
 * - `agreements`: the Collaboration Agreements waiting for review, as post IDs, each a post the suite
 *   seeds, since the cards read how long each has waited;
 * - `drafts` and `due`: the semester reports to review and the semesters due for drafting, as rows;
 * - `requests`: the open mentor requests, ID => facts, `overdue` among them;
 * - `locked`: the accounts locked today;
 * - `sponsor_posts` and `sponsor_applications`: the rows waiting, and `sponsor_agreements`, ID =>
 *   the facts of a document waiting for review;
 * - `offers`, ID => offer, and `codes`, ID => the offer's code counts;
 * - `duplicates`: the Student Duplicate Finder's last report, or none before its first scan;
 * - `progress`: audience => where its sync is (`running`, `label`, `error`).
 *
 * When each sync last ran is the option it keeps (`OPT_LAST`) and when it runs next is the cron, both
 * the suite's own, since the cards read those through WordPress. The rest of WordPress is each
 * suite's too, and so are the other classes the cards read, which each suite loads or stands in: the
 * settings, the semesters and the Institutions module's open states. The sponsors' records, their
 * dashboards and what they said lately are read through classes left out here, which the cards
 * guard: the Overview shows none of them.
 *
 * Beside what the cards read, the members the two suites' other readers ask of the same classes: the
 * Institutions and Sponsors entries count their menu bubbles with `pending_count()` and
 * `awaiting_review()`, and the Mentor Status Checker describes itself with the mentors sync's column
 * names.
 *
 * Loaded with `require_once __DIR__ . '/stubs/overview-reads.php';` from a suite's header.
 */

$GLOBALS['overview_reads'] = array(); // What the program holds, by the keys above.

/**
 * What the program holds under a key, or what a new site holds.
 *
 * @param string $key  One of the keys above.
 * @param mixed  $none What a new site holds.
 * @return mixed
 */
function wpcpm_overview_holds( $key, $none ) {
	return array_key_exists( $key, $GLOBALS['overview_reads'] ) ? $GLOBALS['overview_reads'][ $key ] : $none;
}

/**
 * Where an audience's sync is: idle, and never failed, unless the suite says otherwise.
 *
 * @param string $audience `students`, `mentors`, `institutions` or `sponsors`.
 * @return array
 */
function wpcpm_overview_progress( $audience ) {
	$progress = (array) wpcpm_overview_holds( 'progress', array() );

	return isset( $progress[ $audience ] ) ? (array) $progress[ $audience ] : array(
		'running' => false,
		'phase'   => '',
		'label'   => '',
		'error'   => '',
	);
}

/* ---- the institutions' queues ---------------------------------------------- */

/**
 * Institution applications: every one that waits is open, so none is rejected or spam. The states
 * are the real class's, which the Institutions module's open states are made of.
 */
class WPCPM_Institution_Application {
	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_INFO     = 'info';
	const STATE_REJECTED = 'rejected';
	const STATE_SPAM     = 'spam';

	/** The rows the dashboard's card draws, which the Overview never does, so none is built. */
	public static function applications( $states, $limit = 0 ) {
		return array();
	}

	/** The IDs a total is counted from: as many open ones as wait, and no closed one. */
	public static function application_ids( $states ) {
		$open = (int) wpcpm_overview_holds( 'applications', 0 );

		return in_array( self::STATE_REJECTED, (array) $states, true ) || $open < 1 ? array() : range( 1, $open );
	}

	/** The Institutions entry's bubble: the applications that wait. */
	public static function pending_count( $limit = 0 ) {
		return (int) wpcpm_overview_holds( 'applications', 0 );
	}
}

/** Collaboration Agreements: those waiting for review, and none returned or revoked. */
class WPCPM_Institution_Agreement {
	const STATE_RETURNED   = 'returned';
	const STATE_REVOKED    = 'revoked';
	const META_INSTITUTION = '_wpcpm_agr_institution';
	const META_NOTE        = '_wpcpm_agr_note';

	public static function awaiting_review( $limit = 200 ) {
		return (array) wpcpm_overview_holds( 'agreements', array() );
	}

	public static function in_state( $state, $limit = 50 ) {
		return array();
	}
}

/** Mentor requests: the open ones with their facts, and none closed. */
class WPCPM_Institution_Request {
	public static function open_requests( $limit = 20 ) {
		return array_keys( (array) wpcpm_overview_holds( 'requests', array() ) );
	}

	public static function closed_requests( $limit = 20 ) {
		return array();
	}

	public static function facts( $id ) {
		$requests = (array) wpcpm_overview_holds( 'requests', array() );

		return isset( $requests[ $id ] ) ? (array) $requests[ $id ] : array();
	}
}

/** Semester reports: those to review and the semesters due, and none approved. */
class WPCPM_Semester_Report {
	public static function queue() {
		return (array) wpcpm_overview_holds( 'drafts', array() );
	}

	public static function due( $today ) {
		return (array) wpcpm_overview_holds( 'due', array() );
	}

	public static function approved_since( $from ) {
		return array();
	}
}

/** The accounts the roster's refusal ceiling locked today. */
class WPCPM_Institution_Roster {
	public static function locked_today() {
		return (array) wpcpm_overview_holds( 'locked', array() );
	}
}

/** The pipeline index, read for the programs' figures, which the Overview does not show: no row. */
class WPCPM_Institutions_Index {
	public static function rows() {
		return array();
	}
}

/** The program map, read for the same figures: no track. */
class WPCPM_Program {
	public static function labels() {
		return array();
	}

	public static function track( $status ) {
		return '';
	}
}

/* ---- the sponsors' queues --------------------------------------------------- */

/** Sponsor posts waiting for review. */
class WPCPM_Sponsor_Posts {
	public static function pending_all( $limit = 50 ) {
		return (array) wpcpm_overview_holds( 'sponsor_posts', array() );
	}
}

/** Sponsor Collaboration Agreements: the documents waiting for review, and none out of force. */
class WPCPM_Sponsor_Agreement {
	public static function awaiting_review( $limit = 50 ) {
		return array_keys( (array) wpcpm_overview_holds( 'sponsor_agreements', array() ) );
	}

	public static function revoked_all( $limit = 50 ) {
		return array();
	}

	public static function review_facts( $id ) {
		$documents = (array) wpcpm_overview_holds( 'sponsor_agreements', array() );

		return isset( $documents[ $id ] ) ? (array) $documents[ $id ] : array();
	}
}

/** Sponsor applications waiting for a decision. */
class WPCPM_Sponsor_Application {
	public static function queue_facts( $limit = 50 ) {
		return (array) wpcpm_overview_holds( 'sponsor_applications', array() );
	}

	/** The Sponsors entry's bubble: the applications that wait. */
	public static function pending_count( $limit = 0 ) {
		return count( self::queue_facts() );
	}
}

/** Sponsors' offers: a live one is live whatever its last day, since no check dates one. */
class WPCPM_Sponsor_Offers {
	const KIND_CODES = 'codes';

	public static function all() {
		return (array) wpcpm_overview_holds( 'offers', array() );
	}

	public static function live() {
		return array_values(
			array_filter(
				self::all(),
				static function ( $offer ) {
					return 'live' === $offer['state'];
				}
			)
		);
	}

	public static function is_live( array $offer, $today = '' ) {
		return 'live' === $offer['state'];
	}
}

/** An offer's codes: how many are left, and no claim. */
class WPCPM_Sponsor_Codes {
	public static function counts( $offer_id ) {
		$codes = (array) wpcpm_overview_holds( 'codes', array() );

		return isset( $codes[ $offer_id ] ) ? (array) $codes[ $offer_id ] : array(
			'available' => 0,
			'claimed'   => 0,
			'void'      => 0,
			'total'     => 0,
		);
	}

	public static function claims( $offer_id ) {
		return array();
	}
}

/* ---- the syncs, the scan, the storage and the mail -------------------------- */

class WPCPM_Students_Sync {
	const OPT_LAST  = 'wpcpm_students_last_sync';
	const CRON_AUTO = 'wpcpm_students_daily';

	public static function progress() {
		return wpcpm_overview_progress( 'students' );
	}
}

class WPCPM_Mentors_Sync {
	const OPT_LAST   = 'wpcpm_mentors_last_sync';
	const CRON_DAILY = 'wpcpm_mentors_daily';

	public static function progress() {
		return wpcpm_overview_progress( 'mentors' );
	}

	/** The statuses the programs' figures count, which the Overview does not show: none. */
	public static function tracked_statuses() {
		return array(
			'active' => array(),
			'past'   => array(),
		);
	}

	/** The column names the Mentor Status Checker's description reads, on the Tools screen. */
	public static function fields() {
		return array(
			'mentor_name'    => 'Full Name',
			'mentor_profile' => 'WordPress profile',
			'mentor_status'  => 'Status',
		);
	}
}

class WPCPM_Institutions_Sync {
	const OPT_LAST   = 'wpcpm_institutions_last_sync';
	const CRON_DAILY = 'wpcpm_institutions_sync_daily';

	public static function progress() {
		return wpcpm_overview_progress( 'institutions' );
	}

	/** As the real one reads it: the option the run stamps when it finishes. */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}
}

class WPCPM_Sponsors_Sync {
	const OPT_LAST   = 'wpcpm_sponsors_last_sync';
	const CRON_DAILY = 'wpcpm_sponsors_daily';

	public static function progress() {
		return wpcpm_overview_progress( 'sponsors' );
	}

	/** As the real one reads it: the option the run stamps when it finishes. */
	public static function last_read() {
		return (int) get_option( self::OPT_LAST, 0 );
	}
}

/** The Student Duplicate Finder's last scan: its report, or none before the first scan. */
class WPCPM_Duplicates_Scan {
	public static function report() {
		return wpcpm_overview_holds( 'duplicates', null );
	}
}

/** The private storage probe, which has never run. */
class WPCPM_Private_Files {
	public static function probe_result() {
		return null;
	}

	public static function verdict( array $result ) {
		return 'unknown';
	}
}

/** The mail log and the invitation run: nothing sent, nothing queued. */
class WPCPM_Mail {
	public static function log() {
		return array();
	}

	public static function run() {
		return array();
	}

	public static function queued() {
		return 0;
	}
}
