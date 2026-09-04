<?php
/**
 * The semester report an institution sends out about one cohort of its students.
 *
 * This file reads, joins and stores; it draws nothing and it touches no superglobal. The
 * screen, the handlers and the print document are `WPCPM_Semester_Report_Screen`, and a test
 * asserts this file reaches none of them.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A snapshot document: generated on request, edited on the dashboard, exported by printing.
 *
 * Two rules shape everything below, and both are the reason this is a snapshot rather than a
 * live view.
 *
 * **Participation comes from the roster index, never from a live roster read.** The index is
 * the whole Students table as the last sync read it, so the rows the accounts side never pages
 * (171 `Not moving forward` rows across the program) are counted. A report that quietly counted
 * only the students who happen to have signed in here would tell a university it sent fewer
 * people than it did.
 *
 * **The snapshot contains nothing the institution could not print.** No email address, no
 * status beside a name, no hours, no grade, no accessibility disclosure. `bin/test-semester-
 * report.php` walks `_wpcpm_report_data` recursively and asserts that no array key is `email`,
 * `email_key`, `status`, `accessibility`, `hours` or `grade`, and that no string value passes
 * `is_email()`. Anything added to the snapshot has to survive that walk, which is why a student
 * is identified in it by a hash of their address and never by the address.
 *
 * **What is deliberately never generated**, each named here once and again beside the code that
 * leaves it out:
 *
 * - **Hours and targets.** Decision 23 of the design spec: the institution's own credit-bearing
 *   paperwork is a different document with a different signature on it, and putting an hours
 *   figure into a document a school publishes turns a mentoring log into a transcript.
 * - **Grades.** The same reason, without the ambiguity.
 * - **Any feedback rating, even aggregated.** An average over ten students is a disclosure about
 *   ten students, and the ten did not agree to it: what they released is the passage they wrote.
 * - **`Contribution Project Summary`.** Free text a student wrote for their mentor, not for
 *   publication; the released half of that answer is the quote.
 * - **Mentor names.** A mentor is a volunteer who agreed to mentor, not to be listed in a
 *   university's semester report.
 *
 * All static, no state. Every decision that turns rows into a document lives in a pure function
 * that takes arrays and returns arrays (`shape_reports_rows()`, `shape_feedback_rows()`,
 * `assemble()`, `consent_view()`), so the joining rules can be driven from fixtures without a
 * WordPress install or a network.
 */
final class WPCPM_Semester_Report {

	/**
	 * The post that holds one institution's report for one cohort.
	 *
	 * Seventeen characters. `register_post_type()` refuses a name over twenty and returns a
	 * `WP_Error` that nothing reads, so an over-long name is a type that silently does not
	 * exist while `get_posts()` goes on querying it; `bin/test-roles.php` measures every one
	 * this plugin declares.
	 *
	 * @var string
	 */
	const POST_TYPE = 'wpcpm_inst_report';

	/**
	 * The institution the report is about. The policy's input, and the queryable key.
	 *
	 * Read from here for the whole life of the report and never from a form, so a save posted
	 * with another school's ID in it still decides against the institution the report was
	 * generated for.
	 *
	 * @var string
	 */
	const META_INSTITUTION = '_wpcpm_report_institution';

	/** The cohort key the report covers, as `WPCPM_Cohort` spells it. */
	const META_COHORT = '_wpcpm_report_cohort';

	/** The snapshot. See `assemble()` for its shape. */
	const META_DATA = '_wpcpm_report_data';

	/** Section key => `array( 'text' => string, 'hidden' => bool )`. Revisioned. */
	const META_SECTIONS = '_wpcpm_report_sections';

	/** Quote id => `array( 'include' => bool, 'translation' => string, 'show_name' => bool )`. Revisioned. */
	const META_CHOICES = '_wpcpm_report_choices';

	/** When the snapshot was last generated, as a Unix time. */
	const META_GENERATED = '_wpcpm_report_generated';

	/** When the print document was last opened, as a Unix time. */
	const META_EXPORTED = '_wpcpm_report_exported';

	/** Prefix for the two cached Airtable reads. */
	const CACHE_PREFIX = 'wpcpm_report_';

	/**
	 * How long a read of Students Reports or Feedback is cached.
	 *
	 * Five minutes, the same window `WPCPM_Student_Feedback` uses for the same table. Consent
	 * is re-checked on every render, so this number is the longest a withdrawal can take to
	 * reach a document that is being looked at; a student's own save clears the cache through
	 * `forget()`, so in practice it is immediate and this is only the ceiling.
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	/** The longest narrative or translation this document will store, per field. */
	const MAX_TEXT = 5000;

	/**
	 * Addresses per Airtable read.
	 *
	 * Fifty, matching the import's ladder: a `filterByFormula` is a URL, and an OR of a
	 * hundred `LOWER({Email}) = '...'` tests is long enough to meet a server's limit.
	 *
	 * @var int
	 */
	const CHUNK = 50;

	/**
	 * The snapshot's shape version.
	 *
	 * A stored snapshot at another version reads as absent rather than being misread, the same
	 * rule the roster index and the pipeline index follow. A document drawn from a shape this
	 * code does not know would be a document with the wrong things in it, which is worse than
	 * a page that says the report has to be generated again.
	 *
	 * @var int
	 */
	const VERSION = 1;

	/** `draft` or `approved`. Version 2 of the vocabulary; `maybe_upgrade()` flips `final`. */
	const META_STATE = '_wpcpm_report_state';
	/**
	 * Who approved the report and when: `array( 'at' => int, 'by' => int )`.
	 *
	 * `by` is 0 for a report that was `final` before approval existed and was flipped by
	 * `maybe_upgrade()`: nobody pressed Approve on it, and a stamp naming the upgrading
	 * request's user would be a claim about a person. Deleted on reopen.
	 *
	 * @var string
	 */
	const META_APPROVED = '_wpcpm_report_approved';
	/**
	 * How the report came to exist: `auto` (the daily job) or `manager` (a press).
	 *
	 * Written once, at the first generation, and never by a regeneration: the log answers
	 * "did the site draft this or did somebody" and a regeneration is neither.
	 *
	 * @var string
	 */
	const META_ORIGIN = '_wpcpm_report_origin';
	/** Rows still in progress when the job drafted it, so the notice can say how late the cohort ran. */
	const META_IN_PROGRESS = '_wpcpm_report_in_progress';
	/** The daily job drafted it. */
	const ORIGIN_AUTO = 'auto';
	/** A program manager pressed Draft now, or generated it on the Institution Dashboard. */
	const ORIGIN_MANAGER = 'manager';
	/** Being written. Everything may be edited, generated and regenerated. Invisible to the institution. */
	const STATE_DRAFT = 'draft';
	/**
	 * Approved by a program manager. The institution reads and prints it.
	 *
	 * Replaces `final` (design of 4 September 2026, decision 1): the institution no longer
	 * writes the document, so "final" stopped meaning "the school has stopped writing" and
	 * started meaning "the program has said this may be read". Only a reopen changes it;
	 * consent is still re-read on every render, so a withdrawal still reaches it.
	 */
	const STATE_APPROVED = 'approved';

	/**
	 * The Feedback column holding the student's release of their name and links.
	 *
	 * Spelled exactly as the base spells it, from `bin/fixtures/feedback-table-fields.json`,
	 * which is the authority: a column name off by one character reads as an unanswered
	 * question, and an unanswered question here means every student is withheld and the
	 * report comes out empty with every line of code looking correct.
	 *
	 * @var string
	 */
	const FIELD_LIST = 'F3 - Report: my institution may list me in its semester report';

	/** The Feedback column holding the student's release of their words. */
	const FIELD_QUOTE_PERMISSION = 'F3 - Report: my institution may quote my feedback in its semester report';

	/**
	 * The Feedback column holding the words themselves.
	 *
	 * **Not the column whose label asks about sharing a quote publicly.** That one reads like
	 * the obvious source and is empty on all 834 rows of the table (design spec section 3): no
	 * student has ever written into it, and its name is deliberately not spelled out here, so
	 * that a search of this file for it finds nothing. Every quote in the reference report is recognisably an answer
	 * to the question below, which is filled on 289 rows, and the permission a student gives
	 * is about *their feedback*, so this is the feedback it releases. A reader who "corrects"
	 * this back to the quoting column gets a Student Feedback section that generates empty for
	 * every institution, with every line of code looking right.
	 */
	const FIELD_QUOTE = 'F3 - One example of a contribution you are proud of';

	/** The Feedback column linking a row to an institution. */
	const FIELD_INSTITUTION = 'Institution';

	/** `FIELD_LIST`: list me under my own name. */
	const LIST_NAMED = 'Yes, with my name';

	/** `FIELD_LIST`: list me under my blog address and not my name. */
	const LIST_BLOG = 'Yes, by my blog address only';

	/** `FIELD_QUOTE_PERMISSION`: quote me under my own name. */
	const QUOTE_NAMED = 'Yes, with my name';

	/** `FIELD_QUOTE_PERMISSION`: quote me, without my name. */
	const QUOTE_ANONYMOUS = 'Yes, without my name';

	/** Both columns: no. Spelled once, so no branch can compare against a different string. */
	const ANSWER_NO = 'No';

	/**
	 * Characters of `wp_hash()` kept as a student's id in the document.
	 *
	 * Twelve hex characters. The id is not a secret and is not meant to resist anything: it
	 * exists so a stored document can be matched back to a live Feedback row by hashing that
	 * row's address, and so the address itself never has to be written down.
	 *
	 * @var int
	 */
	const ID_LENGTH = 12;

	/**
	 * The option holding one counter per institution, used to expire that institution's caches.
	 *
	 * A cache key names the addresses that were asked about, so there is no single key to
	 * delete when a student changes their answer. Bumping a counter the key is built from
	 * expires every cached read for that institution in one write, whatever cohort it was for.
	 *
	 * @var string
	 */
	const OPT_EPOCH = 'wpcpm_report_epoch';

	/** The version of the state vocabulary this site's reports are written in. */
	const OPT_STATE_VERSION = 'wpcpm_report_state_version';
	/** Version 2 renamed `final` to `approved`; `maybe_upgrade()` flips the rows once. */
	const STATE_VERSION = 2;
	/**
	 * The day the drafting job was installed, as `Y-m-d`.
	 *
	 * Nothing whose semester window closed before this day is drafted by the job: the first
	 * run on a site with forty institutions and two years of rosters would otherwise draft
	 * eighty reports nobody asked for. A manager who wants an older one presses Draft now.
	 *
	 * @var string
	 */
	const OPT_AUTODRAFT_SINCE = 'wpcpm_report_autodraft_since';
	/** The log of drafts, approvals and reopenings, newest first, capped at LOG_MAX. */
	const OPT_LOG = 'wpcpm_report_log';
	/** Entries the log keeps. */
	const LOG_MAX = 200;
	/** Log events. */
	const LOG_DRAFTED      = 'drafted';
	const LOG_DRAFT_FAILED = 'draft_failed';
	const LOG_APPROVED     = 'approved';
	const LOG_REOPENED     = 'reopened';

	/*
	 * --------------------------------------------------------------------
	 * Registration
	 * --------------------------------------------------------------------
	 */

