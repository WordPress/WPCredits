<?php
/**
 * The annual sponsor report: one private post per sponsor per term.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store and the generator of a sponsor's annual report (spec section 10), in the semester
 * report's shape: generated figures, a manager's narratives, two states.
 *
 * **One report per sponsor per term, keyed by the term's last day.** A term is the twelve months
 * ending on the sponsor's `tier_until` (the owner, 25 September 2026: "plan on the term"), so a
 * report is found by its sponsor and its period end (`find()`), and a second report for one end is
 * refused rather than written over the first. The daily run drafts one the day after a term ends
 * (`maybe_draft()`); a manager may draft one for any period end on which the sponsor's tier carried
 * a report.
 *
 * **One draft of a term at a time.** The daily run drafts a term's report the day after the term
 * ends, and a manager may press Draft a report for the same term in the same moment; each would
 * find no report and store one. `generate()` decides and writes under a lock per sponsor, the
 * option `LOCK_PREFIX` and the key, taken as the events store takes its own, and refuses a draft
 * asked for while another holds it as `busy`.
 *
 * **A renewal made before a term ends keeps that term's report.** Renewal is a manager moving the
 * day on the Sponsors screen, which the thirty-day mail asks for, so by the day a term ends the row
 * may already describe the next one. The Edit save records the term it moved off on the row, as
 * `report_owed` (`owed_after_save()`): its tier, its last day and its funded line, when that tier
 * carried a report and none exists yet. The daily run, the due list and a manager's draft for that
 * day read it before the row's own term, and take the tier and the funded line for that end from
 * it; it is cleared once a report for that end exists.
 *
 * **The snapshot is the site's, the narratives the manager's.** `META_DATA` holds the generated
 * document, versioned, so a snapshot at another version reads as absent rather than misread;
 * `META_SECTIONS` holds what a manager writes under the narrative sections. Both are revisioned
 * meta, written before `wp_update_post()`, where WordPress takes the revision, so every revision of
 * a report holds the words and the figures they were written against. A draft starts each track
 * block's narrative with the partners' narrative its track was last published with, and the
 * report's copy is its own from then on. A regeneration makes a fresh snapshot in the same post and
 * keeps the narratives, and says so on the audit trail.
 *
 * **States draft and final.** A draft is a manager's to write; a final report is the sponsor's to
 * read. A final report is neither saved nor regenerated until a manager reopens it. Every draft,
 * regeneration and state move is a row on the sponsors module's audit trail
 * (`WPCPM_Institution_Audit::record_sponsor()`), of the kinds below.
 *
 * **Counts, the mentors' names, and nothing else about a person.** The figures come from
 * `WPCPM_Sponsor_Report_Numbers`, the one reader of the records for a period, and a title names the
 * company and two months. `bin/test-sponsor-report.php` walks the snapshots, the titles and every
 * tier's sections for the students and institutions it seeds.
 *
 * This file stores and reads: it draws nothing and reads no request, and whether a person may do
 * any of this is the caller's to decide. `bin/test-sponsor-policy.php` scans it, with every other
 * sponsor file, for a sponsor ID compared with `===`.
 */
final class WPCPM_Sponsor_Report {

	/**
	 * The post that holds one sponsor's report for one term.
	 *
	 * Twenty characters, the most `register_post_type()` accepts: a longer name is refused with a
	 * `WP_Error` nothing reads, and the type then silently does not exist while `get_posts()` goes
	 * on querying it. `bin/test-roles.php` measures every name this plugin declares.
	 *
	 * @var string
	 */
	const POST_TYPE = 'wpcpm_sponsor_report';

	/** The snapshot's shape version; a snapshot stored at another version reads as absent. */
	const VERSION = 1;

	/** The sponsor the report is about: its key, written once, and the key a report is found by. */
	const META_SPONSOR = '_wpcpm_sreport_sponsor';

	/** The period's last day, `Y-m-d`: with the sponsor, what a report is keyed by. */
	const META_UNTIL = '_wpcpm_sreport_until';

	/** The snapshot, as `build()` writes it. Revisioned. */
	const META_DATA = '_wpcpm_sreport_data';

	/** The narratives, narrative key => text, the keys `narrative_keys()` names. Revisioned. */
	const META_SECTIONS = '_wpcpm_sreport_sections';

	/** `draft` or `final`. */
	const META_STATE = '_wpcpm_sreport_state';

	/** Who marked the report final and when: `array( 'at' => int, 'by' => int )`. Deleted on reopen. */
	const META_FINAL = '_wpcpm_sreport_final';

	/** The attached PDF, as `WPCPM_Private_Files::store()` answers: `array( 'path', 'sha256', 'size' )`. */
	const META_FILE = '_wpcpm_sreport_file';

	/** The attached PDF's name as a manager uploaded it, for managers only. */
	const META_FILE_NAME = '_wpcpm_sreport_file_name';

	/** Being written by a manager; the sponsor sees nothing of it. */
	const STATE_DRAFT = 'draft';

	/** Marked final by a manager; the sponsor's members read it. */
	const STATE_FINAL = 'final';

	// The sections, in the order a report prints them.
	const SECTION_PROGRAM     = 'program';
	const SECTION_SPONSORSHIP = 'sponsorship';
	const SECTION_COST_LINE   = 'cost_line';
	const SECTION_TRACK       = 'track';
	const SECTION_AHEAD       = 'ahead';

	// The kinds of the report's rows on the sponsors module's audit trail.
	const LOG_DRAFTED  = 'sponsor_report_drafted';
	const LOG_FINAL    = 'sponsor_report_final';
	const LOG_REOPENED = 'sponsor_report_reopened';
	const LOG_PDF      = 'sponsor_report_pdf';

	/**
	 * A program manager said a moved day was no renewal, and the term it moved off is owed no report:
	 * the kind the Sponsors screen's Not a renewal writes when it clears `report_owed`.
	 */
	const LOG_OWED_CLEARED = 'sponsor_report_owed_cleared';

	/** A manager asked for the report: the audit row's ground is `manager`. */
	const ORIGIN_MANAGER = 'manager';

	/** The daily run drafted it: the audit row's ground is `system`. */
	const ORIGIN_SYSTEM = 'system';

	/**
	 * The option one sponsor's lock is held as: this prefix and the sponsor's key. Held while a
	 * report of the sponsor's is drafted, and read from the options table itself when a draft takes
	 * it (`lock()`), so the daily run and a manager's draft of one term cannot both store one.
	 */
	const LOCK_PREFIX = 'wpcpm_sreport_lock_';

	/**
	 * How long a lock is honored, in seconds.
	 *
	 * Five minutes, the semester report's figure for its own generation: a draft reads its figures
	 * over every institution's roster, so it can take a while, and a lock older than that belonged to
	 * a request that died between taking it and giving it back. Left in place, it would refuse every
	 * draft of the sponsor's reports.
	 */
	const LOCK_TIMEOUT = 300;

	/**
	 * The longest narrative a block takes, in characters as the manager typed it: each line break,
	 * and each entity the cleaner wrote, counted once (`WPCPM_Typed_Text::typed_length()`), as the
	 * editor's box counts them against its `maxlength`.
	 */
	const MAX_TEXT = 5000;

	/*
	 * --------------------------------------------------------------------
	 * Registration
	 * --------------------------------------------------------------------
	 */

	/**
	 * Hooks: the post type and its two revisioned meta keys, both on `init` at 5, the sponsors
	 * store's own priority, ahead of anything that could query a report on the same request. The
	 * meta after the type: a key registered with revisions needs a type that supports them.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 5 );
	}

	/**
	 * Register the report post type.
	 *
	 * The semester report's arguments: not public, not queryable, not in search, no screen, no
	 * menu, not in REST, and a capability type nothing is granted. A draft names mentors and the
	 * company's own figures, which is nobody's business before a manager marks it final, and the
	 * one way to it is the plugin's own screens. `revisions` so every save a manager makes is kept;
	 * no `editor`, because nothing is written to `post_content`: the narratives are meta, and a
	 * revision carries them.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor reports', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor report', 'wpcredits-program-manager' ),
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
				'supports'            => array( 'title', 'revisions' ),
				'capability_type'     => array( self::POST_TYPE, self::POST_TYPE . 's' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register the narratives and the snapshot as revisioned meta.
	 *
	 * The narratives, so every save is kept as a version; the snapshot beside them, so a version
	 * holds the figures its words were written against, and a regeneration is itself a version.
	 * `revisions_enabled` landed in WordPress 6.4, inside this plugin's 6.5 floor. Not in REST and
	 * not writable through the meta API, `auth_callback` said rather than inherited: the one road to
	 * either value is this class.
	 */
	public static function register_meta() {
		foreach ( array( self::META_SECTIONS, self::META_DATA ) as $key ) {
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
	 * The period and the title
	 * --------------------------------------------------------------------
	 */

	/**
	 * The twelve months a report covers: the year ending on its last day.
	 *
	 * Reckoned at noon in UTC, so that neither the timezone PHP runs in nor the site's can move a
	 * day. The day after the end, then a year back, and not a year back and then a day on: a year
	 * back from 29 February lands on a day that does not exist and rolls into March, so the term
	 * ending on 29 February would start on 2 March, leaving 1 March in no term, and the term ending
	 * a year later would start on 29 February, giving that day to two. This way consecutive terms
	 * tile the calendar, and every other end day gives the same first day either way.
	 *
	 * @param string $until The period's last day, `Y-m-d`.
	 * @return array{from: string, until: string} Both '' for a value that is not a day.
	 */
	public static function period( $until ) {
		$until = is_scalar( $until ) ? (string) $until : '';
		$none  = array(
			'from'  => '',
			'until' => '',
		);

		if ( ! self::is_period_end( $until ) ) {
			return $none;
		}

		$day = DateTime::createFromFormat( '!Y-m-d H:i:s', $until . ' 12:00:00', new DateTimeZone( 'UTC' ) );

		if ( false === $day ) {
			return $none;
		}

		$day->modify( '+1 day' );
		$day->modify( '-1 year' );

		return array(
			'from'  => $day->format( 'Y-m-d' ),
			'until' => $until,
		);
	}

	/**
	 * Whether a value is a day a report can end on: a real day, written `Y-m-d`.
	 *
	 * @param mixed $value Anything.
	 * @return bool
	 */
	public static function is_period_end( $value ) {
		return is_string( $value ) && WPCPM_Sponsor_Tiers::is_date( $value );
	}

	/**
	 * What a report is called: the sponsor's name and the months its period starts and ends in, the
	 * heading of the document as well as the post's title.
	 *
	 * @param array $row    The sponsor's row, read for `name`, and `record_id` when it has none.
	 * @param array $period As `period()` answers it.
	 * @return string
	 */
	public static function title_for( array $row, array $period ) {
		return sprintf(
			/* translators: 1: the sponsor's name, 2: the month and year the report's period starts in, 3: the month and year it ends in. */
			__( '%1$s, %2$s to %3$s', 'wpcredits-program-manager' ),
			self::name_of( $row ),
			self::month_of( isset( $period['from'] ) ? (string) $period['from'] : '' ),
			self::month_of( isset( $period['until'] ) ? (string) $period['until'] : '' )
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Finding and reading a report
	 * --------------------------------------------------------------------
	 */

	/**
	 * The report for one sponsor and one period end, or null.
	 *
	 * Both values are shape-checked first, so a pasted string finds nothing: an empty meta value
	 * would otherwise match every report whose own value is empty.
	 *
	 * **A fresh look reads the table.** WP_Query keeps a query's answer in the object cache, and in
	 * one request that answer stands until the request itself writes a post or a post's meta, so a
	 * look this request made a moment before, the daily run's own just ahead of its draft, would
	 * answer again without a report another request has stored since. `generate()` looks under its
	 * lock, where the answer decides whether a second report is stored, so it asks for no cache and
	 * no filter.
	 *
	 * @param string $record Sponsor key.
	 * @param string $until  The period's last day, `Y-m-d`.
	 * @param bool   $fresh  Whether to read past this request's caches, as the look under the lock does.
	 * @return WP_Post|null
	 */
	public static function find( $record, $until, $fresh = false ) {
		$record = trim( (string) $record );
		$until  = is_scalar( $until ) ? (string) $until : '';

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) || ! self::is_period_end( $until ) ) {
			return null;
		}

		$query = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => self::META_SPONSOR,
					'value' => $record,
				),
				array(
					'key'   => self::META_UNTIL,
					'value' => $until,
				),
			),
		);

		if ( $fresh ) {
			$query['suppress_filters'] = true;
			$query['cache_results']    = false;
		}

		$found = get_posts( $query );