	/**
	 * Hooks.
	 */
	public static function init() {
		// Before the post type, on purpose: the flip queries by post type string and needs no
		// registration, and running first means every later hook of this request sees the
		// new vocabulary.
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register the report post type.
	 *
	 * Invisible everywhere, like the audit log, the import batches and the requests: not
	 * public, not queryable, not in REST, not in search, no admin UI, and mapped to a
	 * capability type nothing is granted. A draft report names students who agreed to appear
	 * in a document their university sends out, which is not the same as agreeing to be one
	 * URL guess away from anybody.
	 *
	 * `revisions` is supported because two people at one institution may both edit this
	 * (decision 13 puts several equal accounts on one record) and because a school that
	 * overwrote a paragraph needs it back. `editor` is supported so `post_content` carries a
	 * plain-text rendering of the narrative and the revision diff screen shows words rather
	 * than a serialised blob.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Semester reports', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Semester report', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
				'capability_type'     => array( 'wpcpm_inst_report', 'wpcpm_inst_reports' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register the two edited meta keys so revisions carry them.
	 *
	 * `revisions_enabled` landed in WordPress 6.4, inside this plugin's 6.5 floor. Without it
	 * a restore would bring back the title and the plain-text body and leave the sections and
	 * the quote choices at whatever the newest save left them, which is a restore that looks
	 * like it worked and did not.
	 *
	 * Not in REST and not editable through the meta API: the one route to either value is
	 * `save()`, which is where the cap on the text and the rule about which quote ids may be
	 * touched live. `auth_callback` is explicit rather than left to the default for protected
	 * keys, so the answer is written down instead of inherited.
	 */
	public static function register_meta() {
		foreach ( array( self::META_SECTIONS, self::META_CHOICES ) as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => false,
					'revisions_enabled' => true,
					'auth_callback'     => '__return_false',
				)
			);
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * The eight sections
	 * --------------------------------------------------------------------
	 */

	/**
	 * The eight sections, in the order they are printed.
	 *
	 * `generated` says whether the section draws anything out of the snapshot; `narrative` says
	 * whether the institution may write under it. Every section takes a narrative, because
	 * every one of them is a place a university may want to say something in its own voice;
	 * four arrive with wording already in the box, because a blank page is the reason a report
	 * never gets written. The four defaults are prose a university could send out unedited, and
	 * they are deliberately modest about what the program claims.
	 *
	 * The keys are stored in `META_SECTIONS` and in `META_CHOICES` is nothing of the sort, so a
	 * key here is a stable identifier: renaming one orphans a school's writing.
	 *
	 * @return array<string, array{title: string, generated: bool, narrative: bool, default: string}>
	 */
	public static function sections() {
		return array(
			'overview'      => array(
				'title'     => __( 'Program Overview', 'wpcredits-program-manager' ),
				'generated' => false,
				'narrative' => true,
				'default'   => __( 'The WordPress Credits Program places students inside the WordPress open source project for a full semester. Each student is paired with an experienced contributor who mentors them week by week, works through the Learn WordPress course with them, and helps them settle on a contribution team and a project. The work is done in public, alongside volunteers from around the world, and what our students produce stays in the project after the term ends. This report describes one semester of that work.', 'wpcredits-program-manager' ),
			),
			'participation' => array(
				'title'     => __( 'Participation', 'wpcredits-program-manager' ),
				'generated' => true,
				'narrative' => true,
				'default'   => '',
			),
			'teams'         => array(
				'title'     => __( 'Contribution Teams', 'wpcredits-program-manager' ),
				'generated' => true,
				'narrative' => true,
				'default'   => '',
			),
			'projects'      => array(
				'title'     => __( 'Student Projects and Blogs', 'wpcredits-program-manager' ),
				'generated' => true,
				'narrative' => true,
				'default'   => __( 'Every student listed below asked to appear here. The links go to work they published themselves during the semester: the site each of them built, the posts they wrote as they went along, and the post they closed the term with. Students who preferred not to appear are not named anywhere in this report, and nothing on this page is an assessment of anybody.', 'wpcredits-program-manager' ),
			),
			'recognition'   => array(
				'title'     => __( 'Recognition and Events', 'wpcredits-program-manager' ),
				'generated' => true,
				'narrative' => true,
				'default'   => '',
			),
			'continuing'    => array(
				'title'     => __( 'Continuing Engagement', 'wpcredits-program-manager' ),
				'generated' => false,
				'narrative' => true,
				'default'   => '',
			),
			'feedback'      => array(
				'title'     => __( 'Student Feedback', 'wpcredits-program-manager' ),
				'generated' => true,
				'narrative' => true,
				'default'   => __( 'At the end of the term we ask our students what the semester was actually like. Each passage below is printed with that student\'s permission and in the words they wrote themselves; where a student answered in another language, our translation follows underneath. Nothing has been shortened or corrected.', 'wpcredits-program-manager' ),
			),
			'ahead'         => array(
				'title'     => __( 'Looking Ahead', 'wpcredits-program-manager' ),
				'generated' => false,
				'narrative' => true,
				'default'   => __( 'We intend to run the program again next semester. Students who have finished are welcome to carry on contributing on their own, and their mentors stay in touch with those who want to. Colleagues who would like to send students, host a contribution session, or simply hear how the term went are very welcome to ask.', 'wpcredits-program-manager' ),
			),
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Finding and reading a report
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report for one institution and one cohort, or null.
	 *
	 * One post per institution per cohort, which is what makes `?wpcpm_report=2026-H1` on the
	 * dashboard an address rather than a search. Both values are shape-checked first, so a
	 * pasted string opens nothing: an empty meta value would otherwise match every report whose
	 * own value is empty, which is the shape of every fence bug in this module's history.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 * @param string $cohort      A `WPCPM_Cohort` key.
	 * @return WP_Post|null
	 */
	public static function find( $institution, $cohort ) {
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) || ! WPCPM_Cohort::is_key( $cohort ) ) {
			return null;
		}

		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => self::META_INSTITUTION,
						'value' => $institution,
					),
					array(
						'key'   => self::META_COHORT,
						'value' => (string) $cohort,
					),
				),
			)
		);

		return empty( $found[0] ) ? null : $found[0];
	}

	/**
	 * The stored snapshot, normalised, or an empty array at a version this code does not know.
	 *
	 * Every key is present and typed on the way out, so a renderer never has to test whether
	 * the document it was handed has a `teams` key. An empty array is the one answer a caller
	 * has to branch on, and it means "this has to be generated again".
	 *
	 * @param WP_Post $post The report.
	 * @return array The snapshot, or `array()`.
	 */
	public static function snapshot( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_DATA, true );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return array();
		}

		return self::shape_snapshot( $stored );
	}

	/**
	 * Which of the two states a report is in.
	 *
	 * A report whose state meta was lost or never written is editable, which is the safe
	 * direction: the other reading would lock a school out of its own document with no way
	 * back. Any other stored value comes back unchanged rather than folded into `draft`: a
	 * row `maybe_upgrade()` has not yet reached still reads as `final` here, and only the
	 * upgrade decides when that changes. A comparison against `approved` still treats such a
	 * row as a draft, because nothing but the exact word matches.
	 *
	 * @param WP_Post $post The report.
	 * @return string `draft`, `approved`, or a value only a not-yet-upgraded row still carries.
	 */
	public static function state( WP_Post $post ) {
		$state = (string) get_post_meta( (int) $post->ID, self::META_STATE, true );

		return '' === $state ? self::STATE_DRAFT : $state;
	}

	/**
	 * The institution a report is about, as the post itself records it.
	 *
	 * @param WP_Post $post The report.
	 * @return string A record ID, or an empty string.
	 */
	public static function institution_of( WP_Post $post ) {
		$record = trim( (string) get_post_meta( (int) $post->ID, self::META_INSTITUTION, true ) );

		return WPCPM_Mentors_Sync::is_record_id( $record ) ? $record : '';
	}

	/**
	 * The cohort a report covers, as the post itself records it.
	 *
	 * @param WP_Post $post The report.
	 * @return string A cohort key, or an empty string.
	 */
	public static function cohort_of( WP_Post $post ) {
		$cohort = (string) get_post_meta( (int) $post->ID, self::META_COHORT, true );

		return WPCPM_Cohort::is_key( $cohort ) ? $cohort : '';
	}

	/**
	 * When the snapshot was generated, as a Unix time.
	 *
	 * @param WP_Post $post The report.
	 * @return int
	 */
	public static function generated_at( WP_Post $post ) {
		return (int) get_post_meta( (int) $post->ID, self::META_GENERATED, true );
	}

	/**
	 * When the print document was last opened, as a Unix time, or 0.
	 *
	 * @param WP_Post $post The report.
	 * @return int
	 */
	public static function exported_at( WP_Post $post ) {
		return (int) get_post_meta( (int) $post->ID, self::META_EXPORTED, true );
	}

	/**
	 * The narratives, every section present, whatever the stored value looks like.
	 *
	 * A section the stored value does not carry takes its default, which is what makes adding a
	 * ninth section to `sections()` safe: existing reports grow the new card with its wording in
	 * it rather than an empty box. A section the stored value carries and `sections()` no longer
	 * knows is dropped here, so a renamed key cannot print under a heading that does not exist.
	 *
	 * @param WP_Post $post The report.
	 * @return array<string, array{text: string, hidden: bool}>
	 */
	public static function narratives( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SECTIONS, true );
		$stored = is_array( $stored ) ? $stored : array();

		return self::shape_narratives( $stored );
	}

	/**
	 * The quote choices, keyed by quote id.
	 *
	 * @param WP_Post $post The report.
	 * @return array<string, array{include: bool, translation: string, show_name: bool}>
	 */
	public static function choices( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_CHOICES, true );

		return self::shape_choices( is_array( $stored ) ? $stored : array() );
	}

	/*
	 * --------------------------------------------------------------------
	 * Writing
	 * --------------------------------------------------------------------
	 */

	/**
	 * Store the narratives and the quote choices, and rebuild the plain-text body.
	 *
	 * **The meta is written before the post, and that order is load-bearing.** WordPress saves
	 * a revision from inside `wp_update_post()`, and `revisions_enabled` meta is copied out of
	 * the post's *current* meta at that moment; writing the post first would file every
	 * revision one save behind, so a restore would bring back the previous edit's text.
	 *
	 * Both values are re-shaped rather than trusted: the cap, the boolean casts and the drop of
	 * unknown keys happen here, once, so no handler carries a second copy of them.
	 *
	 * @param WP_Post $post       The report.
	 * @param array   $narratives Section key => `text`, `hidden`. Unknown keys are dropped.
	 * @param array   $choices    Quote id => `include`, `translation`, `show_name`.
	 * @return int|WP_Error The post ID, or whatever `wp_update_post()` refused with.
	 */
	public static function save( WP_Post $post, array $narratives, array $choices ) {
		$post_id    = (int) $post->ID;
		$narratives = self::shape_narratives( $narratives );

		update_post_meta( $post_id, self::META_SECTIONS, $narratives );
		update_post_meta( $post_id, self::META_CHOICES, self::shape_choices( $choices ) );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				// The narrative as words, so the revision diff screen shows a paragraph that
				// changed instead of two serialised arrays. Nothing reads it back.
				'post_content' => self::narrative_text( $narratives ),
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : $post_id;
	}

	/**
	 * Move a report between draft and approved.
	 *
	 * @param WP_Post $post  The report.
	 * @param string  $state `draft` or `approved`.
	 * @return bool Whether the state was written.
	 */
	public static function set_state( WP_Post $post, $state ) {
		if ( self::STATE_DRAFT !== $state && self::STATE_APPROVED !== $state ) {
			return false;
		}

		update_post_meta( (int) $post->ID, self::META_STATE, $state );

		return true;
	}

	/**
	 * Approve a report: the state, and who did it when.
	 *
	 * @param WP_Post $post    The report.
	 * @param int     $user_id The manager pressing Approve; 0 for nobody.
	 * @return bool
	 */
	public static function approve( WP_Post $post, $user_id ) {
		if ( ! self::set_state( $post, self::STATE_APPROVED ) ) {
			return false;
		}

		update_post_meta(
			(int) $post->ID,
			self::META_APPROVED,
			array(
				'at' => time(),
				'by' => max( 0, (int) $user_id ),
			)
		);

		return true;
	}

	/**
	 * Make an approved report a draft again. The stamp goes with the state, so a reopened
	 * report never says who approved a version that no longer exists.
	 *
	 * @param WP_Post $post The report.
	 * @return bool
	 */
	public static function reopen( WP_Post $post ) {
		if ( ! self::set_state( $post, self::STATE_DRAFT ) ) {
			return false;
		}

		delete_post_meta( (int) $post->ID, self::META_APPROVED );

		return true;
	}

	/**
	 * When and by whom a report was approved, or an empty array for a draft.
	 *
	 * @param WP_Post $post The report.
	 * @return array `array()` or `array( 'at' => int, 'by' => int )`.
	 */
	public static function approved_at( WP_Post $post ) {
		$stamp = get_post_meta( (int) $post->ID, self::META_APPROVED, true );

		if ( ! is_array( $stamp ) || empty( $stamp['at'] ) ) {
			return array();
		}

		return array(
			'at' => (int) $stamp['at'],
			'by' => isset( $stamp['by'] ) ? max( 0, (int) $stamp['by'] ) : 0,
		);
	}

	/**
	 * How the report came to exist. Anything but `auto` reads as a manager's, including
	 * the empty value every report written before origins existed carries.
	 *
	 * @param WP_Post $post The report.
	 * @return string ORIGIN_AUTO or ORIGIN_MANAGER.
	 */
	public static function origin_of( WP_Post $post ) {
		return self::ORIGIN_AUTO === (string) get_post_meta( (int) $post->ID, self::META_ORIGIN, true )
			? self::ORIGIN_AUTO
			: self::ORIGIN_MANAGER;
	}

	/**
	 * Rows still in progress when the job drafted the report; 0 for a report drafted by hand.
	 *
	 * @param WP_Post $post The report.
	 * @return int
	 */
	public static function in_progress_of( WP_Post $post ) {
		return max( 0, (int) get_post_meta( (int) $post->ID, self::META_IN_PROGRESS, true ) );
	}

	/**
	 * Record that the print document was opened.
	 *
	 * @param WP_Post $post The report.
	 */
	public static function mark_exported( WP_Post $post ) {
		update_post_meta( (int) $post->ID, self::META_EXPORTED, time() );
	}

	/**
	 * Remove every report on uninstall.
	 *
	 * These posts hold students' own words and the names of students who agreed to appear in
	 * one university's document. Left behind by an uninstall the plugin would be gone and the
	 * people would still be here, which is the line `uninstall.php` draws everywhere else. The
	 * students themselves are records in Airtable and accounts on this site, and both are
	 * people rather than plugin state, so neither is touched.
	 *
	 * @return int How many reports were removed.
	 */
	public static function delete_all() {
		$removed = 0;

		// An explicit list and not `'any'`: `any` means every status not excluded from search,
		// and `trash` is excluded from search, so a report somebody had trashed by hand stayed
		// in the database after the plugin was gone, with its students' names and words in it.
		// This post type is the one holding released quotes, which is why it matters here more
		// than on the other private types that share the idiom.
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $found as $post_id ) {
			if ( wp_delete_post( (int) $post_id, true ) ) {
				++$removed;
			}
		}

		delete_option( self::OPT_EPOCH );
		delete_option( self::OPT_LOG );
		delete_option( self::OPT_STATE_VERSION );
		delete_option( self::OPT_AUTODRAFT_SINCE );

		return $removed;
	}

	/**
	 * Flip every `final` report to `approved`, once, and record the day the job arrived.
	 *
	 * Runs on `init` at priority 5 for sites updated by dropping in files, the way the roles
	 * and settings upgrades do. Idempotent: the version option is what makes it once.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_STATE_VERSION ) >= self::STATE_VERSION ) {
			return;
		}

		// Every status, for the reason delete_all() gives: a trashed report is still a report,
		// and a `final` one left in the trash would read as a third state nothing knows.
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_STATE,
						'value' => 'final',
					),
				),
			)
		);

		foreach ( $found as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_APPROVED );

			// `by` 0: nobody pressed Approve. `at` is the last edit, the closest fact to "when
			// it was marked final" a version-1 row holds.
			update_post_meta(
				(int) $post->ID,
				self::META_APPROVED,
				array(
					'at' => (int) get_post_modified_time( 'U', true, $post ),
					'by' => 0,
				)
			);
		}

		// `add_option()` and not `update_option()`: the day the job was installed is a fact
		// about this site that a later upgrade must not move.
		add_option( self::OPT_AUTODRAFT_SINCE, wp_date( 'Y-m-d' ), '', false );

		update_option( self::OPT_STATE_VERSION, self::STATE_VERSION );
	}

	/**
	 * Record a draft, an approval, a reopening or a failed draft.
	 *
	 * Two facts a sentence may need travel with the entry and nothing else: no prose from
	 * the report, no address, no name. A reason is cut to 200 characters because it is
	 * Airtable's own message and is only there to say which read failed.
	 *
	 * @param string $event       One of the LOG_* values.
	 * @param string $institution Institutions record ID.
	 * @param string $cohort      Cohort key.
	 * @param int    $actor       The account, or 0 for the job.
	 * @param array  $extra       Optional `in_progress` (int) and `why` (string).
	 */
	public static function log( $event, $institution, $cohort, $actor, array $extra = array() ) {
		$entries = get_option( self::OPT_LOG );
		$entries = is_array( $entries ) ? $entries : array();

		$entry = array(
			'event'       => (string) $event,
			'institution' => trim( (string) $institution ),
			'cohort'      => (string) $cohort,
			'actor'       => max( 0, (int) $actor ),
			'at'          => time(),
		);

		if ( isset( $extra['in_progress'] ) ) {
			$entry['in_progress'] = max( 0, (int) $extra['in_progress'] );
		}

		if ( isset( $extra['why'] ) ) {
			$entry['why'] = mb_substr( sanitize_text_field( (string) $extra['why'] ), 0, 200 );
		}

		array_unshift( $entries, $entry );

		update_option( self::OPT_LOG, array_slice( $entries, 0, self::LOG_MAX ), false );
	}

	/**
	 * The log, newest first.
	 *
	 * @return array[]
	 */
	public static function log_entries() {
		$entries = get_option( self::OPT_LOG );

		return is_array( $entries ) ? array_values( $entries ) : array();
	}

	/*
	 * --------------------------------------------------------------------
	 * What the job and the Administrator Dashboard read
	 * --------------------------------------------------------------------
	 */

	/**
	 * The institution cohorts a draft is owed for (design section 5.1).
	 *
	 * Due when all five hold: the institution is in an active stage and has roster rows in
	 * the cohort; the cohort is a semester (not NONE) whose window closed before today; the
	 * window closed on or after the since-date; no report exists for the pair; and either
	 * every row is finished or the window closed at least the grace ago. One function, read
	 * by the cron and by the screens alike, so they cannot disagree about what "due" means.
	 *
	 * Dates are compared as `Y-m-d` strings, the cohort class's rule: a day boundary is a
	 * calendar fact, and a timestamp would pin it to one timezone's midnight.
	 *
	 * @param string $today Today as `Y-m-d`.
	 * @return array[] `institution`, `cohort`, `in_progress`, `window_end`; oldest window first.
	 */
	public static function due( $today ) {
		$today = (string) $today;

		// Pattern and checkdate(), the cohort class's rule: "2026-13-45" matches the pattern
		// and would reach the day count below with a meaningless answer.
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $today, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return array();
		}

		$active = (array) WPCPM_Settings::get_value( 'institution_active_stages', array() );
		$past   = (array) WPCPM_Settings::get_value( 'past_statuses', array() );
		$grace  = max( 0, (int) WPCPM_Settings::get_value( 'report_autodraft_grace_days', 45 ) );
		$since  = (string) get_option( self::OPT_AUTODRAFT_SINCE, '' );
		$due    = array();

		// An empty stage list is nobody active, not everybody: the sync reads the same list
		// as the definition of the pipeline, and a report drafted for an institution the
		// pipeline does not count is a report about nobody's partner.
		if ( empty( $active ) ) {
			return array();
		}

		foreach ( WPCPM_Institutions_Index::rows() as $institution ) {
			if ( ! is_array( $institution ) || empty( $institution['record_id'] ) ) {
				continue;
			}

			$record = (string) $institution['record_id'];
			$stage  = isset( $institution['stage'] ) ? trim( (string) $institution['stage'] ) : '';

			if ( ! in_array( $stage, $active, true ) ) {
				continue;
			}

			$by_cohort = array();

			foreach ( WPCPM_Roster_Index::rows( $record ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$status = isset( $row['status'] ) ? trim( (string) $row['status'] ) : '';

				if ( in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
					continue;
				}

				$key = WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' );

				if ( WPCPM_Cohort::NONE === $key ) {
					continue;
				}

				if ( ! isset( $by_cohort[ $key ] ) ) {
					$by_cohort[ $key ] = 0;
				}

				// The leading Y-m-d and nothing else, the guard WPCPM_Cohort::key() applies to
				// `start`: "2026-06-30 00:00:00" compares as later than "2026-06-30" byte for
				// byte, so a datetime would keep a finished row in progress on its own last
				// day. A value with no date in it reads as unknown, and unknown is in progress:
				// the job waits rather than drafting over somebody who may not be finished.
				$end = isset( $row['end'] ) ? trim( (string) $row['end'] ) : '';
				$end = preg_match( '/^(\d{4}-\d{2}-\d{2})/', $end, $found ) ? $found[1] : '';

				if ( ! in_array( $status, $past, true ) && ( '' === $end || $end > $today ) ) {
					++$by_cohort[ $key ];
				}
			}

			foreach ( $by_cohort as $key => $in_progress ) {
				$range = WPCPM_Cohort::range( $key );
				$end   = $range['to'];

				if ( '' === $end || $end >= $today ) {
					continue;
				}

				if ( '' !== $since && $end < $since ) {
					continue;
				}

				if ( self::find( $record, $key ) instanceof WP_Post ) {
					continue;
				}

				$days = (int) floor( ( strtotime( $today . ' 00:00:00 UTC' ) - strtotime( $end . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );

				if ( $in_progress > 0 && $days < $grace ) {
					continue;
				}

				$due[] = array(
					'institution' => $record,
					'cohort'      => (string) $key,
					'in_progress' => (int) $in_progress,
					'window_end'  => $end,
				);
			}
		}

		usort(
			$due,
			static function ( $a, $b ) {
				$order = strcmp( $a['window_end'], $b['window_end'] );

				return 0 !== $order ? $order : strcmp( $a['institution'], $b['institution'] );
			}
		);

		return $due;
	}

	/**
	 * Every draft, oldest generated first: what the Administrator Dashboard lists to review.
	 *
	 * @return array[]
	 */
	public static function queue() {
		return self::rows_in_state( self::STATE_DRAFT, 0 );
	}

	/**
	 * Every report approved at or after a moment, oldest generated first.
	 *
	 * @param int $timestamp Unix time; 0 for every approved report.
	 * @return array[]
	 */
	public static function approved_since( $timestamp ) {
		return self::rows_in_state( self::STATE_APPROVED, (int) $timestamp );
	}

	/**
	 * Plain rows for one state. No prose, no snapshot: the readers of this list draw a table.
	 *
	 * @param string $state          STATE_DRAFT or STATE_APPROVED.
	 * @param int    $approved_after Keep only reports approved at or after this; 0 keeps all.
	 * @return array[]
	 */
	private static function rows_in_state( $state, $approved_after ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_STATE,
						'value' => (string) $state,
					),
				),
			)
		);

		$rows = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$approved = self::approved_at( $post );

			if ( $approved_after > 0 && ( empty( $approved['at'] ) || $approved['at'] < $approved_after ) ) {
				continue;
			}

			$generated = self::generated_at( $post );

			$rows[] = array(
				'post_id'     => (int) $post->ID,
				'institution' => self::institution_of( $post ),
				'cohort'      => self::cohort_of( $post ),
				'generated'   => $generated,
				'origin'      => self::origin_of( $post ),
				'in_progress' => self::in_progress_of( $post ),
				'age_days'    => $generated > 0 ? (int) floor( ( time() - $generated ) / DAY_IN_SECONDS ) : 0,
				'approved_at' => isset( $approved['at'] ) ? (int) $approved['at'] : 0,
				'approved_by' => isset( $approved['by'] ) ? (int) $approved['by'] : 0,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				// Two reports generated in one second sort by ID, so the order is the same on
				// every read rather than whatever the query returned.
				$order = $a['generated'] <=> $b['generated'];

				return 0 !== $order ? $order : ( $a['post_id'] <=> $b['post_id'] );
			}
		);

		return $rows;
	}

	/*
	 * --------------------------------------------------------------------
	 * Generating
	 * --------------------------------------------------------------------
	 */

	/**
	 * Generate or regenerate one institution's report for one cohort.
	 *
	 * **Any `WP_Error` aborts the whole thing and comes back with its message unchanged.** A
	 * report with Participation filled in and Projects empty because one read failed looks
	 * finished, and a school would send it. So nothing is written until every read has come
	 * back, and the sentence the caller shows is the one Airtable gave.
	 *
	 * A regeneration keeps what the institution wrote: the narratives are theirs, and the only
	 * thing thrown away is a quote choice whose quote is no longer in the document. It is
	 * refused on an `approved` report, because regenerating one would rewrite a document that has
	 * been issued; reopening it is a deliberate act with a button of its own.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 * @param string $cohort      A `WPCPM_Cohort` key.
	 * @param string $origin      ORIGIN_AUTO or ORIGIN_MANAGER; ORIGIN_MANAGER by default.
	 * @return int|WP_Error The report's post ID.
	 */
	public static function generate( $institution, $cohort, $origin = self::ORIGIN_MANAGER ) {
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return new WP_Error( 'wpcpm_report_no_institution', __( 'That institution could not be identified.', 'wpcredits-program-manager' ) );
		}

		// NONE is a key and is deliberately not a report: "No start date" is a bucket on the
		// roster for rows whose date could not be read, not a semester a university reports on.
		if ( ! WPCPM_Cohort::is_key( $cohort ) || WPCPM_Cohort::NONE === $cohort ) {
			return new WP_Error( 'wpcpm_report_no_cohort', __( 'That is not a semester this report can be generated for.', 'wpcredits-program-manager' ) );
		}

		$post = self::find( $institution, $cohort );

		if ( $post instanceof WP_Post && self::STATE_APPROVED === self::state( $post ) ) {
			return new WP_Error(
				'wpcpm_report_approved',
				__( 'This report has been approved. Reopen it before generating it again.', 'wpcredits-program-manager' )
			);
		}

		$built = self::build( $institution, $cohort );

		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$built['generated'] = time();

		return self::store( $post, $institution, $cohort, $built, $origin );
	}

	/**
	 * Read everything one report is made of and assemble the snapshot.
	 *
	 * Split out of `generate()` because `refresh_consent()` needs exactly the same reads: one
	 * place that knows which columns are asked for is one place to get that wrong.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 * @param string $cohort      A `WPCPM_Cohort` key.
	 * @return array|WP_Error The snapshot, or the first error any read returned.
	 */
	private static function build( $institution, $cohort ) {
		$envelope = WPCPM_Roster_Index::read( $institution );
		$emails   = self::cohort_emails( $envelope['rows'], $cohort );

		$reports = self::read_reports( $institution, $emails );

		if ( is_wp_error( $reports ) ) {
			return $reports;
		}

		$feedback = self::read_feedback( $institution, $emails );

		if ( is_wp_error( $feedback ) ) {
			return $feedback;
		}

		return self::assemble(
			array(
				'institution' => $institution,
				'cohort'      => $cohort,
				'read'        => $envelope['read'],
				'rows'        => $envelope['rows'],
				'reports'     => $reports,
				'feedback'    => $feedback,
			)
		);
	}

	/**
	 * Write a snapshot to a new report post, or over an existing one.
	 *
	 * @param WP_Post|null $post        The existing report, or null.
	 * @param string       $institution Airtable Institutions record ID.
	 * @param string       $cohort      A `WPCPM_Cohort` key.
	 * @param array        $snapshot    The assembled snapshot.
	 * @param string       $origin      ORIGIN_AUTO or ORIGIN_MANAGER; only written for a new report.
	 * @return int|WP_Error The post ID.
	 */
	private static function store( $post, $institution, $cohort, array $snapshot, $origin = self::ORIGIN_MANAGER ) {
		$title = self::title_for( $institution, $cohort );

		if ( $post instanceof WP_Post ) {
			$post_id  = (int) $post->ID;
			$previous = self::snapshot( $post );

			update_post_meta( $post_id, self::META_DATA, $snapshot );
			update_post_meta( $post_id, self::META_GENERATED, (int) $snapshot['generated'] );
			update_post_meta(
				$post_id,
				self::META_CHOICES,
				self::prune_choices( self::choices( $post ), $snapshot['quotes'], isset( $previous['quotes'] ) ? $previous['quotes'] : array() )
			);

			// The title is rewritten because an institution can be renamed in Airtable, and the
			// post's modified time moves because a regeneration changes the document under
			// anybody who has the editing screen open: that is exactly what the stale-save
			// check on `post_modified_gmt` is there to notice.
			$updated = wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $title,
				),
				true
			);

			return is_wp_error( $updated ) ? $updated : $post_id;
		}

		$narratives = self::shape_narratives( array() );

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_content' => self::narrative_text( $narratives ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! $post_id ) {
			return new WP_Error( 'wpcpm_report_not_created', __( 'The report could not be created.', 'wpcredits-program-manager' ) );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_INSTITUTION, $institution );
		update_post_meta( $post_id, self::META_COHORT, $cohort );
		update_post_meta( $post_id, self::META_DATA, $snapshot );
		update_post_meta( $post_id, self::META_SECTIONS, $narratives );
		update_post_meta( $post_id, self::META_CHOICES, array() );
		update_post_meta( $post_id, self::META_GENERATED, (int) $snapshot['generated'] );
		update_post_meta( $post_id, self::META_STATE, self::STATE_DRAFT );
		update_post_meta( $post_id, self::META_ORIGIN, self::ORIGIN_AUTO === $origin ? self::ORIGIN_AUTO : self::ORIGIN_MANAGER );

		return $post_id;
	}

	/**
	 * What the report is called, in the browser tab and at the head of the print document.
	 *
	 * The institution's own name, unlike the import batch's title, which deliberately carries
	 * none: a batch title would name a school's file, and this one names the school's own
	 * document. A record the pipeline index has not read falls back to the record ID rather
	 * than to a blank, because a report with no title is one nobody can find again.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 * @param string $cohort      A `WPCPM_Cohort` key.
	 * @return string
	 */
	private static function title_for( $institution, $cohort ) {
		$row  = WPCPM_Institutions_Index::row( $institution );
		$name = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

		if ( '' === $name ) {
			$name = $institution;
		}

		/* translators: 1: institution name, 2: semester, for example January to June 2026. */
		return sprintf( __( 'Semester report: %1$s, %2$s', 'wpcredits-program-manager' ), $name, WPCPM_Cohort::label( $cohort ) );
	}

	/*
	 * --------------------------------------------------------------------
	 * The two Airtable reads
	 * --------------------------------------------------------------------
	 */

	/**
	 * The lowercased addresses of the cohort's students, from the index.
	 *
	 * @param array  $rows   Roster index rows for one institution.
	 * @param string $cohort A `WPCPM_Cohort` key.
	 * @return string[] Unique, in index order.
	 */
	private static function cohort_emails( array $rows, $cohort ) {
		$emails = array();

		foreach ( self::cohort_rows( $rows, $cohort ) as $row ) {
			if ( '' !== $row['email_key'] ) {
				$emails[ $row['email_key'] ] = true;
			}
		}

		return array_keys( $emails );
	}

	/**
	 * The cohort's rows, in a shape the joining code can read.
	 *
	 * **Filtered by `WPCPM_Cohort::NOT_SIGNED_UP`, the same set `participation()` drops**, and
	 * not by the roster index's shorter `NEVER_SHOWN`. The two differ by `Interested`, a lead
	 * who never enrolled: the index still shows such a row to a school, but Participation does
	 * not count it, and a report that listed a lead's name under a Participation number that
	 * left them out would be the disagreement decision 11 created `WPCPM_Cohort` to prevent.
	 * `Interested` is at zero rows in the base today, which is exactly when to get this right.
	 *
	 * @param array  $rows   Roster index rows for one institution.
	 * @param string $cohort A `WPCPM_Cohort` key.
	 * @return array[] Each `array( 'email_key', 'name', 'website' )`, in index order.
	 */
	private static function cohort_rows( array $rows, $cohort ) {
		$out = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = trim( isset( $row['status'] ) ? (string) $row['status'] : '' );

			if ( in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
				continue;
			}

			if ( WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) !== $cohort ) {
				continue;
			}

			$out[] = array(
				'email_key' => strtolower( trim( isset( $row['email_key'] ) ? (string) $row['email_key'] : '' ) ),
				'name'      => trim( isset( $row['name'] ) ? (string) $row['name'] : '' ),
				'website'   => self::clean_url( isset( $row['website'] ) ? $row['website'] : '' ),
			);
		}

		return $out;
	}

	/**
	 * The cohort's Students Reports rows, kept or dropped on their institution link.
	 *
	 * **Read by address, in chunks, and never by an institution-name formula.** A formula on
	 * `Educational institution` would have to compare a name, and comparing names across the
	 * wire means lowercasing on both sides: Airtable's `LOWER()` is Unicode aware and PHP's
	 * `strtolower()` is not, so `Uniwersytet Łódzki` would fold on one side and not the other
	 * and the read would come back with nothing, with every line of code looking correct. The
	 * addresses are ASCII by nature, which is the one case `formula_in()`'s third argument is
	 * right for. Do not simplify this into a name query.
	 *
	 * @param string   $institution Airtable Institutions record ID.
	 * @param string[] $emails      Lowercased addresses.
	 * @return array|WP_Error Shaped rows, or the first error.
	 */
	private static function read_reports( $institution, array $emails ) {
		$records = self::fetch_by_email(
			'reports',
			$institution,
			$emails,
			WPCPM_Settings::get()['reports_table'],
			'Email',
			array(
				'Name',
				'Email',
				'Educational institution',
				'Main Contribution Team',
				'Personal Website URL',
				'Post Reflection: Building Your Personal Website',
				'Post Reflection: Choosing Your Team and Project',
				'Post Reflection: Your First Contribution',
				'Post Reflection: Halfway Check-In',
				'Closing post URL',
				'WP event participation URL',
				// `Hours`, `Status`, every grade column and `Contribution Project Summary` are
				// absent from this list on purpose. See the class docblock: what is not read
				// cannot end up in a document a university publishes.
			),
			static function ( array $records ) use ( $institution ) {
				return self::shape_reports_rows( $records, $institution, WPCPM_Mentors_Sync::lookups()['teams'] );
			}
		);

		// Shaped rows already, from the cache or from the read: see `fetch_by_email()`.
		return $records;
	}

	/**
	 * The cohort's Feedback rows, kept or dropped on their `Institution` link.
	 *
	 * Read by address in chunks for the reason `read_reports()` gives at length.
	 *
	 * @param string   $institution Airtable Institutions record ID.
	 * @param string[] $emails      Lowercased addresses.
	 * @return array|WP_Error Shaped rows, or the first error.
	 */
	private static function read_feedback( $institution, array $emails ) {
		$records = self::fetch_by_email(
			'feedback',
			$institution,
			$emails,
			WPCPM_Settings::get()['feedback_table'],
			'Email',
			array(
				'Name',
				'Email',
				self::FIELD_INSTITUTION,
				self::FIELD_LIST,
				self::FIELD_QUOTE_PERMISSION,
				self::FIELD_QUOTE,
				// Not one rating column, not even to average. See the class docblock.
			),
			static function ( array $records ) use ( $institution ) {
				return self::shape_feedback_rows( $records, $institution );
			}
		);

		// Shaped rows already, from the cache or from the read: see `fetch_by_email()`.
		return $records;
	}

	/**
	 * Fetch every row of one table whose address is in this list, through a cache.
	 *
	 * The cache is per institution, per set of addresses, and it is expired for a whole
	 * institution at once by `forget()`: a student saving their own consent must be visible on
	 * the next render, and there is no single key to delete because a key names the addresses
	 * that were asked about.
	 *
	 * @param string   $kind        `reports` or `feedback`, for the cache key.
	 * @param string   $institution Airtable Institutions record ID.
	 * @param string[] $emails      Lowercased addresses.
	 * @param string   $table       Table ID.
	 * @param string   $column      The email column's name in that table.
	 * @param string[] $wanted      Columns to read back.
	 * @param callable $shape       Turns the raw records into the rows the report uses; its output is what is cached.
	 * @return array|WP_Error Shaped rows.
	 */
	private static function fetch_by_email( $kind, $institution, array $emails, $table, $column, array $wanted, $shape ) {
		$emails = array_values( array_unique( array_filter( $emails, 'strlen' ) ) );

		if ( empty( $emails ) ) {
			return array();
		}

		sort( $emails );

		$key    = self::cache_key( $kind, $institution, $emails );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$airtable = new WPCPM_Airtable();
		$records  = array();

		foreach ( array_chunk( $emails, self::CHUNK ) as $chunk ) {
			$page = $airtable->fetch_all(
				$table,
				array(
					'formula' => $airtable->formula_in( $column, $chunk, true ),
					'fields'  => $wanted,
				)
			);

			// Returned rather than skipped: a chunk that failed is a set of students who would
			// silently be missing from the document, and the school could not tell.
			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$records = array_merge( $records, $page );
		}

		// **Shaped before it is cached.** The transient used to hold Airtable's raw records,
		// which for the Feedback table meant every cohort student's unreleased words and every
		// address, for five minutes, in an option. What is cached is what the report may use.
		$shaped = call_user_func( $shape, $records );

		set_transient( $key, $shaped, self::CACHE_TTL );

		return $shaped;
	}

	/**
	 * The cache key for one read.
	 *
	 * @param string   $kind        `reports` or `feedback`.
	 * @param string   $institution Airtable Institutions record ID.
	 * @param string[] $emails      Sorted, lowercased addresses.
	 * @return string
	 */
	private static function cache_key( $kind, $institution, array $emails ) {
		return self::CACHE_PREFIX . $kind . '_' . md5( $institution . '|' . self::epoch( $institution ) . '|' . implode( ',', $emails ) );
	}

	/**
	 * This institution's cache counter.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 * @return int
	 */
	private static function epoch( $institution ) {
		$stored = get_option( self::OPT_EPOCH );
		$stored = is_array( $stored ) ? $stored : array();

		return isset( $stored[ $institution ] ) ? (int) $stored[ $institution ] : 0;
	}

	/**
	 * Expire every cached read for one institution.
	 *
	 * **Called by `WPCPM_Student_Feedback::handle_save()` when a student saves their own
	 * permission answers**, so a withdrawal is visible on the next render rather than up to
	 * `CACHE_TTL` later. Also worth calling after anything that changes a Students Reports row
	 * on this institution's students.
	 *
	 * @param string $institution Airtable Institutions record ID.
	 */
	public static function forget( $institution ) {
		$institution = trim( (string) $institution );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
			return;
		}

		$stored = get_option( self::OPT_EPOCH );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $institution ] = ( isset( $stored[ $institution ] ) ? (int) $stored[ $institution ] : 0 ) + 1;

		update_option( self::OPT_EPOCH, $stored, false );
	}

	/*
	 * --------------------------------------------------------------------
	 * Shaping what came back. Pure: arrays in, arrays out.
	 * --------------------------------------------------------------------
	 */

	/**
	 * Students Reports records reduced to what the document needs, on the keep rule.
	 *
	 * **The keep rule.** A row is kept when its institution link is empty or contains this
	 * institution, and dropped when it names another. Empty is kept because a reports row whose
	 * link nobody has filled in is still this student's row, and dropping it would blank the
	 * projects of every student whose paperwork is half done; a row naming another university
	 * is dropped because it is that university's row and belongs in that university's report.
	 *
	 * Rows are returned as a flat list rather than keyed by address, because several rows may
	 * share an address and telling them apart is exactly the problem `assemble()` solves.
	 *
	 * @param array  $records     Raw Airtable records.
	 * @param string $institution Airtable Institutions record ID.
	 * @param array  $teams       Record ID to team name, from `WPCPM_Mentors_Sync::lookups()`.
	 * @return array[] Each `array( 'email_key', 'name', 'website', 'teams', 'links', 'events' )`.
	 */
	public static function shape_reports_rows( array $records, $institution, array $teams ) {
		$institution = trim( (string) $institution );
		$out         = array();

		// In the order the document prints them: the site first, then the posts as the term
		// went, then the closing post. The label each one is printed under comes from the
		// student's own form, so the school reads the same words the student answered.
		$link_fields = array(
			'Post Reflection: Building Your Personal Website',
			'Post Reflection: Choosing Your Team and Project',
			'Post Reflection: Your First Contribution',
			'Post Reflection: Halfway Check-In',
			'Closing post URL',
		);

		foreach ( $records as $record ) {
			$cells = ( is_array( $record ) && isset( $record['fields'] ) && is_array( $record['fields'] ) ) ? $record['fields'] : array();
			$key   = strtolower( trim( WPCPM_Airtable::flatten( isset( $cells['Email'] ) ? $cells['Email'] : '' ) ) );

			// No address is no join: this row cannot be attached to a student on the roster,
			// and a row that cannot be attached to anybody cannot be printed about anybody.
			if ( '' === $key ) {
				continue;
			}

			if ( ! self::keeps( isset( $cells['Educational institution'] ) ? $cells['Educational institution'] : null, $institution ) ) {
				continue;
			}

			$links = array();

			foreach ( $link_fields as $field ) {
				foreach ( self::urls_in( isset( $cells[ $field ] ) ? $cells[ $field ] : '' ) as $url ) {
					$links[] = array(
						'field' => $field,
						'url'   => $url,
					);
				}
			}

			$out[] = array(
				'email_key' => $key,
				'name'      => trim( WPCPM_Airtable::flatten( isset( $cells['Name'] ) ? $cells['Name'] : '' ) ),
				'website'   => self::clean_url( WPCPM_Airtable::flatten( isset( $cells['Personal Website URL'] ) ? $cells['Personal Website URL'] : '' ) ),
				'teams'     => self::team_names( isset( $cells['Main Contribution Team'] ) ? $cells['Main Contribution Team'] : null, $teams ),
				'links'     => $links,
				'events'    => self::urls_in( isset( $cells['WP event participation URL'] ) ? $cells['WP event participation URL'] : '' ),
			);
		}

		return $out;
	}

	/**
	 * Feedback records reduced to the three answers this document reads, on the keep rule.
	 *
	 * The keep rule is `shape_reports_rows()`'s, on the `Institution` link. `linked` records
	 * which of the two ways a row was kept, because among the 50 duplicated addresses in the
	 * Feedback table the row that names this institution is the one to believe.
	 *
	 * The quote text is capped at `MAX_TEXT` here rather than where it is printed, so the
	 * ceiling is on the stored document and not on one renderer's opinion of it.
	 *
	 * @param array  $records     Raw Airtable records.
	 * @param string $institution Airtable Institutions record ID.
	 * @return array[] Each `array( 'email_key', 'name', 'linked', 'list', 'permission', 'quote' )`.
	 */
	public static function shape_feedback_rows( array $records, $institution ) {
		$institution = trim( (string) $institution );
		$out         = array();

		foreach ( $records as $record ) {
			$cells = ( is_array( $record ) && isset( $record['fields'] ) && is_array( $record['fields'] ) ) ? $record['fields'] : array();
			$key   = strtolower( trim( WPCPM_Airtable::flatten( isset( $cells['Email'] ) ? $cells['Email'] : '' ) ) );

			if ( '' === $key ) {
				continue;
			}

			$cell = isset( $cells[ self::FIELD_INSTITUTION ] ) ? $cells[ self::FIELD_INSTITUTION ] : null;

			if ( ! self::keeps( $cell, $institution ) ) {
				continue;
			}

			$out[] = array(
				'email_key'  => $key,
				'name'       => trim( WPCPM_Airtable::flatten( isset( $cells['Name'] ) ? $cells['Name'] : '' ) ),
				'linked'     => in_array( $institution, self::institution_ids( $cell ), true ),
				'list'       => trim( WPCPM_Airtable::flatten( isset( $cells[ self::FIELD_LIST ] ) ? $cells[ self::FIELD_LIST ] : '' ) ),
				'permission' => trim( WPCPM_Airtable::flatten( isset( $cells[ self::FIELD_QUOTE_PERMISSION ] ) ? $cells[ self::FIELD_QUOTE_PERMISSION ] : '' ) ),
				'quote'      => '',
				'has_quote'  => false,
			);

			// **The words themselves are kept only once the student released them.** Until then
			// the row records that there is something to release and nothing more, so a quote
			// nobody has released is held nowhere on this site: not in the snapshot, and not in
			// the five-minute cache these rows go into, which used to carry every unreleased
			// answer of the cohort in an option anybody with database access could read. The
			// flag is what `consent_candidates()` needs to know whom to write to.
			$quote = self::cap( WPCPM_Airtable::flatten( isset( $cells[ self::FIELD_QUOTE ] ) ? $cells[ self::FIELD_QUOTE ] : '' ) );
			$last  = count( $out ) - 1;

			$out[ $last ]['has_quote'] = '' !== trim( $quote );

			if ( in_array( $out[ $last ]['permission'], array( self::QUOTE_NAMED, self::QUOTE_ANONYMOUS ), true ) ) {
				$out[ $last ]['quote'] = $quote;
			}
		}

		return $out;
	}

	/**
	 * Whether a row's institution link keeps it for this institution.
	 *
	 * @param mixed  $cell        The raw link cell.
	 * @param string $institution Airtable Institutions record ID.
	 * @return bool
	 */
	private static function keeps( $cell, $institution ) {
		$ids = self::institution_ids( $cell );

		return empty( $ids ) || in_array( $institution, $ids, true );
	}

	/**
	 * The institution record IDs in a link cell.
	 *
	 * Both columns are linked-record fields, so `link_ids()` is the answer nearly always. The
	 * scalar fallback is there because a base owner can change a column's type in the grid, and
	 * a link that came back as a plain record ID string would otherwise read as "no institution
	 * named" and quietly keep another university's row.
	 *
	 * @param mixed $cell The raw cell.
	 * @return string[]
	 */
	private static function institution_ids( $cell ) {
		$ids = WPCPM_Airtable::link_ids( $cell );

		if ( empty( $ids ) && is_scalar( $cell ) && WPCPM_Mentors_Sync::is_record_id( $cell ) ) {
			$ids = array( trim( (string) $cell ) );
		}

		return $ids;
	}

	/**
	 * The team names on a `Main Contribution Team` cell.
	 *
	 * A linked-record field, so the cell holds record IDs and the names come from the map the
	 * sync stored. A cell that is not a link cell (a lookup, or plain text after a column type
	 * change) is flattened and split on the comma `resolve_links()` joins with, so a base
	 * change does not empty the section.
	 *
	 * @param mixed $cell  The raw cell.
	 * @param array $teams Record ID to team name.
	 * @return string[] Names, unique, in cell order.
	 */
	private static function team_names( $cell, array $teams ) {
		$names = array();
		$ids   = WPCPM_Airtable::link_ids( $cell );

		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				if ( isset( $teams[ $id ] ) && '' !== trim( (string) $teams[ $id ] ) ) {
					$names[] = trim( (string) $teams[ $id ] );
				}
			}
		} else {
			foreach ( explode( ',', WPCPM_Airtable::flatten( $cell ) ) as $name ) {
				$name = trim( $name );

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/*
	 * --------------------------------------------------------------------
	 * The join, the consent, and the counting. Pure.
	 * --------------------------------------------------------------------
	 */

	/**
	 * Turn the index rows and the two reads into a snapshot.
	 *
	 * Pure: arrays in, an array out, no network, no options, no request. Everything that makes
	 * this document what it is happens here, which is what lets `bin/test-semester-report.php`
	 * drive the whole set of rules from fixtures.
	 *
	 * The snapshot's shape:
	 *
	 *     array(
	 *         'v'             => 1,
	 *         'generated'     => int,     // when generate() ran
	 *         'read'          => int,     // when the roster index was read
	 *         'cohort'        => '2026-H1',
	 *         'participation' => array( signed_up, graduated, pending, active, withdrawn, not_started, other ),
	 *         'previous'      => array( 'key', 'signed_up', 'graduated', 'has_rows' ),
	 *         'teams'         => array( array( 'team', 'count' ) ),
	 *         'students'      => array( array( 'id', 'display', 'named', 'website', 'links', 'events' ) ),
	 *         'events'        => array( array( 'url', 'count' ) ),
	 *         'quotes'        => array( array( 'id', 'text', 'named', 'name' ) ),
	 *         'withheld'      => array( 'no_answer', 'declined', 'ambiguous' ),
	 *     )
	 *
	 * **`withheld` counts students, and a student is counted at most once**, so the three
	 * numbers can be read side by side and added up. `ambiguous` is the one that needs saying
	 * out loud: it counts a student whose Airtable rows could not be told apart, whether that
	 * cost them their listing (two Feedback rows) or only their links (two Students Reports
	 * rows). Everything else is a consent answer: `declined` said no, `no_answer` said nothing.
	 *
	 * @param array $input {
	 *     Everything one report is joined out of.
	 *
	 *     @type string $institution Airtable Institutions record ID.
	 *     @type string $cohort      A `WPCPM_Cohort` key.
	 *     @type int    $read        When the roster index was read.
	 *     @type array  $rows        Roster index rows for this institution, every cohort.
	 *     @type array  $reports     Rows from `shape_reports_rows()`.
	 *     @type array  $feedback    Rows from `shape_feedback_rows()`.
	 *     @type array  $labels      Optional. Airtable field name to label; read from the
	 *                               student's own form when absent.
	 *     @type int    $generated   Optional. Stamped by the caller otherwise.
	 * }
	 * @return array The snapshot.
	 */
	public static function assemble( array $input ) {
		$cohort   = isset( $input['cohort'] ) ? (string) $input['cohort'] : '';
		$rows     = isset( $input['rows'] ) && is_array( $input['rows'] ) ? $input['rows'] : array();
		$labels   = isset( $input['labels'] ) && is_array( $input['labels'] ) ? $input['labels'] : self::link_labels();
		$reports  = self::group_by_email( isset( $input['reports'] ) ? (array) $input['reports'] : array() );
		$feedback = self::group_by_email( isset( $input['feedback'] ) ? (array) $input['feedback'] : array() );

		$students = array();
		$quotes   = array();
		$teams    = array();
		$withheld = array(
			'no_answer' => 0,
			'declined'  => 0,
			'ambiguous' => 0,
		);
		$seen     = array();

		foreach ( self::cohort_rows( $rows, $cohort ) as $row ) {
			$id = self::student_id( $row['email_key'] );

			// A student with two Students rows appears twice on the index under two record
			// IDs. They are one person and are printed once; the second row would otherwise
			// duplicate their name, their links and their quote.
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;

			$report = self::pick_report( isset( $reports[ $row['email_key'] ] ) ? $reports[ $row['email_key'] ] : array(), $row['name'] );
			$answer = self::pick_feedback( isset( $feedback[ $row['email_key'] ] ) ? $feedback[ $row['email_key'] ] : array() );

			// The team count is an aggregate with no person in it, so it is taken over every
			// student in the cohort whatever they said about being named. It is taken over the
			// row that won the join and never over both of two rows: fields are not unioned.
			if ( is_array( $report ) ) {
				foreach ( $report['teams'] as $team ) {
					$teams[ $team ] = isset( $teams[ $team ] ) ? $teams[ $team ] + 1 : 1;
				}
			}

			// Two Feedback rows this institution may read, disagreeing about permission, is not
			// a release. The student is withheld and counted, rather than guessed about.
			if ( false === $answer ) {
				++$withheld['ambiguous'];
				continue;
			}

			$listing = self::listing_for( $id, $row, $report, $answer );

			if ( '' !== $listing['withheld'] ) {
				++$withheld[ $listing['withheld'] ];
			} elseif ( is_array( $listing['student'] ) ) {
				$students[] = $listing['student'];

				// Listed, but their project links could not be attached to them, because two
				// Students Reports rows carry their address and neither one's name matches.
				// **Only several rows is ambiguous.** A student with no Students Reports row at
				// all (31 in the program today, spec section 8.1) has nothing to attach and
				// nothing unmatched; counting them here told a university two students'
				// records were unmatchable when one was.
				if ( null === $report && ! empty( $reports[ $row['email_key'] ] ) ) {
					++$withheld['ambiguous'];
				}
			}

			// **Independent of the listing answer, on purpose.** A student may decline to be
			// named in the projects list and still release a passage to be quoted, or the other
			// way round; they are two questions and the student answered both.
			$quote = self::quote_for( $id, $row, $report, $answer );

			if ( is_array( $quote ) ) {
				$quotes[] = $quote;
			}
		}

		return self::shape_snapshot(
			array(
				'v'             => self::VERSION,
				'generated'     => isset( $input['generated'] ) ? (int) $input['generated'] : 0,
				'read'          => isset( $input['read'] ) ? (int) $input['read'] : 0,
				'cohort'        => $cohort,
				'participation' => WPCPM_Cohort::participation( $rows, $cohort ),
				'previous'      => self::previous_for( $rows, $cohort ),
				'teams'         => self::rank( $teams, 'team' ),
				'students'      => self::sort_students( $students, $labels ),
				'events'        => self::group_events( $students ),
				'quotes'        => self::sort_quotes( $quotes ),
				'withheld'      => $withheld,
			)
		);
	}

	/**
	 * Which Students Reports row is this student's, or null when it cannot be said.
	 *
	 * Twenty-three students in the base today have more than one row. The tie is broken on the
	 * normalised name and on nothing else: none matching or several matching means the row
	 * cannot be identified, and **no field is taken from any of them**. Unioning the rows would
	 * put one person's closing post beside another person's website under a third person's
	 * name, and a document that did that would be wrong in a way nobody could see.
	 *
	 * @param array  $candidates Kept rows sharing this address.
	 * @param string $name       The student's name as the roster index holds it.
	 * @return array|null The row, or null.
	 */
	private static function pick_report( array $candidates, $name ) {
		if ( 1 === count( $candidates ) ) {
			return $candidates[0];
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		$key     = self::name_key( $name );
		$matches = array();

		if ( '' !== $key ) {
			foreach ( $candidates as $candidate ) {
				if ( self::name_key( $candidate['name'] ) === $key ) {
					$matches[] = $candidate;
				}
			}
		}

		return 1 === count( $matches ) ? $matches[0] : null;
	}

	/**
	 * Which Feedback row this student's answers are on.
	 *
	 * Among the 50 duplicated addresses in that table, the row linked to this institution is
	 * the one to believe: a consent recorded on a row linked somewhere else is a consent this
	 * institution never fetched. When that still leaves more than one row, `false` says the
	 * answers cannot be identified, which is a different thing from "there are none" and is
	 * counted differently.
	 *
	 * @param array $candidates Kept rows sharing this address.
	 * @return array|null|false The row, null when there is none, false when there are several.
	 */
	private static function pick_feedback( array $candidates ) {
		if ( empty( $candidates ) ) {
			return null;
		}

		if ( 1 === count( $candidates ) ) {
			return $candidates[0];
		}

		$linked = array();

		foreach ( $candidates as $candidate ) {
			if ( ! empty( $candidate['linked'] ) ) {
				$linked[] = $candidate;
			}
		}

		return 1 === count( $linked ) ? $linked[0] : false;
	}

	/**
	 * How this student appears in the projects list, or which bucket withheld them.
	 *
	 * The three answers to `FIELD_LIST`, and the two ways of ending up withheld anyway. A blog
	 * address the student has not got is the one that reads oddly: they answered, they said
	 * yes, and there is nothing to print them under. Falling back to their name would publish
	 * the one thing that answer exists to keep back, so they are withheld, under `no_answer`,
	 * and the school sees a number rather than a name.
	 *
	 * @param string     $id     The student's id in this document.
	 * @param array      $row    The roster row: `name`, `website`.
	 * @param array|null $report The joined Students Reports row, or null.
	 * @param array|null $answer The joined Feedback row, or null.
	 * @return array{student: array|null, withheld: string}
	 */
	private static function listing_for( $id, array $row, $report, $answer ) {
		$none = array(
			'student'  => null,
			'withheld' => 'no_answer',
		);

		if ( ! is_array( $answer ) ) {
			return $none;
		}

		$website = is_array( $report ) && '' !== $report['website'] ? $report['website'] : $row['website'];
		$host    = self::host_of( $website );

		if ( self::ANSWER_NO === $answer['list'] ) {
			return array(
				'student'  => null,
				'withheld' => 'declined',
			);
		}

		if ( self::LIST_NAMED === $answer['list'] ) {
			$display = '' !== $row['name'] ? $row['name'] : ( is_array( $report ) ? $report['name'] : '' );

			// A name that is itself an email address cannot go into this document: the snapshot
			// carries no addresses, and the no-PII test would fail on it. Their blog host is the
			// other thing they might be known by, and if there is none they are withheld.
			if ( '' === $display || is_email( $display ) ) {
				$display = $host;
			}

			if ( '' === $display ) {
				return $none;
			}

			return array(
				'student'  => self::student_entry( $id, $display, true, $website, $report ),
				'withheld' => '',
			);
		}

		if ( self::LIST_BLOG === $answer['list'] ) {
			if ( '' === $host ) {
				return $none;
			}

			return array(
				'student'  => self::student_entry( $id, $host, false, $website, $report ),
				'withheld' => '',
			);
		}

		return $none;
	}

	/**
	 * One student's entry in the projects list.
	 *
	 * `links` and `events` are empty when no Students Reports row could be identified: the
	 * student is still listed, because they said they wanted to be, and the school is told in
	 * the withheld line that one student's records could not be matched.
	 *
	 * @param string     $id      The student's id in this document.
	 * @param string     $display The name or the blog host, per their own answer.
	 * @param bool       $named   Whether `display` is their name.
	 * @param string     $website Their personal site, or an empty string.
	 * @param array|null $report  The joined Students Reports row, or null.
	 * @return array
	 */
	private static function student_entry( $id, $display, $named, $website, $report ) {
		return array(
			'id'      => $id,
			'display' => $display,
			'named'   => (bool) $named,
			'website' => (string) $website,
			'links'   => is_array( $report ) ? $report['links'] : array(),
			// Kept on the student as well as in the grouped `events` list, so a render that has
			// dropped somebody on a live consent check can regroup the section without them
			// rather than printing a count that still includes their attendance.
			'events'  => is_array( $report ) ? $report['events'] : array(),
		);
	}

	/**
	 * This student's quote, or null.
	 *
	 * **A school never sees a candidate quote before the student released it.** A non-empty
	 * answer with no permission answer contributes nothing at all: not a blurred line, not a
	 * count, not an entry with `include` false that somebody could tick. The words are simply
	 * not read into the document.
	 *
	 * @param string     $id     The student's id in this document.
	 * @param array      $row    The roster row: `name`.
	 * @param array|null $report The joined Students Reports row, or null.
	 * @param array|null $answer The joined Feedback row, or null.
	 * @return array|null
	 */
	private static function quote_for( $id, array $row, $report, $answer ) {
		if ( ! is_array( $answer ) ) {
			return null;
		}

		$text = trim( (string) $answer['quote'] );

		// A quote that is nothing but an address is not printable here for the reason
		// `listing_for()` gives about names, and it is not a quote about the program either.
		if ( '' === $text || is_email( $text ) ) {
			return null;
		}

		$permission = $answer['permission'];

		if ( self::QUOTE_NAMED !== $permission && self::QUOTE_ANONYMOUS !== $permission ) {
			return null;
		}

		$name = '';

		if ( self::QUOTE_NAMED === $permission ) {
			$name = '' !== $row['name'] ? $row['name'] : ( is_array( $report ) ? $report['name'] : '' );

			if ( is_email( $name ) ) {
				$name = '';
			}
		}

		return array(
			'id'    => $id,
			// The student's own words, byte for byte as the base holds them. Nothing in this
			// module edits them, and the institution's contribution is a translation printed
			// underneath, never a correction printed instead.
			'text'  => $text,
			'named' => '' !== $name,
			'name'  => $name,
		);
	}

	/**
	 * The previous semester's two numbers, and whether it holds any rows at all.
	 *
	 * `has_rows` is the difference between "nobody signed up that semester" and "we have
	 * nothing to compare against", which a comparison strip has to be able to say. A cohort
	 * with rows that are all `Interested` is a real semester with a signed-up count of zero,
	 * and that is a true sentence rather than a missing one.
	 *
	 * @param array  $rows   Roster index rows.
	 * @param string $cohort The cohort being reported on.
	 * @return array{key: string, signed_up: int, graduated: int, has_rows: bool}
	 */
	private static function previous_for( array $rows, $cohort ) {
		$key = WPCPM_Cohort::previous( $cohort );

		if ( '' === $key ) {
			return array(
				'key'       => '',
				'signed_up' => 0,
				'graduated' => 0,
				'has_rows'  => false,
			);
		}

		$counts   = WPCPM_Cohort::participation( $rows, $key );
		$has_rows = false;

		// The same rows `participation()` counts, and no others. A semester holding only a SPAM
		// row, or an `Interested` lead, is a semester the school never ran; with it counted here
		// the page said "0 students took part and 0 graduated" about a term that never existed,
		// where the sentence for an empty semester is the true one.
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = trim( isset( $row['status'] ) ? (string) $row['status'] : '' );

			if ( in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
				continue;
			}

			if ( WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) === $key ) {
				$has_rows = true;
				break;
			}
		}

		return array(
			'key'       => $key,
			'signed_up' => (int) $counts['signed_up'],
			'graduated' => (int) $counts['graduated'],
			'has_rows'  => $has_rows,
		);
	}

	/**
	 * Rows from either read, grouped by address.
	 *
	 * @param array $rows Shaped rows.
	 * @return array<string, array[]>
	 */
	private static function group_by_email( array $rows ) {
		$out = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['email_key'] ) && '' !== $row['email_key'] ) {
				$out[ $row['email_key'] ][] = $row;
			}
		}

		return $out;
	}

	/**
	 * Identical event URLs grouped with a count.
	 *
	 * Public because a render that has narrowed the student list on a live consent check has to
	 * rebuild this section from the students it kept: the stored `events` list is the one the
	 * document was generated with, and subtracting one person from a grouped count is not
	 * something a count can be asked to do.
	 *
	 * @param array $students Student entries carrying their own `events`.
	 * @return array[] Each `array( 'url', 'count' )`, most attended first.
	 */
	public static function group_events( array $students ) {
		$counts = array();

		$display = array();

		foreach ( $students as $student ) {
			$urls = ( is_array( $student ) && isset( $student['events'] ) ) ? (array) $student['events'] : array();
			$seen = array();

			// Unique per student, so one person listing the same event twice does not read as
			// two people having gone to it.
			foreach ( $urls as $url ) {
				$url = (string) $url;

				if ( '' === $url ) {
					continue;
				}

				// Grouped on a key that folds the case of the scheme and the host, because a
				// host is case-insensitive by definition and `Europe.WordCamp.org` is the same
				// place as `europe.wordcamp.org`; three students at one WordCamp printed as
				// three events with no count. The path keeps its case, and a trailing slash is
				// left to stand: the spec says identical URLs, and `/2026` and `/2026/` are the
				// same page on most servers and different pages on some.
				$key = self::event_key( $url );

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ]   = true;
				$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;

				// The first spelling seen is the one printed, so the document shows a URL a
				// student actually typed rather than one the plugin rewrote.
				if ( ! isset( $display[ $key ] ) ) {
					$display[ $key ] = $url;
				}
			}
		}

		$shown = array();

		foreach ( $counts as $key => $count ) {
			$shown[ $display[ $key ] ] = $count;
		}

		return self::rank( $shown, 'url' );
	}

	/**
	 * The grouping key for one event URL: scheme and host lowercased, the rest as typed.
	 *
	 * @param string $url A cleaned URL.
	 * @return string
	 */
	private static function event_key( $url ) {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return strtolower( $url );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) . '://' : '';
		$rest   = ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. ( isset( $parts['path'] ) ? $parts['path'] : '' )
			. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
			. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );

		return $scheme . strtolower( $parts['host'] ) . $rest;
	}

	/**
	 * A name => count map as a ranked list.
	 *
	 * Count descending, then the name ascending, so the order is stable across generations: a
	 * regenerated report whose numbers did not change reads identically, which is what lets a
	 * school diff two terms.
	 *
	 * @param array  $counts Name to count.
	 * @param string $key    What the name is called in each row: `team` or `url`.
	 * @return array[]
	 */
	private static function rank( array $counts, $key ) {
		$out = array();

		foreach ( $counts as $name => $count ) {
			$out[] = array(
				$key    => (string) $name,
				'count' => (int) $count,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) use ( $key ) {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] - $a['count'];
				}

				return strnatcasecmp( $a[ $key ], $b[ $key ] );
			}
		);

		return $out;
	}

	/**
	 * The projects list in the order it is printed, and its links labelled.
	 *
	 * Sorted on what each student chose to be called, which is the only thing about them this
	 * document holds. The labels are attached here rather than in the shaping pass because they
	 * are translated strings and a snapshot is stored, not rendered: a document generated in
	 * one language and read in another gets the labels of the language it is stored in, which
	 * is the language the school wrote the rest of it in.
	 *
	 * @param array $students Student entries.
	 * @param array $labels   Airtable field name to label.
	 * @return array[]
	 */
	private static function sort_students( array $students, array $labels ) {
		foreach ( $students as $index => $student ) {
			$links = array();

			foreach ( $student['links'] as $link ) {
				$field = isset( $link['field'] ) ? (string) $link['field'] : '';

				$links[] = array(
					'label' => isset( $labels[ $field ] ) ? $labels[ $field ] : $field,
					'url'   => isset( $link['url'] ) ? (string) $link['url'] : '',
				);
			}

			$students[ $index ]['links'] = $links;
		}

		usort(
			$students,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['display'], $b['display'] );
			}
		);

		return $students;
	}

	/**
	 * The quotes in the order they are printed.
	 *
	 * By id, which is by nothing a reader can see. **Alphabetical order would be a disclosure**:
	 * a list sorted by author name tells anybody holding the class list roughly where in the
	 * alphabet an anonymous quote's author sits, and half the point of the anonymous option is
	 * that it does not. The id is stable, so the order is too.
	 *
	 * @param array $quotes Quote entries.
	 * @return array[]
	 */
	private static function sort_quotes( array $quotes ) {
		usort(
			$quotes,
			static function ( $a, $b ) {
				return strcmp( $a['id'], $b['id'] );
			}
		);

		return $quotes;
	}

	/**
	 * The label each project link is printed under, from the student's own form.
	 *
	 * Merged across the three tracks because a field's group differs by track and a 50-hour
	 * student's form does not carry the reflection posts at all; the wording of a label that
	 * exists on two tracks is the same on both. A field the form has never heard of falls back
	 * to the Airtable column name where it is used, so a new column is readable rather than
	 * invisible.
	 *
	 * @return array<string, string> Airtable field name to label.
	 */
	public static function link_labels() {
		$labels = array();

		foreach ( array( '150h', '50h', 'dev' ) as $track ) {
			foreach ( WPCPM_Student_Report_Form::fields( $track ) as $field => $spec ) {
				if ( ! isset( $labels[ $field ] ) && isset( $spec['label'] ) && '' !== $spec['label'] ) {
					$labels[ $field ] = (string) $spec['label'];
				}
			}
		}

		return $labels;
	}

	/*
	 * --------------------------------------------------------------------
	 * Consent, re-checked
	 * --------------------------------------------------------------------
	 */

	/**
	 * What the reader may see right now, against the live Feedback rows.
	 *
	 * **Every render goes through this, screen and print alike.** It is what makes a withdrawal
	 * reach a stored document without anybody regenerating it: a student who changes their
	 * answer to `No` disappears from the next page load and from the next print, and the page
	 * says that a quote was withdrawn since the draft was generated.
	 *
	 * **It only ever narrows.** A student who has newly said yes since the snapshot was made
	 * does not appear here, and one who has upgraded from a blog address to their name keeps
	 * the blog address: the document holds no addresses and no names it was not given, so there
	 * is nothing to widen it with. `refresh_consent()` is the button for that, and it re-reads.
	 *
	 * A read failure comes back as a `WP_Error` rather than as the stored lists. If consent
	 * cannot be confirmed it has not been confirmed, and a caller that cannot tell the two
	 * apart would print names on the strength of an Airtable outage.
	 *
	 * @param WP_Post $post The report.
	 * @return array{students: array, quotes: array, dropped: int}|WP_Error `dropped` counts
	 *         quotes that are in the snapshot and may no longer be printed.
	 */
	public static function consent_check( WP_Post $post ) {
		$snapshot = self::snapshot( $post );
		$empty    = array(
			'students' => array(),
			'quotes'   => array(),
			'dropped'  => 0,
		);

		if ( empty( $snapshot ) ) {
			return $empty;
		}

		// Nothing was released, so there is nothing to re-check and no reason to spend a read
		// on every page load of a report where every student said no.
		if ( empty( $snapshot['students'] ) && empty( $snapshot['quotes'] ) ) {
			return $empty;
		}

		$institution = self::institution_of( $post );
		$cohort      = self::cohort_of( $post );

		if ( '' === $institution || '' === $cohort ) {
			return new WP_Error( 'wpcpm_report_unplaced', __( 'This report is not attached to an institution and a semester, so consent cannot be checked.', 'wpcredits-program-manager' ) );
		}

		$emails = self::cohort_emails( WPCPM_Roster_Index::rows( $institution ), $cohort );

		// **A read that was never made is not a confirmed consent.** The snapshot names people,
		// so the index held addresses when it was generated; an empty list now means the index
		// is gone (the option is discarded whenever `WPCPM_Roster_Index::VERSION` moves, and it
		// has moved before) rather than that everybody left. Asking Airtable about nobody comes
		// back with nothing, and nothing read as "every student withdrew" printed a document
		// with the participation numbers intact, no names, and a line about four withdrawals
		// nobody made. The rule in the class docblock is that unconfirmed means withheld, and
		// this is where it has to be said out loud rather than inferred from an empty array.
		if ( empty( $emails ) ) {
			return new WP_Error( 'wpcpm_report_no_index', __( 'The list of students this report was built from is not available right now, so the students\' answers could not be checked. Try again after the next sync.', 'wpcredits-program-manager' ) );
		}

		$feedback = self::read_feedback( $institution, $emails );

		if ( is_wp_error( $feedback ) ) {
			return $feedback;
		}

		return self::consent_view( $snapshot, $feedback );
	}

	/**
	 * The students a consent request would be written to: accounts, in roster order.
	 *
	 * Design spec 7.9: **a candidate answer and no permission answer.** Both halves are
	 * required and both are narrow on purpose, because the result of this function is
	 * unsolicited mail to somebody who is not being paid to read it.
	 *
	 * - *A candidate answer* is a non-empty quote on a Feedback row this institution's report
	 *   may read. That is the one thing a student writes that the report cannot use until they
	 *   release it, and the one thing this message exists to ask about. A student who filled in
	 *   nothing has said nothing to release, and mailing them would be the site asking a
	 *   stranger for permission to print a blank.
	 * - *No permission answer* means neither of the two columns is filled in. The message asks
	 *   about both questions in one breath, so sending it to somebody who has answered one of
	 *   them re-asks a question they have already answered, which is what "never twice" is for.
	 *
	 * Two rows for one address that cannot be told apart are left out: `pick_feedback()`
	 * returns `false` there, and asking is a write to a person, which is not a thing to do on a
	 * guess. A row with no WordPress account is left out too, because `WPCPM_Mail::send()`
	 * addresses an account and the report's own reads never carry an address out of Airtable.
	 *
	 * The thirty-day rule is not applied here. It is a property of the sending, the screen half
	 * owns the stamp, and the queue re-checks it batch by batch days after this list was built.
	 *
	 * @param WP_Post $post The report.
	 * @return int[]|WP_Error User IDs.
	 */
	public static function consent_candidates( WP_Post $post ) {
		$institution = self::institution_of( $post );
		$cohort      = self::cohort_of( $post );

		if ( '' === $institution || '' === $cohort ) {
			return new WP_Error( 'wpcpm_report_unplaced', __( 'This report is not attached to an institution and a semester, so its students cannot be found.', 'wpcredits-program-manager' ) );
		}

		$rows     = WPCPM_Roster_Index::rows( $institution );
		$accounts = self::cohort_accounts( $rows, $cohort );

		if ( empty( $accounts ) ) {
			return array();
		}

		$feedback = self::read_feedback( $institution, array_keys( $accounts ) );

		if ( is_wp_error( $feedback ) ) {
			return $feedback;
		}

		$ids = array();

		foreach ( self::group_by_email( $feedback ) as $email_key => $candidates ) {
			if ( ! isset( $accounts[ $email_key ] ) ) {
				continue;
			}

			$row = self::pick_feedback( $candidates );

			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( empty( $row['has_quote'] ) ) {
				continue;
			}

			if ( '' !== trim( (string) $row['list'] ) || '' !== trim( (string) $row['permission'] ) ) {
				continue;
			}

			$ids[] = (int) $accounts[ $email_key ];
		}

		return $ids;
	}

	/**
	 * The cohort's addresses that have a WordPress account behind them.
	 *
	 * Keyed by `email_key` so a Feedback row can be looked up against it without the address
	 * leaving this function in any other form.
	 *
	 * @param array  $rows   Roster index rows for one institution.
	 * @param string $cohort A `WPCPM_Cohort` key.
	 * @return array<string, int> Address key to user ID.
	 */
	private static function cohort_accounts( array $rows, $cohort ) {
		$out = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = trim( isset( $row['status'] ) ? (string) $row['status'] : '' );

			// The same rows `cohort_rows()` drops, for the same reason: a spam submission, a
			// duplicate and a lead who never enrolled are not people the school sent, and none
			// of them is somebody to write to about a report they are not in.
			if ( in_array( $status, WPCPM_Cohort::NOT_SIGNED_UP, true ) ) {
				continue;
			}

			if ( WPCPM_Cohort::key( isset( $row['start'] ) ? $row['start'] : '' ) !== $cohort ) {
				continue;
			}

			$key     = strtolower( trim( isset( $row['email_key'] ) ? (string) $row['email_key'] : '' ) );
			$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;

			if ( '' !== $key && $user_id > 0 && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $user_id;
			}
		}

		return $out;
	}

	/**
	 * Narrow a stored snapshot to what the live answers still allow. Pure.
	 *
	 * The rules, one per way an answer can change:
	 *
	 * - No live row for a student's id: dropped. The row was deleted, or the address left the
	 *   roster; either way nothing confirms the release any more.
	 * - `No`, or no answer: dropped.
	 * - `Yes, by my blog address only` where the snapshot has them named: the name is replaced
	 *   by the blog host, and they are dropped if the snapshot holds no website for them.
	 * - `Yes, with my name` where the snapshot has them under a blog host: left as the host.
	 *   The document does not hold their name, so there is nothing to promote it to.
	 * - A quote whose live text no longer matches the stored text: dropped and counted. A
	 *   student who rewrote their answer has retracted the words that were printed, and the
	 *   institution's translation is a translation of those words and not of the new ones.
	 *
	 * @param array $snapshot The stored snapshot.
	 * @param array $feedback Rows from `shape_feedback_rows()`.
	 * @return array{students: array, quotes: array, dropped: int}
	 */
	public static function consent_view( array $snapshot, array $feedback ) {
		$live     = self::live_by_id( $feedback );
		$students = array();
		$quotes   = array();

		foreach ( $snapshot['students'] as $student ) {
			$answer = isset( $live[ $student['id'] ] ) ? $live[ $student['id'] ] : null;

			if ( ! is_array( $answer ) ) {
				continue;
			}

			if ( self::LIST_NAMED === $answer['list'] ) {
				$students[] = $student;
				continue;
			}

			if ( self::LIST_BLOG !== $answer['list'] ) {
				continue;
			}

			$host = self::host_of( $student['website'] );

			if ( '' === $host ) {
				continue;
			}

			$student['display'] = $host;
			$student['named']   = false;
			$students[]         = $student;
		}

		foreach ( $snapshot['quotes'] as $quote ) {
			$answer = isset( $live[ $quote['id'] ] ) ? $live[ $quote['id'] ] : null;

			if ( ! is_array( $answer ) || trim( (string) $answer['quote'] ) !== trim( (string) $quote['text'] ) ) {
				continue;
			}

			if ( self::QUOTE_NAMED === $answer['permission'] ) {
				$quotes[] = $quote;
				continue;
			}

			if ( self::QUOTE_ANONYMOUS !== $answer['permission'] ) {
				continue;
			}

			// Un-naming is a narrowing this document can always perform: dropping a name needs
			// nothing the snapshot has not got.
			$quote['named'] = false;
			$quote['name']  = '';
			$quotes[]       = $quote;
		}

		return array(
			'students' => $students,
			'quotes'   => $quotes,
			'dropped'  => max( 0, count( $snapshot['quotes'] ) - count( $quotes ) ),
		);
	}

	/**
	 * Live Feedback answers keyed by the id a snapshot student carries.
	 *
	 * Duplicated addresses are resolved the way `pick_feedback()` resolves them, and an id that
	 * still has two rows is left out: no entry means the student is dropped, which is the safe
	 * reading of two rows that cannot be told apart.
	 *
	 * @param array $feedback Rows from `shape_feedback_rows()`.
	 * @return array<string, array>
	 */
	private static function live_by_id( array $feedback ) {
		$out = array();

		foreach ( self::group_by_email( $feedback ) as $email_key => $candidates ) {
			$chosen = self::pick_feedback( $candidates );
			$id     = self::student_id( $email_key );

			if ( is_array( $chosen ) && '' !== $id ) {
				$out[ $id ] = $chosen;
			}
		}

		return $out;
	}

	/**
	 * Re-read the people half of a report from live data.
	 *
	 * **Allowed on a `final` report**, which is the whole reason it exists apart from
	 * `generate()`: a student who withdraws after a report has been issued must be able to
	 * reach the stored document, and reopening the report to do it would be an edit nobody
	 * asked for.
	 *
	 * It rewrites `students`, `quotes`, `events` and `withheld`, and **nothing else**. The
	 * participation numbers, the previous semester's numbers, the team counts, the index read
	 * time and the generation time all stay where they are: a number a school has already
	 * printed must not move under it because somebody pressed a consent button. The people half
	 * is rebuilt rather than filtered, so a student who has said yes since the report was
	 * generated is added, which is what makes this the button to press after `ACTION_ASK`.
	 *
	 * A quote choice is dropped when its quote has gone; its translation is dropped when the
	 * quote is still there and the words have changed, because a translation of the old words
	 * is not a translation of the new ones.
	 *
	 * @param WP_Post $post The report.
	 * @return int|WP_Error The post ID.
	 */
	public static function refresh_consent( WP_Post $post ) {
		$snapshot = self::snapshot( $post );

		if ( empty( $snapshot ) ) {
			return new WP_Error( 'wpcpm_report_no_snapshot', __( 'This report has to be generated before its consent can be checked.', 'wpcredits-program-manager' ) );
		}

		$institution = self::institution_of( $post );
		$cohort      = self::cohort_of( $post );

		if ( '' === $institution || '' === $cohort ) {
			return new WP_Error( 'wpcpm_report_unplaced', __( 'This report is not attached to an institution and a semester, so consent cannot be checked.', 'wpcredits-program-manager' ) );
		}

		$fresh = self::build( $institution, $cohort );

		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}

		$post_id  = (int) $post->ID;
		$previous = $snapshot['quotes'];

		$snapshot['students'] = $fresh['students'];
		$snapshot['quotes']   = $fresh['quotes'];
		$snapshot['events']   = $fresh['events'];
		$snapshot['withheld'] = $fresh['withheld'];

		// Written straight to the meta rather than through `save()`, so no revision is filed and
		// the post's modified time does not move: a student withdrawing is not an edit by
		// anybody at the institution, and it must not read as one in the history or refuse the
		// next save as stale.
		update_post_meta( $post_id, self::META_DATA, $snapshot );
		update_post_meta( $post_id, self::META_CHOICES, self::prune_choices( self::choices( $post ), $snapshot['quotes'], $previous ) );

		return $post_id;
	}

	/**
	 * Drop the choices for quotes that are gone, and the translations that no longer translate.
	 *
	 * @param array $choices  The stored choices.
	 * @param array $quotes   The quotes the snapshot now holds.
	 * @param array $previous The quotes it held before.
	 * @return array
	 */
	private static function prune_choices( array $choices, array $quotes, array $previous ) {
		$now = array();
		$was = array();

		foreach ( $quotes as $quote ) {
			$now[ $quote['id'] ] = (string) $quote['text'];
		}

		foreach ( $previous as $quote ) {
			if ( is_array( $quote ) && isset( $quote['id'] ) ) {
				$was[ $quote['id'] ] = isset( $quote['text'] ) ? (string) $quote['text'] : '';
			}
		}

		$out = array();

		foreach ( $choices as $id => $choice ) {
			if ( ! isset( $now[ $id ] ) ) {
				continue;
			}

			if ( isset( $was[ $id ] ) && $was[ $id ] !== $now[ $id ] ) {
				$choice['translation'] = '';
			}

			$out[ $id ] = $choice;
		}

		return $out;
	}

	/*
	 * --------------------------------------------------------------------
	 * Small pure helpers
	 * --------------------------------------------------------------------
	 */

	/**
	 * The id a student is known by inside this document.
	 *
	 * `wp_hash()` of the roster index's already-lowercased key, cut to twelve characters. It is
	 * stable across generations, so an institution's choice about a quote survives a
	 * regeneration; it lets a render match a stored student to a live Feedback row by hashing
	 * that row's address; and it keeps the address itself out of a stored document, which is
	 * the promise the no-PII test enforces.
	 *
	 * @param string $email_key A lowercased address.
	 * @return string Twelve hex characters, or an empty string.
	 */
	public static function student_id( $email_key ) {
		$email_key = strtolower( trim( (string) $email_key ) );

		if ( '' === $email_key ) {
			return '';
		}

		return substr( wp_hash( $email_key ), 0, self::ID_LENGTH );
	}

	/**
	 * A name reduced to its letters and digits, lowercased.
	 *
	 * The same normalisation the import uses to compare a file's name against a roster name.
	 * Restated here rather than shared because the import's copy is private to a class whose
	 * job is reading files, and a helper reaching across for it would tie two unrelated
	 * modules together.
	 *
	 * @param string $name A name.
	 * @return string
	 */
	private static function name_key( $name ) {
		$key = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $name );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $key ) : strtolower( (string) $key );
	}

	/**
	 * A value reduced to an http or https URL, or an empty string.
	 *
	 * Everything printed in this document goes through here. A `javascript:` or `data:` value
	 * pasted into a form field is not a link a university prints, and the document is drawn on
	 * a page a school opens and on a page it hands to a printer.
	 *
	 * @param mixed $url The raw value.
	 * @return string
	 */
	private static function clean_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$url = (string) esc_url_raw( $url, array( 'http', 'https' ) );

		if ( '' === $url ) {
			return '';
		}

		// **A URL with a userinfo part is refused whole.** `WPCPM_Field_Value::clean_url()`
		// prepends a scheme to a scheme-less value, so a student who typed their email address
		// into the website box has it stored as `https://name@example.test`, and that string is
		// not an email address to `is_email()`, so the snapshot's own guard never saw it. It was
		// then printed under the heading of the one path whose purpose is not naming the student.
		// The same shape can carry a name or a password. No genuine personal site or blog post
		// needs credentials in its address, so nothing real is lost by dropping the whole value.
		$parts = wp_parse_url( $url );

		if ( is_array( $parts ) && ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Every URL in one cell.
	 *
	 * The columns are single-URL fields on the student's form, and students paste two into one
	 * of them often enough that dropping the second would be a link a student wrote and nobody
	 * ever saw. Split on whitespace, with trailing punctuation trimmed off each piece, so a
	 * comma-separated pair works as well as a line-separated one.
	 *
	 * @param mixed $value The raw cell.
	 * @return string[] Unique, in cell order.
	 */
	private static function urls_in( $value ) {
		$out = array();

		foreach ( preg_split( '/\s+/', trim( WPCPM_Airtable::flatten( $value, ' ' ) ) ) as $piece ) {
			$url = self::clean_url( rtrim( (string) $piece, ',;.' ) );

			if ( '' !== $url ) {
				$out[ $url ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * The host of a URL, with a leading `www.` removed.
	 *
	 * What a student who chose to be listed by their blog address is listed as. `www.` goes
	 * because it is not part of how anybody says their own site's name.
	 *
	 * @param string $url A URL.
	 * @return string
	 */
	private static function host_of( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * Text trimmed and cut to `MAX_TEXT`.
	 *
	 * By characters where mbstring is available, because cutting UTF-8 by bytes can end a
	 * string in the middle of a character and a document is going into a page and a PDF.
	 *
	 * @param mixed $text The raw value.
	 * @return string
	 */
	private static function cap( $text ) {
		$text = trim( (string) $text );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, self::MAX_TEXT );
		}

		return substr( $text, 0, self::MAX_TEXT );
	}

	/**
	 * The narrative as plain text, for `post_content` and the revision diff.
	 *
	 * Hidden sections are included and marked, because a revision has to record that a section
	 * was hidden as much as that its wording changed. Nothing reads this back.
	 *
	 * @param array $narratives Section key => `text`, `hidden`.
	 * @return string
	 */
	private static function narrative_text( array $narratives ) {
		$parts = array();

		foreach ( self::sections() as $key => $section ) {
			if ( ! isset( $narratives[ $key ] ) ) {
				continue;
			}

			$title = $section['title'];

			if ( ! empty( $narratives[ $key ]['hidden'] ) ) {
				/* translators: %s: a report section's title. */
				$title = sprintf( __( '%s (hidden)', 'wpcredits-program-manager' ), $title );
			}

			$parts[] = $title . "\n\n" . $narratives[ $key ]['text'];
		}

		return trim( implode( "\n\n", $parts ) );
	}

	/**
	 * Narratives with every section present and every value typed.
	 *
	 * @param array $stored Whatever was stored or submitted.
	 * @return array<string, array{text: string, hidden: bool}>
	 */
	private static function shape_narratives( array $stored ) {
		$out = array();

		foreach ( self::sections() as $key => $section ) {
			$given = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();

			$out[ $key ] = array(
				'text'   => array_key_exists( 'text', $given ) ? self::cap( sanitize_textarea_field( (string) $given['text'] ) ) : $section['default'],
				'hidden' => ! empty( $given['hidden'] ),
			);
		}

		return $out;
	}

	/**
	 * Quote choices with every value typed.
	 *
	 * Which ids are allowed through is the caller's question, not this one's: the handler reads
	 * choices only for ids that are in the snapshot, and that is where the rule belongs because
	 * that is where the snapshot is.
	 *
	 * @param array $stored Whatever was stored or submitted.
	 * @return array<string, array{include: bool, translation: string, show_name: bool}>
	 */
	private static function shape_choices( array $stored ) {
		$out = array();

		foreach ( $stored as $id => $choice ) {
			if ( ! is_string( $id ) || '' === $id || ! is_array( $choice ) ) {
				continue;
			}

			$out[ $id ] = array(
				'include'     => ! empty( $choice['include'] ),
				'translation' => isset( $choice['translation'] ) ? self::cap( sanitize_textarea_field( (string) $choice['translation'] ) ) : '',
				'show_name'   => ! empty( $choice['show_name'] ),
			);
		}

		return $out;
	}

	/**
	 * A snapshot with every key present and typed.
	 *
	 * The one place the stored shape is written down in code, so a renderer never guards and a
	 * key that grew a new sub-key in a later version reads with a default rather than a notice.
	 *
	 * @param array $stored The stored or freshly assembled snapshot.
	 * @return array
	 */
	private static function shape_snapshot( array $stored ) {
		$participation = isset( $stored['participation'] ) && is_array( $stored['participation'] ) ? $stored['participation'] : array();
		$previous      = isset( $stored['previous'] ) && is_array( $stored['previous'] ) ? $stored['previous'] : array();
		$withheld      = isset( $stored['withheld'] ) && is_array( $stored['withheld'] ) ? $stored['withheld'] : array();

		$counts = array();

		foreach ( array( 'signed_up', 'graduated', 'pending', 'active', 'withdrawn', 'not_started', 'other' ) as $bucket ) {
			$counts[ $bucket ] = isset( $participation[ $bucket ] ) ? (int) $participation[ $bucket ] : 0;
		}

		return array(
			'v'             => self::VERSION,
			'generated'     => isset( $stored['generated'] ) ? (int) $stored['generated'] : 0,
			'read'          => isset( $stored['read'] ) ? (int) $stored['read'] : 0,
			'cohort'        => isset( $stored['cohort'] ) ? (string) $stored['cohort'] : '',
			'participation' => $counts,
			'previous'      => array(
				'key'       => isset( $previous['key'] ) ? (string) $previous['key'] : '',
				'signed_up' => isset( $previous['signed_up'] ) ? (int) $previous['signed_up'] : 0,
				'graduated' => isset( $previous['graduated'] ) ? (int) $previous['graduated'] : 0,
				'has_rows'  => ! empty( $previous['has_rows'] ),
			),
			'teams'         => isset( $stored['teams'] ) && is_array( $stored['teams'] ) ? array_values( $stored['teams'] ) : array(),
			'students'      => isset( $stored['students'] ) && is_array( $stored['students'] ) ? array_values( $stored['students'] ) : array(),
			'events'        => isset( $stored['events'] ) && is_array( $stored['events'] ) ? array_values( $stored['events'] ) : array(),
			'quotes'        => isset( $stored['quotes'] ) && is_array( $stored['quotes'] ) ? array_values( $stored['quotes'] ) : array(),
			'withheld'      => array(
				'no_answer' => isset( $withheld['no_answer'] ) ? (int) $withheld['no_answer'] : 0,
				'declined'  => isset( $withheld['declined'] ) ? (int) $withheld['declined'] : 0,
				'ambiguous' => isset( $withheld['ambiguous'] ) ? (int) $withheld['ambiguous'] : 0,
			),
		);
	}
}