		return isset( $found[0] ) && self::is_report( $found[0] ) ? $found[0] : null;
	}

	/**
	 * Every report a sponsor has, the newest period first.
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[] A list; `array()` for a value that is not a key.
	 */
	public static function reports_of( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_SPONSOR,
						'value' => $record,
					),
				),
			)
		);

		$posts = array_values(
			array_filter(
				(array) $posts,
				static function ( $post ) {
					return self::is_report( $post );
				}
			)
		);

		usort(
			$posts,
			static function ( $a, $b ) {
				$order = strcmp( self::until_of( $b ), self::until_of( $a ) );

				return 0 !== $order ? $order : (int) $b->ID - (int) $a->ID;
			}
		);

		return $posts;
	}

	/**
	 * The stored snapshot, every key present and typed, or an empty array at a version this code
	 * does not know.
	 *
	 * An empty array is the one answer a caller has to branch on, and it means "generate this again":
	 * a document drawn from a shape this code does not know would be a document with the wrong
	 * things in it.
	 *
	 * @param WP_Post $post The report.
	 * @return array `v`, `generated` (a Unix time), `as_of` (the day its figures were read), `sponsor`,
	 *               `from`, `until`, `program` (`WPCPM_Sponsor_Report_Numbers::program()`'s shape),
	 *               `sponsorship` (its `sponsorship()` shape) and `tracks` (a list, each `key`, the
	 *               program figures but `finished`, and `label`); or `array()`.
	 */
	public static function snapshot( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_DATA, true );

		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::VERSION !== (int) $stored['v'] ) {
			return array();
		}

		$tracks = array();

		foreach ( isset( $stored['tracks'] ) && is_array( $stored['tracks'] ) ? $stored['tracks'] : array() as $track ) {
			if ( ! is_array( $track ) || '' === self::text( $track, 'key' ) ) {
				continue;
			}

			$tracks[] = array_merge( array( 'key' => self::text( $track, 'key' ) ), self::shape_counts( $track, WPCPM_Sponsor_Report_Numbers::TRACK_COUNTS ), array( 'label' => self::text( $track, 'label' ) ) );
		}

		return array(
			'v'           => self::VERSION,
			'generated'   => isset( $stored['generated'] ) ? (int) $stored['generated'] : 0,
			'as_of'       => self::text( $stored, 'as_of' ),
			'sponsor'     => self::text( $stored, 'sponsor' ),
			'from'        => self::text( $stored, 'from' ),
			'until'       => self::text( $stored, 'until' ),
			'program'     => self::shape_counts( isset( $stored['program'] ) && is_array( $stored['program'] ) ? $stored['program'] : array() ),
			'sponsorship' => self::shape_sponsorship( isset( $stored['sponsorship'] ) && is_array( $stored['sponsorship'] ) ? $stored['sponsorship'] : array() ),
			'tracks'      => $tracks,
		);
	}

	/**
	 * The sections a report of a tier carries, in the order it prints them, from the policy.
	 *
	 * The program and the sponsorship for every tier that includes `report_program`; the cost line
	 * for one that includes `report_cost_line`; the track for one that includes `report_track`,
	 * drawn once, and written under once, for each track the sponsor partners, and left out when it
	 * partners none, since a heading with nothing under it reads as a broken report; looking ahead
	 * always. The track narrative is the report's own, begun at the draft with the partners'
	 * narrative its track was last published with (`pull_narratives()`): one shared by a track's
	 * partners would need a period the partners have in common, and terms end on different days.
	 *
	 * @param string $tier   One of `WPCPM_Sponsor_Tiers::KEYS`: the tier held on the period's last day.
	 * @param array  $tracks The tracks the sponsor partners, as `WPCPM_Tracks::partner_tracks()`
	 *                       answers them or a snapshot holds them: each `key`, and `label`.
	 * @return array<string, array{title: string, generated: bool, narrative: bool, per_track: array<string, string>}>
	 *         `generated` says the section draws figures from the snapshot, `narrative` that a
	 *         manager writes under it, and `per_track` names the tracks it is drawn once for, key
	 *         => name, empty for a section drawn once.
	 */
	public static function sections_for( $tier, array $tracks ) {
		$tier     = (string) $tier;
		$sections = array();

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'report_program' ) ) {
			$sections[ self::SECTION_PROGRAM ]     = self::section( __( 'The program this year', 'wpcredits-program-manager' ), true, false );
			$sections[ self::SECTION_SPONSORSHIP ] = self::section( __( 'Your sponsorship', 'wpcredits-program-manager' ), true, false );
		}

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'report_cost_line' ) ) {
			$sections[ self::SECTION_COST_LINE ] = self::section( __( 'The cost line', 'wpcredits-program-manager' ), false, true );
		}

		$per_track = array();

		foreach ( $tracks as $track ) {
			$key = is_array( $track ) ? self::text( $track, 'key' ) : '';

			if ( '' === $key ) {
				continue;
			}

			$label             = self::text( $track, 'label' );
			$per_track[ $key ] = '' !== $label ? $label : $key;
		}

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'report_track' ) && ! empty( $per_track ) ) {
			$sections[ self::SECTION_TRACK ] = self::section( _n( 'The track', 'The tracks', count( $per_track ), 'wpcredits-program-manager' ), true, true, $per_track );
		}

		$sections[ self::SECTION_AHEAD ] = self::section( __( 'Looking ahead', 'wpcredits-program-manager' ), false, true );

		return $sections;
	}

	/**
	 * The sections of one report: its stored tier's and its stored tracks', so a report keeps the
	 * sections it was generated with whatever the sponsor's tier is today.
	 *
	 * @param WP_Post $post The report.
	 * @return array As `sections_for()` answers; `array()` for a report whose snapshot cannot be
	 *               read, which has to be generated again before anything is drawn or written.
	 */
	public static function sections_of( WP_Post $post ) {
		$snapshot = self::snapshot( $post );

		if ( empty( $snapshot ) ) {
			return array();
		}

		return self::sections_for( $snapshot['sponsorship']['tier'], $snapshot['tracks'] );
	}

	/**
	 * The narrative keys a report's sections take, in order: a section's own key, or, for a section
	 * drawn once per track, its key and the track's, `track_dev`.
	 *
	 * @param array $sections As `sections_for()` answers.
	 * @return string[]
	 */
	public static function narrative_keys( array $sections ) {
		$keys = array();

		foreach ( $sections as $key => $section ) {
			if ( ! is_array( $section ) || empty( $section['narrative'] ) ) {
				continue;
			}

			if ( ! empty( $section['per_track'] ) && is_array( $section['per_track'] ) ) {
				foreach ( array_keys( $section['per_track'] ) as $track ) {
					$keys[] = $key . '_' . $track;
				}

				continue;
			}

			$keys[] = (string) $key;
		}

		return $keys;
	}

	/**
	 * The narratives of a report, one per key its sections take, in order, '' where nothing is
	 * written yet.
	 *
	 * A key the stored narratives hold and the sections no longer take is not answered, and not
	 * deleted either: a report generated again under another tier keeps the words until a section
	 * takes them again.
	 *
	 * @param WP_Post $post The report.
	 * @return array<string, string>
	 */
	public static function narratives( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SECTIONS, true );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array();

		foreach ( self::narrative_keys( self::sections_of( $post ) ) as $key ) {
			$out[ $key ] = isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
		}

		return $out;
	}

	/**
	 * Which of the two states a report is in.
	 *
	 * Anything but `final` is a draft: a report whose state was lost, or holds a word this store does
	 * not know, is never a document the sponsor reads.
	 *
	 * @param WP_Post $post The report.
	 * @return string `draft` or `final`.
	 */
	public static function state( WP_Post $post ) {
		return self::STATE_FINAL === (string) get_post_meta( (int) $post->ID, self::META_STATE, true ) ? self::STATE_FINAL : self::STATE_DRAFT;
	}

	/**
	 * When and by whom a report was marked final, or an empty array for a draft.
	 *
	 * @param WP_Post $post The report.
	 * @return array `array()` or `array( 'at' => int, 'by' => int )`.
	 */
	public static function final_at( WP_Post $post ) {
		$stamp = get_post_meta( (int) $post->ID, self::META_FINAL, true );

		if ( ! is_array( $stamp ) || empty( $stamp['at'] ) ) {
			return array();
		}

		return array(
			'at' => (int) $stamp['at'],
			'by' => isset( $stamp['by'] ) ? max( 0, (int) $stamp['by'] ) : 0,
		);
	}

	/**
	 * The sponsor a report is about, as the post itself records it.
	 *
	 * @param WP_Post $post The report.
	 * @return string A sponsor key, or ''.
	 */
	public static function sponsor_of( WP_Post $post ) {
		$record = trim( (string) get_post_meta( (int) $post->ID, self::META_SPONSOR, true ) );

		return WPCPM_Sponsors_Index::is_key( $record ) ? $record : '';
	}

	/**
	 * The last day of a report's period, as the post itself records it.
	 *
	 * @param WP_Post $post The report.
	 * @return string `Y-m-d`, or ''.
	 */
	public static function until_of( WP_Post $post ) {
		$until = (string) get_post_meta( (int) $post->ID, self::META_UNTIL, true );

		return self::is_period_end( $until ) ? $until : '';
	}

	/**
	 * The term a renewal moved off, as the row records it, when it is one: a tier and a real day,
	 * with the line that term's money funded.
	 *
	 * Public for the Sponsors screen, which says while a term is owed its report, drafts for its day
	 * by default and offers Not a renewal, and has to read the owed term by this rule and no other.
	 *
	 * @param array $row The sponsor's row.
	 * @return array `tier`, `until` and `funded_line` ('' when the term had none), or `array()`.
	 */
	public static function owed_of( array $row ) {
		$owed  = isset( $row['report_owed'] ) && is_array( $row['report_owed'] ) ? $row['report_owed'] : array();
		$tier  = isset( $owed['tier'] ) ? (string) $owed['tier'] : '';
		$until = isset( $owed['until'] ) ? (string) $owed['until'] : '';

		if ( ! WPCPM_Sponsor_Tiers::is_tier( $tier ) || ! self::is_period_end( $until ) ) {
			return array();
		}

		return array(
			'tier'        => $tier,
			'until'       => $until,
			'funded_line' => isset( $owed['funded_line'] ) && is_scalar( $owed['funded_line'] ) ? trim( (string) $owed['funded_line'] ) : '',
		);
	}

	/*
	 * --------------------------------------------------------------------
	 * Generating and writing
	 * --------------------------------------------------------------------
	 */

	/**
	 * Draft a sponsor's report for the period ending on a day.
	 *
	 * Refused, with nothing written: a sponsor the store does not hold; a value that is not a day; a
	 * period end a report already exists for ("open it", never a second report over the first); and
	 * a tier that carries no report on that day, the tier held on the period's last day
	 * (`WPCPM_Sponsor_Tiers::effective()` on it), so a day after a term ends is a Friend's.
	 * For the end of a term a renewal moved off (`report_owed`), the tier and the funded line are the
	 * ones that term held, whatever the row holds now, and once the report is stored the owed term is
	 * cleared. The narratives start empty, but for a track block whose track was last published with
	 * a partners' narrative, which starts with those words.
	 *
	 * **Under the sponsor's lock.** The daily run and a manager's Draft a report can ask for one term
	 * in the same moment, and each would find no report and store one. So everything that decides
	 * whether a report is made, and makes it, happens under the sponsor's lock (`lock()`): taken
	 * before the row is read and given back once the post and every key of it are stored, as it is
	 * on every refusal and as a thrown error unwinds. A draft asked for while another holds it
	 * is refused as `busy`, and nothing is written; the look for the report under it is a fresh one.
	 * A value that is no key names no sponsor and no lock, and is refused before one is taken. The
	 * owed term is cleared and the audit row written once the lock is given back: the report is
	 * stored by then, and a draft asked for next finds it.
	 *
	 * @param string $record Sponsor key.
	 * @param string $until  The period's last day, `Y-m-d`.
	 * @param string $origin `manager` (the default) or `system`, the daily run: the audit row's
	 *                       ground (the system's too for a manager's draft with no account signed
	 *                       in), and a system draft has no author.
	 * @param string $today  `Y-m-d`, the day the figures are read, which the snapshot records; ''
	 *                       for today.
	 * @return int|WP_Error The report's post ID, or the refusal: `wpcpm_sreport_sponsor`,
	 *                      `wpcpm_sreport_date`, `wpcpm_sreport_exists`, `wpcpm_sreport_tier`,
	 *                      `busy`, `wpcpm_sreport_not_created` or the insert's own.
	 */
	public static function generate( $record, $until, $origin = self::ORIGIN_MANAGER, $today = '' ) {
		$record = trim( (string) $record );
		$until  = is_scalar( $until ) ? (string) $until : '';
		$system = self::ORIGIN_SYSTEM === $origin;
		$actor  = $system ? 0 : get_current_user_id();

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return self::unknown_sponsor();
		}

		if ( ! self::lock( $record ) ) {
			return new WP_Error( 'busy', __( 'A report for this sponsor is being drafted right now. Try again in a minute.', 'wpcredits-program-manager' ) );
		}

		// Given back however the locked part ends, since it answers from several places.
		try {
			$row     = WPCPM_Sponsors_Index::row( $record );
			$post_id = null === $row ? self::unknown_sponsor() : self::create( $record, $row, $until, $actor, $today );
		} finally {
			self::unlock( $record );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::clear_owed( $record, $row, $until );

		self::audit(
			self::LOG_DRAFTED,
			$record,
			$post_id,
			$actor,
			$system ? WPCPM_Institution_Audit::GROUND_SYSTEM : self::ground_for( $actor ),
			$system
				/* translators: %s: the last day of the sponsor's term, in the site's date format. */
				? sprintf( __( 'The daily run drafted a sponsor report for the term ending %s.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $until ) )
				/* translators: %s: the last day of the sponsor's term, in the site's date format. */
				: sprintf( __( 'A sponsor report was drafted for the term ending %s.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $until ) ),
			array(
				'until'       => $until,
				'regenerated' => 0,
			)
		);

		return $post_id;
	}

	/**
	 * Generate a draft report's snapshot again, in the same post, and keep its narratives.
	 *
	 * For a sponsor whose records moved since the draft: an import landed, a mentor's students were
	 * synced, the company renamed itself, which the title follows. Refused for a final report, which
	 * is the sponsor's to read and has to be reopened first, for a sponsor the store no longer
	 * holds, and for a tier that carries no report on the period's last day any more.
	 *
	 * **The tier and the funded line are the ones the report's term held.** While the row's own term
	 * ends on the report's day, the row is the record of that term and is read as `generate()` reads
	 * it, so a tier or a line a manager set on it since decides; once a renewal has moved the row on,
	 * the row describes another term, and the tier and the line the report was generated with stand,
	 * so a Backer year stays a Backer's report, funding what it funded, after a renewal to Champion.
	 *
	 * **The narratives are the report's own,** a track's words pulled at the draft among them: nothing
	 * is pulled again, so a track published with other words since, or a block a manager emptied on
	 * purpose, stays as the report holds it.
	 *
	 * The snapshot is written before the post is updated, so the revision that update files holds it
	 * beside the narratives. Once the snapshot is written the regeneration is stored: a refused update,
	 * which would only have moved the title and the modified time, loses none of it, so it is no
	 * failure, and the audit says the report was generated again either way, by the account asking,
	 * or on the system's ground when there is none.
	 *
	 * @param WP_Post $post  The report.
	 * @param string  $today `Y-m-d`, the day the figures are read; '' for today.
	 * @return bool Whether a fresh snapshot was stored.
	 */
	public static function regenerate( WP_Post $post, $today = '' ) {
		if ( ! self::is_report( $post ) || self::STATE_FINAL === self::state( $post ) ) {
			return false;
		}

		$record = self::sponsor_of( $post );
		$until  = self::until_of( $post );
		$row    = WPCPM_Sponsors_Index::row( $record );

		if ( null === $row || '' === $until ) {
			return false;
		}

		$term = self::term_row( $row, $until );

		// A row that no longer describes the report's term, moved on by a renewal: the tier and the
		// funded line the report was generated with are the term's.
		if ( ! isset( $term['tier_until'] ) || (string) $term['tier_until'] !== $until ) {
			$snapshot = self::snapshot( $post );
			$held     = empty( $snapshot ) ? '' : $snapshot['sponsorship']['tier'];

			if ( WPCPM_Sponsor_Tiers::is_tier( $held ) ) {
				$term = array_merge(
					$row,
					array(
						'tier'        => $held,
						'tier_until'  => $until,
						'funded_line' => $snapshot['sponsorship']['funded_line'],
					)
				);
			}
		}

		$tier = WPCPM_Sponsor_Tiers::effective( $term, $until );

		if ( ! WPCPM_Sponsor_Tiers::includes( $tier, 'report_program' ) ) {
			return false;
		}

		$post_id = (int) $post->ID;

		update_post_meta( $post_id, self::META_DATA, wp_slash( self::build( $record, $term, $until, $tier, $today ) ) );

		// The title follows a renamed company, and the update files the revision; its answer is not
		// the regeneration's, which is stored already.
		wp_update_post(
			wp_slash(
				array(
					'ID'         => $post_id,
					'post_title' => self::title_for( $row, self::period( $until ) ),
				)
			),
			true
		);

		$actor = get_current_user_id();

		self::audit(
			self::LOG_DRAFTED,
			$record,
			$post_id,
			$actor,
			self::ground_for( $actor ),
			/* translators: %s: the last day of the sponsor's term, in the site's date format. */
			sprintf( __( 'A sponsor report was generated again for the term ending %s, and its narratives were kept.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $until ) ),
			array(
				'until'       => $until,
				'regenerated' => 1,
			)
		);

		return true;
	}

	/**
	 * Store a manager's narratives on a draft report.
	 *
	 * The text a caller names replaces what is stored under that key, tags stripped and each line
	 * break one `\n` (`clean_text()`); a key the report's sections take and the caller leaves out
	 * keeps its text; a key they do not take is ignored. Refused for a final report and for one whose
	 * snapshot cannot be read, which has no sections to write under.
	 *
	 * **A text that reads as the stored one is the stored one.** The editor draws each box as the
	 * manager typed it (`WPCPM_Typed_Text::typed_text()`), so a box posted back unedited can come back
	 * in other bytes: a ">" the box held as `&gt;` after a "<" the cleaner would read as a tag, a
	 * break kept as CR LF, or without the space or break an earlier version's cut left at the end,
	 * which the cleaner trims (`changed_narratives()`). Read the same, it keeps the stored text
	 * whatever its length, and a save that changes no text writes nothing: no meta, no last editor,
	 * no revision (`changes_narratives()` says so beforehand). **A changed text is refused, never cut,
	 * past `MAX_TEXT` as typed** (`long_narrative()`), since a text cut short would reach the sponsor
	 * missing its end; and the whole save is refused with it, every other box's words too, by the
	 * rule the Sponsors screen's Edit form keeps: one value that fails its rule refuses the whole
	 * save, so a press stores all it was given or nothing.
	 *
	 * **The meta is written before the post, and that order is load-bearing.** WordPress takes the
	 * revision inside `wp_update_post()`, copying the meta registered with `revisions_enabled` as
	 * it stands at that moment; the other way round, every revision would hold the save before it.
	 * Once the meta is written the words are stored: a refused update, which would only have taken
	 * the revision and moved the modified time, is no failure of the save, as with a regeneration.
	 *
	 * @param WP_Post $post    The report.
	 * @param array   $texts   Narrative key => text.
	 * @param int     $user_id The manager saving, recorded as the post's last editor, the key
	 *                         WordPress's own editor writes.
	 * @return bool Whether the narratives are stored: true once the words are there, a save that
	 *              changed none included. False only for a post that is not a report, a final
	 *              report, one whose snapshot cannot be read, and a text over `MAX_TEXT`.
	 */
	public static function save_narratives( WP_Post $post, array $texts, $user_id ) {
		if ( ! self::is_report( $post ) || self::STATE_FINAL === self::state( $post ) ) {
			return false;
		}

		$keys = self::narrative_keys( self::sections_of( $post ) );

		if ( empty( $keys ) || ! empty( self::long_narrative( $post, $texts ) ) ) {
			return false;
		}

		$post_id = (int) $post->ID;
		$stored  = self::stored_narratives( $post_id );
		$changed = false;

		foreach ( self::changed_narratives( $keys, $stored, $texts ) as $key => $text ) {
			$stored[ $key ] = $text;
			$changed        = true;
		}

		if ( ! $changed ) {
			return true;
		}

		update_post_meta( $post_id, self::META_SECTIONS, wp_slash( $stored ) );

		if ( (int) $user_id > 0 ) {
			update_post_meta( $post_id, '_edit_last', (int) $user_id );
		}

		// The update files the revision; its answer is not the save's, which is stored already.
		wp_update_post( array( 'ID' => $post_id ), true );

		return true;
	}

	/**
	 * The first narrative a save would refuse for its length: in the order of the report's sections,
	 * the first text a caller names under a key they take that changes the stored text
	 * (`changed_narratives()`) and is longer than `MAX_TEXT` as the manager typed it
	 * (`WPCPM_Typed_Text::typed_length()`), with how far over it is. The editor asks it before a
	 * save, so its notice can name the box; `save_narratives()` asks it too, and stores nothing when
	 * it finds one.
	 *
	 * @param WP_Post $post  The report.
	 * @param array   $texts Narrative key => text, as `save_narratives()` takes them.
	 * @return array `key` and `over` (how many characters as typed), or `array()` when none is over.
	 */
	public static function long_narrative( WP_Post $post, array $texts ) {
		$keys = self::narrative_keys( self::sections_of( $post ) );

		foreach ( self::changed_narratives( $keys, self::stored_narratives( (int) $post->ID ), $texts ) as $key => $text ) {
			$over = WPCPM_Typed_Text::typed_length( $text ) - self::MAX_TEXT;

			if ( $over > 0 ) {
				return array(
					'key'  => (string) $key,
					'over' => $over,
				);
			}
		}

		return array();
	}

	/**
	 * Whether a save of these texts would write anything: whether one text a caller names, under a
	 * key the report's sections take, does not read as the text stored under it
	 * (`changed_narratives()`). The editor asks it before a save, so a press that changed nothing says
	 * so rather than that the narratives were saved.
	 *
	 * @param WP_Post $post  The report.
	 * @param array   $texts Narrative key => text, as `save_narratives()` takes them.
	 * @return bool
	 */
	public static function changes_narratives( WP_Post $post, array $texts ) {
		$keys = self::narrative_keys( self::sections_of( $post ) );

		return ! empty( self::changed_narratives( $keys, self::stored_narratives( (int) $post->ID ), $texts ) );
	}

	/**
	 * A report's narratives as the meta holds them, every key it keeps.
	 *
	 * @param int $post_id The report.
	 * @return array Narrative key => text.
	 */
	private static function stored_narratives( $post_id ) {
		$stored = get_post_meta( (int) $post_id, self::META_SECTIONS, true );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * The texts a save would write: each one a caller names under a key the report's sections take,
	 * cleaned (`clean_text()`), that does not read as the text stored under it. Posted back unedited,
	 * a box drawn as typed can come back in other bytes, a ">" held as `&gt;` or a break as CR LF,
	 * and reads the same (`WPCPM_Typed_Text::same_lines()`), so it is not among them. The stored text
	 * is read trimmed, as the cleaner trims every post-back: a narrative an earlier version cut at
	 * `MAX_TEXT` stored characters can end in a space or a line break, which its box posts back and
	 * the cleaner takes off.
	 *
	 * @param string[] $keys   The keys the report's sections take, in order.
	 * @param array    $stored The narratives as stored.
	 * @param array    $texts  Narrative key => text, as a caller sent them.
	 * @return array<string, string> Narrative key => the cleaned text, in the order of `$keys`.
	 */
	private static function changed_narratives( array $keys, array $stored, array $texts ) {
		$changed = array();

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $texts ) ) {
				continue;
			}

			$text = self::clean_text( $texts[ $key ] );
			$was  = isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';

			if ( ! WPCPM_Typed_Text::same_lines( $text, trim( $was ) ) ) {
				$changed[ $key ] = $text;
			}
		}

		return $changed;
	}

	/**
	 * Mark a draft final: the state, who and when, and the audit row.
	 *
	 * @param WP_Post $post    The report.
	 * @param int     $user_id The manager marking it.
	 * @return bool False for a report already final, or a post that is not a report.
	 */
	public static function finalize( WP_Post $post, $user_id ) {
		if ( ! self::is_report( $post ) || self::STATE_FINAL === self::state( $post ) ) {
			return false;
		}

		$actor = max( 0, (int) $user_id );

		self::set_state( $post, self::STATE_FINAL );

		update_post_meta(
			(int) $post->ID,
			self::META_FINAL,
			array(
				'at' => time(),
				'by' => $actor,
			)
		);

		$until = self::until_of( $post );

		self::audit(
			self::LOG_FINAL,
			self::sponsor_of( $post ),
			(int) $post->ID,
			$actor,
			self::ground_for( $actor ),
			/* translators: %s: the last day of the sponsor's term, in the site's date format. */
			sprintf( __( 'A sponsor report was marked final for the term ending %s.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $until ) ),
			array( 'until' => $until )
		);

		return true;
	}

	/**
	 * Make a final report a draft again. The stamp goes with the state, so a reopened report never
	 * says who marked final a version that may no longer be the one there.
	 *
	 * @param WP_Post $post    The report.
	 * @param int     $user_id The manager reopening it.
	 * @return bool False for a draft, or a post that is not a report.
	 */
	public static function reopen( WP_Post $post, $user_id ) {
		if ( ! self::is_report( $post ) || self::STATE_FINAL !== self::state( $post ) ) {
			return false;
		}

		$actor = max( 0, (int) $user_id );

		self::set_state( $post, self::STATE_DRAFT );
		delete_post_meta( (int) $post->ID, self::META_FINAL );

		$until = self::until_of( $post );

		self::audit(
			self::LOG_REOPENED,
			self::sponsor_of( $post ),
			(int) $post->ID,
			$actor,
			self::ground_for( $actor ),
			/* translators: %s: the last day of the sponsor's term, in the site's date format. */
			sprintf( __( 'A sponsor report was reopened for the term ending %s.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $until ) ),
			array( 'until' => $until )
		);

		return true;
	}

	/*
	 * --------------------------------------------------------------------
	 * What the daily run and the Administrator Dashboard read
	 * --------------------------------------------------------------------
	 */

	/**
	 * The daily run's draft: a sponsor's report for a term that ended, once.
	 *
	 * The terms `owed_terms()` names, the one a renewal moved off first: an Approved sponsor's term
	 * whose last day is before today, on a tier that carries a report. The row's own term is skipped
	 * once `report_drafted_for` names its day. A term that already has a report, a manager's draft
	 * made first, is not drafted again; the row is stamped as the run's own draft would be, so the
	 * run stops asking, and a draft thrown away later is not drafted again whoever made it. A draft
	 * that could not be stored, one refused as `busy` while a manager drafts for the sponsor among
	 * them, stamps nothing, so the next run tries again. Once a report for an owed term exists, the
	 * owed term is cleared. One draft a run at most.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, as the run read it.
	 * @param string $today  `Y-m-d`; '' for today.
	 * @return bool Whether a draft was stored.
	 */
	public static function maybe_draft( $record, array $row, $today = '' ) {
		$record = trim( (string) $record );

		foreach ( self::owed_terms( $row, $today ) as $term ) {
			if ( ! $term['owed'] && isset( $row['report_drafted_for'] ) && (string) $row['report_drafted_for'] === $term['until'] ) {
				continue;
			}

			if ( self::find( $record, $term['until'] ) instanceof WP_Post ) {
				self::mark_drafted( $record, $row, $term['until'] );
				continue;
			}

			if ( is_wp_error( self::generate( $record, $term['until'], self::ORIGIN_SYSTEM, $today ) ) ) {
				return false;
			}

			self::mark_drafted( $record, $row, $term['until'] );

			return true;
		}

		return false;
	}

	/**
	 * What a save of the Sponsors screen's Edit form leaves owed: the term the save moves the day
	 * off, when its tier carries a report and no report exists for its last day.
	 *
	 * **The first owed term wins.** While the row already holds an owed term with no report, a second
	 * save inside that window, a corrected day or a second renewal, keeps the first: that is the term
	 * still owed its report, and the day the second save moves off is the renewal's own. An owed term
	 * that has its report gives way.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $stored The row before the save.
	 * @param array  $saved  The row the save writes.
	 * @return array `tier`, `until` and `funded_line`, the term to record as `report_owed`, its line as
	 *               the row held it before the save, which may be rewriting the line as well as the
	 *               day; `array()` when the save owes nothing new, which leaves the row's own owed
	 *               term as it is.
	 */
	public static function owed_after_save( $record, array $stored, array $saved ) {
		$record = trim( (string) $record );
		$until  = isset( $stored['tier_until'] ) ? (string) $stored['tier_until'] : '';
		$moved  = isset( $saved['tier_until'] ) ? (string) $saved['tier_until'] : '';

		if ( $moved === $until || ! self::is_period_end( $until ) ) {
			return array();
		}

		$tier = WPCPM_Sponsor_Tiers::effective( $stored, $until );

		if ( ! WPCPM_Sponsor_Tiers::includes( $tier, 'report_program' ) ) {
			return array();
		}

		$owed = self::owed_of( $stored );

		if ( ! empty( $owed ) && ! ( self::find( $record, $owed['until'] ) instanceof WP_Post ) ) {
			return array();
		}

		if ( self::find( $record, $until ) instanceof WP_Post ) {
			return array();
		}

		return array(
			'tier'        => $tier,
			'until'       => $until,
			'funded_line' => isset( $stored['funded_line'] ) ? trim( (string) $stored['funded_line'] ) : '',
		);
	}

	/**
	 * The terms a report is owed for: Approved sponsors' terms that ended before today on a tier
	 * that carries a report, a term a renewal moved off among them, with no report for that end.
	 * The daily run's own rule of a term that has ended (`owed_terms()`), so the run and the
	 * Administrator Dashboard cannot disagree about it; the run alone also reads its stamp, which is
	 * why a draft thrown away after the run stamped its term is listed here, for a manager to draft
	 * again, and is not drafted again by the run. Every report is read once, whatever the number of
	 * terms.
	 *
	 * @param string $today `Y-m-d`; '' for today.
	 * @return array[] One a term, each `sponsor` (the key), `name`, `until` and `tier` (the tier held
	 *                 that day), so a sponsor owed two terms is listed twice; the oldest end first,
	 *                 then by name.
	 */
	public static function due( $today = '' ) {
		$reported = array();

		foreach ( self::all_reports() as $post ) {
			$reported[ self::sponsor_of( $post ) . '|' . self::until_of( $post ) ] = true;
		}

		$due = array();

		foreach ( WPCPM_Sponsors_Index::approved() as $record => $row ) {
			foreach ( self::owed_terms( $row, $today ) as $term ) {
				if ( isset( $reported[ $record . '|' . $term['until'] ] ) ) {
					continue;
				}

				$due[] = array(
					'sponsor' => (string) $record,
					'name'    => self::name_of( $row ),
					'until'   => $term['until'],
					'tier'    => $term['tier'],
				);
			}
		}

		usort(
			$due,
			static function ( $a, $b ) {
				$order = strcmp( $a['until'], $b['until'] );

				if ( 0 === $order ) {
					$order = strcasecmp( $a['name'], $b['name'] );
				}

				return 0 !== $order ? $order : strcmp( $a['sponsor'], $b['sponsor'] );
			}
		);

		return $due;
	}

	/**
	 * Every draft, the oldest first: what a manager has left to finish.
	 *
	 * @return WP_Post[]
	 */
	public static function queue() {
		return array_values(
			array_filter(
				self::all_reports(),
				static function ( $post ) {
					return self::STATE_DRAFT === self::state( $post );
				}
			)
		);
	}

	/**
	 * Every final report marked final at or after a moment, the earliest marked first.
	 *
	 * @param int $timestamp Unix time; 0 for every final report.
	 * @return WP_Post[]
	 */
	public static function final_since( $timestamp ) {
		$after = (int) $timestamp;
		$out   = array();

		foreach ( self::all_reports() as $post ) {
			if ( self::STATE_FINAL !== self::state( $post ) ) {
				continue;
			}

			$stamp = self::final_at( $post );

			if ( $after > 0 && ( empty( $stamp['at'] ) || $stamp['at'] < $after ) ) {
				continue;
			}

			$out[] = $post;
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$at_a  = self::final_at( $a );
				$at_b  = self::final_at( $b );
				$order = ( empty( $at_a['at'] ) ? 0 : $at_a['at'] ) <=> ( empty( $at_b['at'] ) ? 0 : $at_b['at'] );

				return 0 !== $order ? $order : (int) $a->ID - (int) $b->ID;
			}
		);

		return $out;
	}

	/**
	 * Remove every report on uninstall, and forget the file attached to each; then every lock.
	 *
	 * Every status named, the trash included, rather than `any`, which leaves the trash out: a report
	 * somebody trashed by hand would otherwise outlive the plugin with a company's figures in it. A
	 * report's revisions go with it, as WordPress deletes them with their post. The locks are found
	 * by their prefix, since a lock is named after a sponsor the store may already have forgotten,
	 * and one a request died holding is still there. Nothing else is touched: the rows' stamps go
	 * with the sponsors store, and the private store's key is the private files' to keep.
	 */
	public static function delete_all() {
		global $wpdb;

		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => WPCPM_Sponsors_Index::POST_STATUSES,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $found as $post_id ) {
			$file = get_post_meta( (int) $post_id, self::META_FILE, true );

			if ( is_array( $file ) && ! empty( $file['path'] ) ) {
				WPCPM_Private_Files::forget( (string) $file['path'] );
			}

			wp_delete_post( (int) $post_id, true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rows are addressable only by exact name; this runs from uninstall.
		$locks = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LOCK_PREFIX ) . '%' ) );

		foreach ( (array) $locks as $name ) {
			delete_option( (string) $name );
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * Internals
	 * --------------------------------------------------------------------
	 */

	/**
	 * What a draft does once it holds the sponsor's lock: the day, the look for the report, the
	 * tier, the post and its keys, its tracks' words among them. Refused with nothing written, as
	 * `generate()` says.
	 *
	 * Until the sponsor and the day, the first keys written after the insert, are in, the post is
	 * there and no look finds it, so the lock is held until they are; and on to the last key, so a
	 * manager sent to open the report by a draft of their own finds its figures and its tracks'
	 * words written.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, read under the lock.
	 * @param string $until  The period's last day, as the caller gave it.
	 * @param int    $actor  The account drafting, the post's author; 0 for the daily run.
	 * @param string $today  `Y-m-d`, the day the figures are read; '' for today.
	 * @return int|WP_Error The report's post ID.
	 */
	private static function create( $record, array $row, $until, $actor, $today ) {
		if ( ! self::is_period_end( $until ) ) {
			return new WP_Error( 'wpcpm_sreport_date', __( 'That is not a day a report can end on.', 'wpcredits-program-manager' ) );
		}

		if ( self::find( $record, $until, true ) instanceof WP_Post ) {
			return new WP_Error( 'wpcpm_sreport_exists', __( 'A report for that term exists; open it.', 'wpcredits-program-manager' ) );
		}

		$term = self::term_row( $row, $until );
		$tier = WPCPM_Sponsor_Tiers::effective( $term, $until );

		if ( ! WPCPM_Sponsor_Tiers::includes( $tier, 'report_program' ) ) {
			return new WP_Error( 'wpcpm_sreport_tier', __( 'The sponsor\'s tier on that day carries no annual report.', 'wpcredits-program-manager' ) );
		}

		// The post, the snapshot and the narratives slashed, because WordPress unslashes a post and
		// its meta on the way in: a company named with a backslash would otherwise lose it, and so
		// would a track's words. The key, the day and the state hold none.
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'post_author' => $actor,
					'post_title'  => self::title_for( $row, self::period( $until ) ),
					// WordPress lowercases a slug, so the key's capitals do not survive in it; nothing
					// finds a report by its slug, only by META_SPONSOR and META_UNTIL.
					'post_name'   => sanitize_title( 'sreport-' . $record . '-' . $until ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! $post_id ) {
			return new WP_Error( 'wpcpm_sreport_not_created', __( 'The report could not be created.', 'wpcredits-program-manager' ) );
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SPONSOR, $record );
		update_post_meta( $post_id, self::META_UNTIL, $until );

		$snapshot = self::build( $record, $term, $until, $tier, $today );

		update_post_meta( $post_id, self::META_DATA, wp_slash( $snapshot ) );
		update_post_meta( $post_id, self::META_SECTIONS, wp_slash( self::pull_narratives( $record, $snapshot['tracks'], array() ) ) );
		update_post_meta( $post_id, self::META_STATE, self::STATE_DRAFT );

		return $post_id;
	}

	/**
	 * The words a report's track blocks start with: for each track the report has a block for, the
	 * partners' narrative the track was last published with, under the block's own key, `track_` and
	 * the track's key, the key `narrative_keys()` names.
	 *
	 * **The published copy, never the saved draft.** A track's students see what was published, and
	 * its partners read the same: an edit reaches the reports drafted after it is published. The copy
	 * is read from the track's post (`WPCPM_Track_Store::published()`), once a draft, since the
	 * compiled index the rest of the site reads leaves the text out. The words are kept as
	 * `save_narratives()` keeps a manager's (`clean_text()`), whole: the track's own rule holds them to
	 * `WPCPM_Track_Definition::MAX_NARRATIVE` characters, the report's `MAX_TEXT`, and the cleaner
	 * only takes characters away from what it counts as typed. Only a key that holds no words is
	 * filled, so nothing a report holds is written over: `create()` hands over a new report's
	 * narratives, which are none, and `regenerate()` pulls nothing. A track published with no text
	 * leaves its key unwritten.
	 *
	 * @param string  $record   Sponsor key.
	 * @param array[] $tracks   The snapshot's tracks, each `key`: the tracks the report has a block for.
	 * @param array   $sections The report's narratives as they stand, narrative key => text.
	 * @return array The narratives, with each track's words under a key that held none.
	 */
	private static function pull_narratives( $record, array $tracks, array $sections ) {
		$posts = array();

		foreach ( WPCPM_Tracks::partner_tracks( $record ) as $track ) {
			$posts[ (string) $track['key'] ] = (int) $track['post'];
		}

		foreach ( $tracks as $track ) {
			$key  = is_array( $track ) ? self::text( $track, 'key' ) : '';
			$slot = self::SECTION_TRACK . '_' . $key;

			if ( '' === $key || ! isset( $posts[ $key ] ) || '' !== self::text( $sections, $slot ) ) {
				continue;
			}

			$words = self::clean_text( WPCPM_Track_Definition::narrative( WPCPM_Track_Store::published( $posts[ $key ] ) ) );

			if ( '' !== $words ) {
				$sections[ $slot ] = $words;
			}
		}

		return $sections;
	}

	/**
	 * Take a sponsor's lock.
	 *
	 * The events store's primitive (`WPCPM_Sponsor_Event::lock()`), and like it no strict mutex, for
	 * the reasons its docblock gives: the held value is read from the options table itself, since on
	 * a site with a persistent object cache a request's own copy of the options can say the lock is
	 * free while another request holds it, the daily run's one request most of all; a lock that is
	 * held is refused; one older than `LOCK_TIMEOUT` belonged to a request that died holding it and is
	 * deleted; and `add_option()` then takes it, the moment it was taken as its value. The windows it
	 * leaves are a few milliseconds wide; without it, the window ran from the look for the report to
	 * the keys that make one findable, across the insert and whatever is hooked on it.
	 *
	 * @param string $record Sponsor key.
	 * @return bool Whether the lock was taken.
	 */
	private static function lock( $record ) {
		global $wpdb;

		$name = self::LOCK_PREFIX . $record;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The row itself, past this request's copy of the options: see above.
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		if ( null !== $held ) {
			if ( ( time() - (int) $held ) <= self::LOCK_TIMEOUT ) {
				return false;
			}

			delete_option( $name );
		}

		return (bool) add_option( $name, time(), '', false );
	}

	/**
	 * Give a sponsor's lock back.
	 *
	 * @param string $record Sponsor key.
	 */
	private static function unlock( $record ) {
		delete_option( self::LOCK_PREFIX . $record );
	}

	/**
	 * The refusal of a sponsor the store does not hold, a value that is no key among them.
	 *
	 * @return WP_Error
	 */
	private static function unknown_sponsor() {
		return new WP_Error( 'wpcpm_sreport_sponsor', __( 'That sponsor could not be identified.', 'wpcredits-program-manager' ) );
	}

	/**
	 * The snapshot of one report: the figures for its period, read now.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $until  The period's last day.
	 * @param string $tier   The tier held on that day.
	 * @param string $today  The day the figures are read, or '' for today.
	 * @return array The shape `snapshot()` answers.
	 */
	private static function build( $record, array $row, $until, $tier, $today ) {
		$period = self::period( $until );
		$tracks = array();

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'report_track' ) ) {
			foreach ( WPCPM_Tracks::partner_tracks( $record ) as $track ) {
				$key = is_array( $track ) ? self::text( $track, 'key' ) : '';

				if ( '' !== $key ) {
					$tracks[] = array_merge( array( 'key' => $key ), WPCPM_Sponsor_Report_Numbers::track( $key, $period['from'], $period['until'] ) );
				}
			}
		}

		return array(
			'v'           => self::VERSION,
			'generated'   => time(),
			'as_of'       => '' !== (string) $today && WPCPM_Sponsor_Tiers::is_date( $today ) ? (string) $today : WPCPM_Sponsor_Tiers::today(),
			'sponsor'     => $record,
			'from'        => $period['from'],
			'until'       => $period['until'],
			'program'     => WPCPM_Sponsor_Report_Numbers::program( $period['from'], $period['until'] ),
			'sponsorship' => WPCPM_Sponsor_Report_Numbers::sponsorship( $record, $row, $period['from'], $period['until'] ),
			'tracks'      => $tracks,
		);
	}

	/**
	 * The terms a sponsor is owed a report for today, by the daily run's rule: an Approved sponsor's
	 * terms whose last day is before today, on a tier that carries a report. The term a renewal moved
	 * off comes first, with the tier it held; then the row's own term, with the tier held on its last
	 * day.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $today `Y-m-d`; '' for today.
	 * @return array[] Each `until`, `tier` and `owed` (whether it is the term a renewal moved off).
	 */
	private static function owed_terms( array $row, $today ) {
		$terms  = array();
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		$today  = '' !== (string) $today ? (string) $today : WPCPM_Sponsor_Tiers::today();

		if ( WPCPM_Sponsors_Index::STATUS_APPROVED !== $status ) {
			return $terms;
		}

		$owed = self::owed_of( $row );

		if ( ! empty( $owed ) && strcmp( $owed['until'], $today ) < 0 && WPCPM_Sponsor_Tiers::includes( $owed['tier'], 'report_program' ) ) {
			$terms[] = array(
				'until' => $owed['until'],
				'tier'  => $owed['tier'],
				'owed'  => true,
			);
		}

		$until = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';

		if ( ! self::is_period_end( $until ) || strcmp( $until, $today ) >= 0 || ( ! empty( $owed ) && $owed['until'] === $until ) ) {
			return $terms;
		}

		$tier = WPCPM_Sponsor_Tiers::effective( $row, $until );

		if ( WPCPM_Sponsor_Tiers::includes( $tier, 'report_program' ) ) {
			$terms[] = array(
				'until' => $until,
				'tier'  => $tier,
				'owed'  => false,
			);
		}

		return $terms;
	}

	/**
	 * The row as it stood for the term ending on a day: for the term a renewal moved off, the tier
	 * that term held, its last day and its funded line; for any other day, the row itself.
	 *
	 * @param array  $row   The sponsor's row.
	 * @param string $until The term's last day.
	 * @return array A row for `WPCPM_Sponsor_Tiers::effective()` and the numbers to read.
	 */
	private static function term_row( array $row, $until ) {
		$owed = self::owed_of( $row );

		if ( empty( $owed ) || $owed['until'] !== $until ) {
			return $row;
		}

		return array_merge(
			$row,
			array(
				'tier'        => $owed['tier'],
				'tier_until'  => $owed['until'],
				'funded_line' => $owed['funded_line'],
			)
		);
	}

	/**
	 * Clear the owed term once a report for its last day exists.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row.
	 * @param string $until  The day a report now exists for.
	 */
	private static function clear_owed( $record, array $row, $until ) {
		$owed = self::owed_of( $row );

		if ( ! empty( $owed ) && $owed['until'] === $until ) {
			WPCPM_Sponsors_Index::patch( $record, array( 'report_owed' => array() ) );
		}
	}

	/**
	 * Stamp a term the run found a report for, drafted now or before: `report_drafted_for` names its
	 * day, and an owed term with that day is cleared.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $row    The sponsor's row, as the run read it.
	 * @param string $until  The term's last day.
	 */
	private static function mark_drafted( $record, array $row, $until ) {
		$fields = array( 'report_drafted_for' => $until );
		$owed   = self::owed_of( $row );

		if ( ! empty( $owed ) && $owed['until'] === $until ) {
			$fields['report_owed'] = array();
		}

		WPCPM_Sponsors_Index::patch( $record, $fields );
	}

	/**
	 * Every report, the oldest first.
	 *
	 * @return WP_Post[]
	 */
	private static function all_reports() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$posts = array_values(
			array_filter(
				(array) $posts,
				static function ( $post ) {
					return self::is_report( $post );
				}
			)
		);

		usort(
			$posts,
			static function ( $a, $b ) {
				return (int) $a->ID - (int) $b->ID;
			}
		);

		return $posts;
	}

	/**
	 * Write a state. `finalize()` and `reopen()` are the only callers: each move carries its stamp
	 * and its audit row.
	 *
	 * @param WP_Post $post  The report.
	 * @param string  $state `draft` or `final`.
	 */
	private static function set_state( WP_Post $post, $state ) {
		update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_FINAL === $state ? self::STATE_FINAL : self::STATE_DRAFT );
	}

	/**
	 * One row on the sponsors module's audit trail, about one report.
	 *
	 * @param string $kind    One of the LOG_* kinds.
	 * @param string $record  Sponsor key.
	 * @param int    $post_id The report, the row's subject.
	 * @param int    $actor   The account, or 0 for the daily run.
	 * @param string $ground  `manager` or `system`.
	 * @param string $message One sentence.
	 * @param array  $data    Facts, never prose.
	 */
	private static function audit( $kind, $record, $post_id, $actor, $ground, $message, array $data ) {
		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => $kind,
				'sponsor'  => $record,
				'subject'  => (string) $post_id,
				'actor'    => (int) $actor,
				'ground'   => $ground,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $message,
				'data'     => $data,
			)
		);
	}

	/**
	 * The ground an account acted on: a manager's, or the system's for no account at all.
	 *
	 * @param int $actor The account, or 0.
	 * @return string
	 */
	private static function ground_for( $actor ) {
		return (int) $actor > 0 ? WPCPM_Institution_Audit::GROUND_MANAGER : WPCPM_Institution_Audit::GROUND_SYSTEM;
	}

	/**
	 * Whether a value is a report post.
	 *
	 * @param mixed $post Anything.
	 * @return bool
	 */
	private static function is_report( $post ) {
		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * One section's entry, as `sections_for()` answers it.
	 *
	 * @param string $title     The heading.
	 * @param bool   $generated Whether it draws figures from the snapshot.
	 * @param bool   $narrative Whether a manager writes under it.
	 * @param array  $per_track Track key => name, for a section drawn once per track.
	 * @return array
	 */
	private static function section( $title, $generated, $narrative, array $per_track = array() ) {
		return array(
			'title'     => $title,
			'generated' => (bool) $generated,
			'narrative' => (bool) $narrative,
			'per_track' => $per_track,
		);
	}

	/**
	 * A sponsor's name as a title and the due list print it: the row's, trimmed, or the key when it
	 * has none.
	 *
	 * @param array $row The sponsor's row.
	 * @return string
	 */
	private static function name_of( array $row ) {
		$name = self::text( $row, 'name' );

		return '' !== $name ? $name : self::text( $row, 'record_id' );
	}

	/**
	 * A day's month and year, in the site's language.
	 *
	 * At noon in UTC and written in UTC: a site far east of UTC reads noon on the last day of a
	 * month as the first of the next, and the title would end a month late. Public for the Sponsor
	 * Dashboard's Reports card, whose row names the latest report's term in the months its title
	 * names.
	 *
	 * @param string $day `Y-m-d`.
	 * @return string '' for a value that is not a day.
	 */
	public static function month_of( $day ) {
		if ( ! self::is_period_end( $day ) ) {
			return '';
		}

		return (string) wp_date( 'F Y', (int) strtotime( $day . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A narrative as it is kept: plain text as the cleaner leaves it, tags stripped, and each line
	 * break one `\n`, as a track's partners' narrative keeps its breaks
	 * (`WPCPM_Track_Definition::normalize()`).
	 *
	 * Nothing is cut: the length is the save's to judge, counted as the manager typed it
	 * (`long_narrative()`). The browser posts each break as two characters, which
	 * `sanitize_textarea_field()` leaves as posted, and the cleaner writes a "<" that opens no tag,
	 * with the quote marks and ampersands after it, as entities, so a text the box let through at
	 * `MAX_TEXT` is stored longer than that. Cut there, it would lose its end at the first Save of
	 * any box on the report.
	 *
	 * @param mixed $text What a caller sent.
	 * @return string
	 */
	private static function clean_text( $text ) {
		return str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( is_scalar( $text ) ? (string) $text : '' ) );
	}

	/**
	 * A trimmed string out of an array, or ''.
	 *
	 * @param array  $values Anything keyed.
	 * @param string $key    The key.
	 * @return string
	 */
	private static function text( array $values, $key ) {
		return isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
	}

	/**
	 * Figures out of a stored array, every one a list names present and typed, and no other: a
	 * track block is shaped with the track's list, so no `finished` is put back into it.
	 *
	 * @param array    $given As stored.
	 * @param string[] $keys  The figures, `WPCPM_Sponsor_Report_Numbers::COUNTS` or `TRACK_COUNTS`.
	 * @return array The figures, in the list's order.
	 */
	private static function shape_counts( array $given, array $keys = WPCPM_Sponsor_Report_Numbers::COUNTS ) {
		$out = array();

		foreach ( $keys as $key ) {
			$value       = isset( $given[ $key ] ) && is_scalar( $given[ $key ] ) ? $given[ $key ] : 0;
			$out[ $key ] = 'hours' === $key ? (float) $value : (int) $value;
		}

		return $out;
	}

	/**
	 * The sponsorship out of a stored array, every key present and typed.
	 *
	 * Each event held keeps its title, its start, its length and its count, and nothing else a stored
	 * entry might hold. A snapshot drafted before the report listed the events held has no such key
	 * and reads as none held, at the same version: a new version would make every report drafted
	 * before it unreadable until it was generated again, and a final one cannot be generated again
	 * without being reopened, whereas read as none held it prints the page it printed before.
	 *
	 * @param array $given As stored.
	 * @return array `WPCPM_Sponsor_Report_Numbers::sponsorship()`'s shape.
	 */
	private static function shape_sponsorship( array $given ) {
		$mentors = array();

		foreach ( isset( $given['mentors'] ) && is_array( $given['mentors'] ) ? $given['mentors'] : array() as $mentor ) {
			if ( is_array( $mentor ) ) {
				$mentors[] = array(
					'name'     => self::text( $mentor, 'name' ),
					'profile'  => self::text( $mentor, 'profile' ),
					'students' => isset( $mentor['students'] ) ? (int) $mentor['students'] : 0,
				);
			}
		}

		$offers = array();

		foreach ( isset( $given['offers'] ) && is_array( $given['offers'] ) ? $given['offers'] : array() as $offer ) {
			if ( is_array( $offer ) ) {
				$offers[] = array(
					'title'  => self::text( $offer, 'title' ),
					'claims' => isset( $offer['claims'] ) ? (int) $offer['claims'] : 0,
					'series' => self::shape_series( isset( $offer['series'] ) && is_array( $offer['series'] ) ? $offer['series'] : array() ),
				);
			}
		}

		$events = array();

		foreach ( isset( $given['events'] ) && is_array( $given['events'] ) ? $given['events'] : array() as $event ) {
			if ( is_scalar( $event ) ) {
				$events[] = (string) $event;
			}
		}

		$held = array();

		foreach ( isset( $given['events_held'] ) && is_array( $given['events_held'] ) ? $given['events_held'] : array() as $event ) {
			if ( is_array( $event ) ) {
				$held[] = array(
					'title'   => self::text( $event, 'title' ),
					'start'   => isset( $event['start'] ) ? (int) $event['start'] : 0,
					'minutes' => isset( $event['minutes'] ) ? (int) $event['minutes'] : 0,
					'joined'  => isset( $event['joined'] ) ? (int) $event['joined'] : 0,
				);
			}
		}

		return array(
			'tier'        => self::text( $given, 'tier' ),
			'tier_label'  => self::text( $given, 'tier_label' ),
			'from'        => self::text( $given, 'from' ),
			'until'       => self::text( $given, 'until' ),
			'funded_line' => self::text( $given, 'funded_line' ),
			'mentors'     => $mentors,
			'months'      => self::shape_series( isset( $given['months'] ) && is_array( $given['months'] ) ? $given['months'] : array() ),
			'offers'      => $offers,
			'posts'       => isset( $given['posts'] ) ? (int) $given['posts'] : 0,
			'events'      => $events,
			'events_held' => $held,
		);
	}

	/**
	 * A `Y-m` => count series out of a stored array.
	 *
	 * @param array $given As stored.
	 * @return array<string, int>
	 */
	private static function shape_series( array $given ) {
		$out = array();

		foreach ( $given as $month => $count ) {
			$out[ (string) $month ] = is_scalar( $count ) ? (int) $count : 0;
		}

		return $out;
	}
}
