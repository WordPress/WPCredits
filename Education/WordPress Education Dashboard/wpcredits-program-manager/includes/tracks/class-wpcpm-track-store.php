<?php
/**
 * Where the Track Builder keeps its tracks: one private post each.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Track definitions as `wpcpm_track` posts, and the compile step the live site runs on.
 *
 * **A draft touches nothing; publishing is the one act that changes the live site** (the
 * design's decision 1.2). The post is where people edit: private, reachable through no generic
 * screen, with the definition in revisioned meta so every saved change is kept. Publishing
 * copies a definition into `META_PUBLISHED`: the one the preflight judged, when
 * `WPCPM_Track_Publish::run()` hands it over, so a draft saved while the run was creating columns
 * stays a change not yet published (PUBLISH-LEARN-1); the stored draft only when nothing is
 * handed over. `compile()`, the only writer of what the site runs on (`WPCPM_Tracks`), reads that
 * copy and never the saved definition: an edit saved to a published track reaches no student
 * until it is published, whatever else is compiled in the meantime (the design's decision 3.2
 * and its open item 5).
 *
 * `compile()` checks every copy it compiles as well (open item 6), because a compile rebuilds
 * every published track, including one whose surroundings changed after it was published.
 */
final class WPCPM_Track_Store {

	/** Eleven characters; `register_post_type()` refuses a name over twenty. */
	const POST_TYPE = 'wpcpm_track';

	/** The definition, as `WPCPM_Track_Definition::encode()` writes it. Revisioned. */
	const META_DEFINITION = '_wpcpm_track_definition';

	/**
	 * `builtin` for a migrated track its PHP still runs; absent for every other track.
	 *
	 * Written by the migration of phase T2 and cleared by the switch; carried into the compiled
	 * row, where `WPCPM_Tracks` leaves such a track to its PHP, and read by `save()`, which keeps
	 * such a track read-only until it switches (the design's section 6).
	 */
	const META_SOURCE = '_wpcpm_track_source';

	/** `1` once somebody has ticked the reports automation item of the publish checklist (T2). */
	const META_AUTOMATION = '_wpcpm_track_automation';

	/**
	 * The definition as it was last published: what `compile()` reads.
	 *
	 * Kept apart from the revisioned definition, which is what people edit, so saving a change to
	 * a published track changes nothing students see until the change is published. Not the ID of
	 * a revision either: a site may limit how many revisions it keeps, and this copy must outlive
	 * them all. It stays when a track is unpublished, as the record of what was live.
	 */
	const META_PUBLISHED = '_wpcpm_track_published';

	/** What happened to the track and who did it, oldest first: `log()` writes it. */
	const META_LOG = '_wpcpm_track_log';

	/**
	 * The published tracks the last compile left out: post ID => the codes of what was wrong.
	 *
	 * For the track list to show. Not autoloaded, because only the Track Builder reads it.
	 */
	const OPT_SKIPPED = 'wpcpm_tracks_skipped';

	/**
	 * The saved definition's fingerprint when a built-in track switched to its definition.
	 *
	 * The way back is open only while the definition is unchanged since the switch (the design's
	 * decision 3.5): going back after an edit would put the PHP in front of students and quietly
	 * drop what the edit published. The fingerprint alone cannot see an edit that was published
	 * and then saved back to its old text without being published, so the way back also needs a
	 * published track's copy to be the PHP's, which `equivalence()` answers (the final review of
	 * T2a).
	 */
	const META_SWITCHED = '_wpcpm_track_switched';

	/**
	 * The track a copy was made from.
	 *
	 * Post meta rather than a property, because `TRACK_PROPERTIES` does not know it and
	 * `validate()` refuses a property it does not know (the T2a handoff).
	 */
	const META_DUPLICATED_FROM = '_wpcpm_track_duplicated_from';

	/** The seed version this site's Track Builder started from, set once. Autoloaded: read every request. */
	const OPT_SEEDED = 'wpcpm_tracks_seeded';

	/** The version of the seeds in `includes/tracks/seeds/`, which `OPT_SEEDED` records. */
	const SEED_VERSION = 1;

	/** The four built-in tracks' keys, in the program's order: one seed file each. */
	const BUILTIN_KEYS = array( '150h', '50h', 'dev', 'design' );

	/**
	 * Register the type and its meta.
	 *
	 * The type before the meta, and that order is load-bearing: WordPress refuses
	 * `revisions_enabled`, with a notice, for a type that does not support revisions yet, and
	 * the definition would then be the one thing a revision does not keep. The seeds come after
	 * both, at 20, once (`maybe_seed()`).
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'init', array( __CLASS__, 'maybe_seed' ), 20 );
	}

	/**
	 * The private post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Tracks', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Track', 'wpcredits-program-manager' ),
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
				// Ignored below WordPress 6.8, where the oEmbed filter refuses the type instead,
				// and honored from it (SURFACES-2).
				'embeddable'          => false,
				'supports'            => array( 'title', 'revisions' ),
				// A capability type nobody is granted, so no role reaches a track through any
				// generic post screen; the Track Builder's own handlers are the one way in.
				'capability_type'     => array( 'wpcpm_track', 'wpcpm_tracks' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The definition's meta, revisioned.
	 *
	 * `revisions_enabled` landed in WordPress 6.4, inside this plugin's 6.5 floor. Not in REST
	 * and not editable through the meta API: `save()` is the one way in, like the semester
	 * report's narratives.
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_DEFINITION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'revisions_enabled' => true,
				'auth_callback'     => '__return_false',
			)
		);
	}

	/**
	 * Create a track, as a draft.
	 *
	 * The definition goes in through `save()`, so the first revision is taken at once and the
	 * history begins with the track as it was created.
	 *
	 * @param array $definition Track definition.
	 * @return int|WP_Error The post ID, or whatever WordPress refused with.
	 */
	public static function create( array $definition ) {
		if ( '' === WPCPM_Track_Definition::encode( WPCPM_Track_Definition::normalize( $definition ) ) ) {
			return self::unencodable();
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return self::save( (int) $post_id, $definition );
	}

	/**
	 * Copy a track: a new draft holding the definition it is given, and a note of where it came from.
	 *
	 * The copy is nobody's built-in track, whatever the original was: `create()` marks nothing, so
	 * its PHP runs the original and this one answers for itself (the design's decision 11).
	 *
	 * @param int   $from_id    The track being copied.
	 * @param array $definition The copy's definition, identity and all.
	 * @return int|WP_Error The new track's ID, or why it was not created.
	 */
	public static function duplicate( $from_id, array $definition ) {
		$created = self::create( $definition );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		update_post_meta( (int) $created, self::META_DUPLICATED_FROM, (int) $from_id );

		return $created;
	}

	/**
	 * Store a definition on its track.
	 *
	 * **A built-in track its PHP still runs is read-only** (the design's section 6): it can be
	 * duplicated, not edited, until it switches to its definition. Its equivalence with the PHP
	 * would otherwise mean nothing, and `locked()` reads the status a built-in draft names now, so
	 * a seed saved as another built-in track would lock the real one out, and a seed renamed away
	 * from its status would publish a track no page runs (the final review of T2a). `create()` is
	 * not held back: `seed()` marks a track built-in only after creating it.
	 *
	 * **The meta is written before the post, and that order is load-bearing** (the semester
	 * report's rule): WordPress saves a revision from inside `wp_update_post()`, copying the
	 * revisioned meta the post holds at that moment, so writing the post first would file every
	 * revision one save behind. The meta and the post array are both slashed, because WordPress
	 * unslashes each on the way in, and a backslash in a label would otherwise be lost.
	 *
	 * @param int   $post_id    The track.
	 * @param array $definition Track definition.
	 * @return int|WP_Error The post ID, or why it could not be stored.
	 */
	public static function save( $post_id, array $definition ) {
		$post_id = (int) $post_id;

		if ( null === self::track_post( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( 'builtin' === get_post_meta( $post_id, self::META_SOURCE, true ) ) {
			return new WP_Error( 'wpcpm_track_builtin', __( 'A built-in track runs from its PHP, so it cannot be edited until it switches to its definition. It can be duplicated.', 'wpcredits-program-manager' ) );
		}

		$definition = WPCPM_Track_Definition::normalize( $definition );
		$json       = WPCPM_Track_Definition::encode( $definition );

		if ( '' === $json ) {
			return self::unencodable();
		}

		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( $json ) );

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'         => $post_id,
					'post_title' => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				)
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : $post_id;
	}

	/**
	 * A track's definition.
	 *
	 * @param int $post_id The track.
	 * @return array|null The definition, or null when the post is not a readable track.
	 */
	public static function get( $post_id ) {
		if ( null === self::track_post( $post_id ) ) {
			return null;
		}

		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_DEFINITION, true ) );
	}

	/**
	 * The post, when it is a track.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	private static function track_post( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Why a definition was not stored: JSON cannot hold one of its values.
	 *
	 * `validate()` refuses the one such value a form could carry, an infinite bound, and this
	 * catches whatever else would reach the store: storing what `wp_json_encode()` gave up with
	 * would leave the track with no definition while the save reported success.
	 *
	 * @return WP_Error
	 */
	private static function unencodable() {
		return new WP_Error( 'wpcpm_track_unencodable', __( 'The track was not saved: one of its values cannot be stored.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Compile every published track into the options the live site runs on.
	 *
	 * From each track's published copy, never its saved definition (the design's decision 3.2),
	 * and only a copy every rule still accepts: a compile rebuilds every published track,
	 * including one whose surroundings changed since it was published (a sync column renamed, a
	 * status added to "Past students"), and the report form writes every column a compiled form
	 * names. Tracks are checked in the order they were made, each against the ones compiled before
	 * it, so of two that claim one status or key the first keeps it. What is left out is recorded
	 * in `OPT_SKIPPED` for the track list, and the rest compile regardless (open item 6).
	 *
	 * Each form is written before the index that names it, so a request never finds a track in
	 * the index without its form, and the form of a track that has left the index is deleted
	 * with it. The index and the skipped list are two options written one after the other, so a
	 * request that dies between the two writes leaves the list one compile behind.
	 *
	 * @return array The index written: status => row.
	 */
	public static function compile() {
		$previous = get_option( WPCPM_Tracks::OPT_TRACKS, array() );
		$rows     = array();
		$skipped  = array();
		$accepted = array();

		foreach ( self::published_posts() as $post ) {
			$definition = self::published( $post->ID );

			if ( ! is_array( $definition ) ) {
				$skipped[ $post->ID ] = array( 'unreadable' );
				continue;
			}

			$errors = WPCPM_Track_Definition::validate( $definition, self::context( $post->ID, $definition, $accepted, false ) );

			if ( array() !== $errors ) {
				$skipped[ $post->ID ] = array_values( array_unique( array_column( $errors, 'code' ) ) );
				continue;
			}

			$accepted[] = $definition;
			$source     = 'builtin' === get_post_meta( $post->ID, self::META_SOURCE, true ) ? 'builtin' : 'definition';
			$automation = '1' === (string) get_post_meta( $post->ID, self::META_AUTOMATION, true );

			update_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $definition['key'], WPCPM_Track_Definition::compile_fields( $definition ), false );

			$rows[ (string) $definition['status'] ] = WPCPM_Track_Definition::row( $definition, $post->ID, $source, $automation );
		}

		$keys = array_column( $rows, 'key' );

		foreach ( is_array( $previous ) ? $previous : array() as $row ) {
			if ( is_array( $row ) && isset( $row['key'] ) && ! in_array( $row['key'], $keys, true ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $row['key'] );
			}
		}

		update_option( WPCPM_Tracks::OPT_TRACKS, $rows, true );
		update_option( self::OPT_SKIPPED, $skipped, false );
		WPCPM_Tracks::flush();

		return $rows;
	}

	/**
	 * Publish a track: check its definition, copy it, and compile.
	 *
	 * The copy is what `compile()` reads from now on (the design's decision 3.2). The track is
	 * checked against every other track, drafts and trashed ones included (`check()`, TRACKS-1),
	 * so two can never be published claiming one status, key or name, and its status joins
	 * "Currently mentoring", because the students sync reads only the statuses listed there (7.2).
	 * What happens around this, the preflight, the checklist and the lock, belongs to
	 * `WPCPM_Track_Publish`, which the Track Builder screen calls; this is the part every path
	 * shares.
	 *
	 * **A definition handed over is the one checked and copied**, in place of the saved draft
	 * (PUBLISH-LEARN-1). The publish run hands over the draft its preflight judged and created
	 * columns for, because the saved draft may have changed while the columns were being made: a
	 * save made meanwhile stays a change not yet published, for the next publish to judge. With
	 * none, the draft is read as it stands.
	 *
	 * When WordPress refuses the status change, its error comes back and the track is left as it
	 * was: the copy it was published with before, or none, and nothing compiled, added or logged.
	 *
	 * @param int        $post_id    The track.
	 * @param int        $user_id    Who published it, for the log; 0 for the current user.
	 * @param array|null $definition The definition to put live, as the publish run judged it; null
	 *                               for the saved draft as it stands.
	 * @return int|WP_Error The post ID, or why the track was not published.
	 */
	public static function publish( $post_id, $user_id = 0, $definition = null ) {
		$post_id    = (int) $post_id;
		$post       = self::track_post( $post_id );
		$definition = ( null !== $post && is_array( $definition ) ) ? $definition : self::get( $post_id );

		if ( ! is_array( $definition ) ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		// The definition outlives the trash, so without this a trashed track published straight
		// out of it, and the track list would show a live track nobody could find (T2a's final
		// review, its M3).
		if ( $post instanceof WP_Post && 'trash' === $post->post_status ) {
			return new WP_Error( 'wpcpm_track_trashed', __( 'That track is in the trash. Restore it before publishing it.', 'wpcredits-program-manager' ) );
		}

		$errors = self::check( $post_id, $definition );

		if ( array() !== $errors ) {
			return new WP_Error( 'wpcpm_track_invalid', $errors[0]['message'], array( 'errors' => $errors ) );
		}

		$previous = (string) get_post_meta( $post_id, self::META_PUBLISHED, true );

		update_post_meta( $post_id, self::META_PUBLISHED, wp_slash( WPCPM_Track_Definition::encode( $definition ) ) );

		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			// Put back the copy it was published with before, or none: `published()` reads an
			// empty value as a track never published.
			if ( '' === $previous ) {
				delete_post_meta( $post_id, self::META_PUBLISHED );
			} else {
				update_post_meta( $post_id, self::META_PUBLISHED, wp_slash( $previous ) );
			}

			return $updated;
		}

		self::compile();

		// Those four statuses were the program's before the Track Builder existed and are edited
		// in Settings, so publishing a seed never puts back one a manager took out (the design's
		// decision 13). A track of somebody's own still gets its status added, which is what
		// makes its students sync.
		if ( 'builtin' !== self::source( $post_id ) ) {
			WPCPM_Settings::add_student_status( (string) $definition['status'] );
		}

		self::log( $post_id, 'publish', $user_id );

		return $post_id;
	}

	/**
	 * Take a track off the live site: back to draft, compiled out, kept.
	 *
	 * Nothing is deleted (the design's decision 3.9): the published copy stays as the record of
	 * what was live, and the status stays in "Currently mentoring", because removing it would
	 * take the Student role from everybody still on the track at the next sync (7.5). Refusing
	 * while students hold the status is the screen's, which can count them.
	 *
	 * When WordPress refuses the status change, its error comes back and nothing is compiled or
	 * logged.
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who unpublished it, for the log; 0 for the current user.
	 * @return int|WP_Error The post ID, or why nothing was done.
	 */
	public static function unpublish( $post_id, $user_id = 0 ) {
		$post = self::track_post( $post_id );

		if ( null === $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'wpcpm_track_not_published', __( 'That track is not published.', 'wpcredits-program-manager' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => (int) $post_id,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::compile();
		self::log( $post_id, 'unpublish', $user_id );

		return (int) $post_id;
	}

	/**
	 * What publishing this definition would refuse, without publishing it.
	 *
	 * The editor's question and Publish's question are the same call, because they have to give the
	 * same answer: `WPCPM_Tracks::validation_context()` leaves a track's own status out for
	 * everybody (T1's decision 4), so a screen built on that would call a clash fine and Publish
	 * would refuse it on the next screen. Read-only: nothing is stored.
	 *
	 * **Every other track counts, drafts included** (TRACKS-1, with BUILDER-1). A draft holds its
	 * status, key and name as surely as a published track does, because publishing it would claim
	 * them. Counting published tracks alone let two drafts share a key, and pressing Delete on the
	 * one never published then took the live form of the other. So New track, Duplicate and a
	 * track's own Save are refused what another draft holds, and Publish refuses a track while a
	 * stray draft holds its status or key. A published track counts by the copy students have; any
	 * other track, and a published one whose copy cannot be read, by the definition it was saved
	 * with. `compile()` still reads published copies alone.
	 *
	 * Beside `validate()`'s rules it asks the hours rule, `WPCPM_Track_Definition::check_hours()`
	 * (TRACKS-3), which `compile()` does not: a published track that breaks it keeps running, and
	 * nothing that breaks it can be published or saved from a track's own screen. The question
	 * editor asks it of the question a press is about alone, so a draft saved before the rule can
	 * be put right one question at a time (the final fix wave of the deep check of 1.109.1).
	 *
	 * @param int   $post_id    The track the definition belongs to.
	 * @param array $definition The definition to check.
	 * @return array[] The rules it fails, as `validate()` answers them; empty when it would publish.
	 */
	public static function check( $post_id, array $definition ) {
		$post_id = (int) $post_id;
		$others  = array();

		foreach ( self::all_ids() as $id ) {
			$id = (int) $id;

			if ( $id === $post_id ) {
				continue;
			}

			$post = self::track_post( $id );
			$copy = null !== $post && 'publish' === $post->post_status ? self::published( $id ) : null;
			$copy = is_array( $copy ) ? $copy : self::get( $id );

			if ( is_array( $copy ) ) {
				$others[] = $copy;
			}
		}

		$errors = WPCPM_Track_Definition::validate( $definition, self::context( $post_id, $definition, $others, true ) );

		return array_merge( $errors, WPCPM_Track_Definition::check_hours( $definition ) );
	}

	/**
	 * A track's definition as it was last published.
	 *
	 * @param int $post_id The track.
	 * @return array|null Null for a track never published, or a post that is not a track.
	 */
	public static function published( $post_id ) {
		if ( null === self::track_post( $post_id ) ) {
			return null;
		}

		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_PUBLISHED, true ) );
	}

	/**
	 * Where a track stands.
	 *
	 * @param int $post_id The track.
	 * @return string `draft`; `published`; `changed` for a published track with a saved edit not
	 *                yet published; `trash` for a trashed track; or an empty string for a post that
	 *                is not a track.
	 */
	public static function state( $post_id ) {
		$post = self::track_post( $post_id );

		if ( null === $post ) {
			return '';
		}

		// Named rather than folded into `draft`: the track list offers Publish on a draft, and
		// `publish()` refuses a trashed one, so a row that called itself a draft offered a button
		// that could only fail (T2a's final review, its M3).
		if ( 'trash' === $post->post_status ) {
			return 'trash';
		}

		if ( 'publish' !== $post->post_status ) {
			return 'draft';
		}

		return self::get( $post_id ) === self::published( $post_id ) ? 'published' : 'changed';
	}

	/**
	 * Which side of the switch a track is on: `builtin` while its PHP runs it, `definition` after.
	 *
	 * @param int $post_id The track.
	 * @return string
	 */
	public static function source( $post_id ) {
		return 'builtin' === get_post_meta( (int) $post_id, self::META_SOURCE, true ) ? 'builtin' : 'definition';
	}

	/**
	 * Whether a track runs from its definition because somebody flipped it.
	 *
	 * The fingerprint is written at the flip and removed when it goes back, so it is also what
	 * says the way back is on offer at all (the design's decision 3.5).
	 *
	 * @param int $post_id The track.
	 * @return bool
	 */
	public static function switched( $post_id ) {
		return '' !== (string) get_post_meta( (int) $post_id, self::META_SWITCHED, true );
	}

	/**
	 * Add a line to a track's log.
	 *
	 * @param int    $post_id The track.
	 * @param string $did     What happened, as a code: `publish`, `unpublish` and the like.
	 * @param int    $user_id Who did it; 0 for the current user.
	 * @param array  $detail  Optional detail stored only when not empty.
	 */
	public static function log( $post_id, $did, $user_id = 0, array $detail = array() ) {
		$entries = self::log_entries( $post_id );
		$entry   = array(
			'at'  => time(),
			'by'  => $user_id ? (int) $user_id : get_current_user_id(),
			'did' => sanitize_key( $did ),
		);

		if ( array() !== $detail ) {
			$entry['detail'] = $detail;
		}

		$entries[] = $entry;

		update_post_meta( (int) $post_id, self::META_LOG, $entries );
	}

	/**
	 * A track's log, oldest first.
	 *
	 * @param int $post_id The track.
	 * @return array[] Each with `at` (a timestamp), `by` (a user ID), `did`, and optionally `detail`.
	 */
	public static function log_entries( $post_id ) {
		$entries = get_post_meta( (int) $post_id, self::META_LOG, true );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Every published track, oldest first.
	 *
	 * @return WP_Post[]
	 */
	private static function published_posts() {
		return get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);
	}

	/**
	 * What the rules are told when a track is published or compiled.
	 *
	 * The site as its PHP describes it, from `WPCPM_Tracks::validation_context()` with the
	 * compiled tracks left out, and then the tracks this one is checked against: every other one,
	 * drafts included, when it is published (`check()`, since TRACKS-1), the ones already compiled
	 * when it is compiled. Its own status comes off the list only when it is locked to it, so a new
	 * track can never take a status that already names a track, one of the four built-in ones
	 * included.
	 *
	 * @param int   $post_id    The track.
	 * @param array $definition Its definition.
	 * @param array $others     The other tracks' definitions it is checked against.
	 * @param bool  $publishing Whether it is being published, rather than compiled.
	 * @return array
	 */
	private static function context( $post_id, array $definition, array $others, $publishing ) {
		$status  = isset( $definition['status'] ) ? (string) $definition['status'] : '';
		$context = WPCPM_Tracks::validation_context( '', false );
		$locked  = self::locked( $post_id, $status, $publishing );

		if ( null !== $locked ) {
			unset( $context['tracks'][ $locked['status'] ], $context['labels'][ $locked['status'] ] );
			$context['locked'] = $locked;
		}

		foreach ( $others as $other ) {
			if ( isset( $other['status'], $other['key'] ) ) {
				$context['tracks'][ (string) $other['status'] ] = (string) $other['key'];
				$context['labels'][ (string) $other['status'] ] = isset( $other['label'] ) ? (string) $other['label'] : '';
			}
		}

		return $context;
	}

	/**
	 * The status and key a track may not change, and which therefore pass as its own.
	 *
	 * A track published before keeps what it was published with (the design's 4.1). A built-in
	 * track keeps its PHP's: the seeded definition when it is published, and at every compile the
	 * copy of any track holding one of the four built-in statuses, which must then hold that
	 * track's key as well. Every other track has nothing locked, so the four statuses and the
	 * reserved keys are refused to it.
	 *
	 * @param int    $post_id    The track.
	 * @param string $status     Its status.
	 * @param bool   $publishing Whether it is being published, rather than compiled.
	 * @return array|null `status` and `key`, or null.
	 */
	private static function locked( $post_id, $status, $publishing ) {
		$builtin = WPCPM_Tracks::builtin_key( $status );

		if ( $publishing ) {
			$published = self::published( $post_id );

			if ( is_array( $published ) && isset( $published['status'], $published['key'] ) ) {
				return array(
					'status' => (string) $published['status'],
					'key'    => (string) $published['key'],
				);
			}

			if ( '' === $builtin || 'builtin' !== get_post_meta( (int) $post_id, self::META_SOURCE, true ) ) {
				return null;
			}
		} elseif ( '' === $builtin ) {
			return null;
		}

		return array(
			'status' => (string) $status,
			'key'    => $builtin,
		);
	}

	/**
	 * Seed the four built-in tracks once, the first time a site runs this version.
	 *
	 * The flag is claimed with `add_option()`, which only one request can win, so two requests
	 * arriving together cannot seed twice. The claim holds because the claimed value and its
	 * autoload are constant: core's `add_option()` upserts, and returns false when no row changed,
	 * so the request that arrives second loses. The compile that follows writes the index even when
	 * nothing is published, so every later request finds `wpcpm_tracks` among the autoloaded
	 * options rather than asking the database for an option that does not exist yet.
	 */
	public static function maybe_seed() {
		$seeded = get_option( self::OPT_SEEDED, null );

		if ( null !== $seeded ) {
			// A release that edits a hand-written form ships new seeds with a new version, and the
			// drafts this site made from the old ones are behind it. Nobody can bring them back by
			// hand, because the store refuses to save a built-in track (the design's decision 12).
			if ( (int) $seeded < self::SEED_VERSION ) {
				self::refresh_builtins();
				update_option( self::OPT_SEEDED, self::SEED_VERSION, true );
			}

			return;
		}

		if ( ! add_option( self::OPT_SEEDED, self::SEED_VERSION, '', true ) ) {
			return;
		}

		self::seed();
		self::compile();
	}

	/**
	 * Put every built-in draft back to the seed the plugin ships now.
	 *
	 * Only a draft with no published copy: a track students may have been reading keeps what it
	 * was published with, and its own refresh is a republish, which is the screen's business.
	 *
	 * @return array Track key => the post ID refreshed, or the WP_Error that stopped it.
	 */
	public static function refresh_builtins() {
		$done = array();

		foreach ( self::all_ids() as $post_id ) {
			if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) || '' !== (string) get_post_meta( $post_id, self::META_PUBLISHED, true ) ) {
				continue;
			}

			$held = self::get( $post_id );
			$key  = is_array( $held ) && isset( $held['key'] ) ? (string) $held['key'] : '';

			$done[ $key ] = self::refresh_builtin( $post_id );
		}

		return $done;
	}

	/**
	 * Put one built-in draft back to the seed the plugin ships now.
	 *
	 * The one write that may touch a built-in track's definition: `save()` refuses them, so that a
	 * seed cannot be pointed at another track (T2a's final review, its I2), and this writes the
	 * shipped seed and nothing a person typed.
	 *
	 * @param int $post_id The track.
	 * @return int|WP_Error The post ID, or why it was not refreshed.
	 */
	public static function refresh_builtin( $post_id ) {
		$post_id = (int) $post_id;

		if ( null === self::track_post( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) ) {
			return new WP_Error( 'wpcpm_track_not_builtin', __( 'Only a built-in track its PHP still runs is refreshed from the seed.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== (string) get_post_meta( $post_id, self::META_PUBLISHED, true ) ) {
			return new WP_Error( 'wpcpm_track_published', __( 'That track has been published, so its definition stays as it is: students may be reading it.', 'wpcredits-program-manager' ) );
		}

		$held  = self::get( $post_id );
		$key   = is_array( $held ) && isset( $held['key'] ) ? (string) $held['key'] : '';
		$seeds = self::seeds();

		if ( ! isset( $seeds[ $key ] ) ) {
			return new WP_Error( 'wpcpm_track_no_seed', __( 'The plugin ships no seed for that track, so there is nothing to refresh it from.', 'wpcredits-program-manager' ) );
		}

		$json = WPCPM_Track_Definition::encode( $seeds[ $key ] );

		if ( '' === $json ) {
			return self::unencodable();
		}

		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( $json ) );

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'         => $post_id,
					'post_title' => isset( $seeds[ $key ]['label'] ) ? (string) $seeds[ $key ]['label'] : '',
				)
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : $post_id;
	}

	/**
	 * Create the four built-in tracks' definitions from the seeds the plugin ships.
	 *
	 * Each is a draft, marked built-in, so its PHP keeps running it until a Program Administrator
	 * publishes it and switches it (the design's decision 3.5). A seed whose status a track already
	 * holds is passed over, so seeding twice creates nothing the second time.
	 *
	 * @return array Track key => the new post's ID, 0 when a track already holds its status, or a
	 *               WP_Error when WordPress refused to create it.
	 */
	public static function seed() {
		$held = array();

		foreach ( self::all_ids() as $post_id ) {
			$definition = self::get( $post_id );

			if ( is_array( $definition ) && isset( $definition['status'] ) ) {
				$held[] = (string) $definition['status'];
			}
		}

		$created = array();

		foreach ( self::seeds() as $key => $definition ) {
			if ( in_array( (string) $definition['status'], $held, true ) ) {
				$created[ $key ] = 0;
				continue;
			}

			$post_id = self::create( $definition );

			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, self::META_SOURCE, 'builtin' );
			}

			$created[ $key ] = $post_id;
		}

		return $created;
	}

	/**
	 * The seed definitions the plugin ships, by track key.
	 *
	 * Written by bin/build-seeds.php from the hand-written forms, and held to them byte for byte by
	 * bin/test-track-definitions.php.
	 *
	 * @return array<string, array>
	 */
	public static function seeds() {
		$seeds = array();

		foreach ( self::BUILTIN_KEYS as $key ) {
			$file = __DIR__ . '/seeds/' . $key . '.json';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file the plugin ships, read from its own folder.
			$definition = is_readable( $file ) ? WPCPM_Track_Definition::decode( (string) file_get_contents( $file ) ) : null;

			if ( is_array( $definition ) ) {
				$seeds[ $key ] = $definition;
			}
		}

		return $seeds;
	}

	/**
	 * What a definition built on a track differs from in its PHP, if it is a built-in track.
	 *
	 * Extracted from `equivalence()` so it can answer about a definition that is about to be
	 * published, not one that already is. Used by both `equivalence()` (for switch operations) and
	 * `php_differences()` (for the preflight).
	 *
	 * @param array $definition The definition to compare.
	 * @param bool  $is_builtin Whether this definition is a built-in track.
	 * @return string[] Empty when they match, or any of `form`, `label`, `course`, `course_id` and
	 *                  `hours`.
	 */
	private static function definition_differences( array $definition, $is_builtin ) {
		if ( ! $is_builtin ) {
			return array();
		}

		$status = isset( $definition['status'] ) ? (string) $definition['status'] : '';
		$key    = WPCPM_Tracks::builtin_key( $status );

		if ( '' === $key || ! isset( $definition['key'] ) || $key !== $definition['key'] ) {
			return array();
		}

		$php       = WPCPM_Tracks::builtin_row( $status );
		$mine      = array(
			'label'     => isset( $definition['label'] ) ? (string) $definition['label'] : '',
			'course'    => isset( $definition['course_url'] ) ? (string) $definition['course_url'] : '',
			'course_id' => isset( $definition['learn_course_id'] ) ? (int) $definition['learn_course_id'] : 0,
			'hours'     => isset( $definition['hours_target'] ) ? (int) $definition['hours_target'] : null,
		);
		$php_field = array(
			'label'     => 'label',
			'course'    => 'course_url',
			'course_id' => 'course_id',
			'hours'     => 'hours',
		);

		$differences = WPCPM_Track_Definition::compile_fields( $definition ) === WPCPM_Student_Report_Form::builtin_fields( $key ) ? array() : array( 'form' );

		foreach ( $php_field as $what => $field ) {
			if ( $mine[ $what ] !== $php[ $field ] ) {
				$differences[] = $what;
			}
		}

		return $differences;
	}

	/**
	 * How a built-in track's published definition differs from its PHP: empty when it does not.
	 *
	 * The switch waits on this (the design's decision 3.5). The form must be `builtin_fields()`
	 * byte for byte, and the name, course and hours the program map's, read with no compiled track
	 * in it, so the switch changes nothing a student sees.
	 *
	 * @param int $post_id The track.
	 * @return string[] `not_published` or `not_builtin` alone, or any of `form`, `label`, `course`,
	 *                  `course_id` and `hours`.
	 */
	public static function equivalence( $post_id ) {
		$post = self::track_post( $post_id );
		$copy = self::published( $post_id );

		if ( null === $post || 'publish' !== $post->post_status || ! is_array( $copy ) ) {
			return array( 'not_published' );
		}

		$status = isset( $copy['status'] ) ? (string) $copy['status'] : '';
		$key    = WPCPM_Tracks::builtin_key( $status );

		if ( '' === $key || ! isset( $copy['key'] ) || $key !== $copy['key'] ) {
			return array( 'not_builtin' );
		}

		return self::definition_differences( $copy, true );
	}

	/**
	 * How a definition about to be published differs from its PHP if its track is built-in.
	 *
	 * Used by the preflight (T2c) to check a definition before publishing. Unlike `equivalence()`,
	 * this does not require the track to be published: it checks the definition being handed to it.
	 * A definition of a non-built-in track always matches (its PHP is unchanged), and the preflight
	 * holds no further rule besides this.
	 *
	 * @param int   $post_id    The track.
	 * @param array $definition The definition about to be published.
	 * @return string[] Empty when they match (or the track is not built-in), or any of `form`,
	 *                  `label`, `course`, `course_id` and `hours`.
	 */
	public static function php_differences( $post_id, array $definition ) {
		$is_builtin = 'builtin' === (string) get_post_meta( (int) $post_id, self::META_SOURCE, true );

		return self::definition_differences( $definition, $is_builtin );
	}

	/**
	 * Run a built-in track from its definition instead of its PHP (the design's decision 3.5).
	 *
	 * Only while the two are identical, which is what makes the switch invisible to students; the
	 * definition's fingerprint is kept, so the way back stays open until the definition is edited,
	 * and, while the track is published, only as long as its published copy is still the PHP's.
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who switched it, for the log; 0 for the current user.
	 * @return int|WP_Error The post ID, or why it was not switched.
	 */
	public static function switch_to_definition( $post_id, $user_id = 0 ) {
		$post_id = (int) $post_id;

		if ( 'builtin' !== get_post_meta( $post_id, self::META_SOURCE, true ) ) {
			return new WP_Error( 'wpcpm_track_not_builtin', __( 'Only a built-in track its PHP still runs can switch to its definition.', 'wpcredits-program-manager' ) );
		}

		$differences = self::equivalence( $post_id );

		if ( array() !== $differences ) {
			return self::not_equivalent( $differences );
		}

		update_post_meta( $post_id, self::META_SWITCHED, md5( (string) get_post_meta( $post_id, self::META_DEFINITION, true ) ) );
		delete_post_meta( $post_id, self::META_SOURCE );
		self::compile();
		self::log( $post_id, 'switch_definition', $user_id );

		return $post_id;
	}

	/**
	 * Run a switched track from its PHP again (the design's decision 3.5).
	 *
	 * Only while the PHP exists, the definition is as it was when the track switched, and a
	 * published track's copy is still the PHP's (`equivalence()` holds nothing but
	 * `not_published`): after an edit, going back would put the PHP in front of students and
	 * quietly drop what was published. The fingerprint alone cannot see an edit that was
	 * published and then saved back to its old text without being published (the final review of
	 * T2a). An unpublished track may go back, the fingerprint permitting: its PHP already runs it,
	 * so going back changes nothing a student sees.
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who switched it back, for the log; 0 for the current user.
	 * @return int|WP_Error The post ID, or why it was not switched back.
	 */
	public static function switch_to_builtin( $post_id, $user_id = 0 ) {
		$post_id     = (int) $post_id;
		$fingerprint = (string) get_post_meta( $post_id, self::META_SWITCHED, true );
		$copy        = self::published( $post_id );

		if ( '' === $fingerprint || ! is_array( $copy ) ) {
			return new WP_Error( 'wpcpm_track_not_switched', __( 'That track does not run from its definition.', 'wpcredits-program-manager' ) );
		}

		if ( ! isset( $copy['status'], $copy['key'] ) || WPCPM_Tracks::builtin_key( (string) $copy['status'] ) !== $copy['key'] ) {
			return new WP_Error( 'wpcpm_track_no_php', __( 'The hand-written track this one came from has been removed, so there is nothing to go back to.', 'wpcredits-program-manager' ) );
		}

		if ( md5( (string) get_post_meta( $post_id, self::META_DEFINITION, true ) ) !== $fingerprint ) {
			return new WP_Error( 'wpcpm_track_edited', __( 'The definition has been edited since the switch, so going back would drop the edit.', 'wpcredits-program-manager' ) );
		}

		$differences = self::equivalence( $post_id );

		if ( array() !== $differences && array( 'not_published' ) !== $differences ) {
			return self::not_equivalent( $differences );
		}

		update_post_meta( $post_id, self::META_SOURCE, 'builtin' );
		delete_post_meta( $post_id, self::META_SWITCHED );
		self::compile();
		self::log( $post_id, 'switch_builtin', $user_id );

		return $post_id;
	}

	/**
	 * Why a built-in track did not switch, either way: its published copy is not its PHP.
	 *
	 * One error for both directions, so the screen gives one message beside the switch, with the
	 * differences `equivalence()` found.
	 *
	 * @param string[] $differences What `equivalence()` answered.
	 * @return WP_Error
	 */
	private static function not_equivalent( array $differences ) {
		return new WP_Error( 'wpcpm_track_not_equivalent', __( 'The definition is not identical to the track as its PHP runs it, so switching would change what students see.', 'wpcredits-program-manager' ), array( 'differences' => $differences ) );
	}

	/**
	 * A track's revisions, newest first, each with the definition it carries.
	 *
	 * Every save is a revision (the design's decision 3.2), and the revisioned meta lives on the
	 * revision post, so this is one query and one meta read a revision. One more than asked for
	 * is read, so the oldest revision History shows still has the copy before it to be compared
	 * with, and "created" is said only when there is none (decision 28). A site may cap how many
	 * revisions it keeps, which is why the published copy lives in meta of its own and not here.
	 *
	 * @param int $post_id The track.
	 * @param int $limit   How many History shows; one more comes back when there is one.
	 * @return array[] Each `id`, `at` (a Unix timestamp), `by` (a user ID) and `definition`
	 *                 (decoded, or null when that revision carries none); empty for a post that
	 *                 is not a track.
	 */
	public static function revisions( $post_id, $limit = 20 ) {
		$post_id = (int) $post_id;

		if ( null === self::track_post( $post_id ) ) {
			return array();
		}

		$revisions = array();

		foreach ( wp_get_post_revisions( $post_id, array( 'posts_per_page' => max( 1, (int) $limit ) + 1 ) ) as $revision ) {
			$revisions[] = array(
				'id'         => (int) $revision->ID,
				'at'         => (int) strtotime( (string) $revision->post_date_gmt . ' UTC' ),
				'by'         => (int) $revision->post_author,
				'definition' => WPCPM_Track_Definition::decode( get_post_meta( (int) $revision->ID, self::META_DEFINITION, true ) ),
			);
		}

		return $revisions;
	}

	/**
	 * How many revisions this site keeps of a track, as WordPress works that number out.
	 *
	 * WordPress prunes a post's revisions to `wp_revisions_to_keep()` at every save, from
	 * `WP_POST_REVISIONS` and the `wp_revisions_to_keep` filter, and the store registers no filter
	 * of its own. History needs the number to say "created" of the oldest revision it shows only
	 * when that revision really is the creation: on a site that caps them, the oldest kept may be
	 * the survivor of a pruning instead (the final review of T3b, finding 1).
	 *
	 * @param int $post_id The track.
	 * @return int -1 when nothing caps them, 0 when revisions are off, else how many are kept; -1
	 *             for a post that is not a track, which has no revisions of a definition to cap.
	 */
	public static function revisions_cap( $post_id ) {
		$post = self::track_post( (int) $post_id );

		return null === $post ? -1 : (int) wp_revisions_to_keep( $post );
	}

	/**
	 * Every other track, as the question editor's sharing index takes them.
	 *
	 * A column is shared when another track holds the same name, verbatim, whatever side of the
	 * switch that track is on: a built-in track's definition is its PHP's output, so its columns
	 * are the ones its form writes (the design's 5). A draft that was never published is named
	 * too, marked as one, because it does not write the column yet but will.
	 *
	 * @param int $post_id The track whose editor is asking, left out of the answer.
	 * @return array[] Each `label`, `published` and `columns`, oldest track first.
	 */
	public static function others( $post_id ) {
		$post_id = (int) $post_id;
		$others  = array();

		foreach ( self::all_ids() as $id ) {
			$id = (int) $id;

			if ( $id === $post_id ) {
				continue;
			}

			$definition = self::get( $id );

			if ( ! is_array( $definition ) ) {
				continue;
			}

			$state = self::state( $id );

			$others[] = array(
				'label'     => isset( $definition['label'] ) ? (string) $definition['label'] : '',
				'published' => 'published' === $state || 'changed' === $state || 'builtin' === self::source( $id ),
				'columns'   => isset( $definition['questions'] ) && is_array( $definition['questions'] ) ? array_map( 'strval', array_keys( $definition['questions'] ) ) : array(),
			);
		}

		return $others;
	}

	/**
	 * Whether a track has ever been published, from its log.
	 *
	 * The log rather than the published copy or the post status: unpublishing sets the status
	 * back to draft and the log is the one record that survives it (the design's decision 25).
	 *
	 * @param int $post_id The track.
	 * @return bool
	 */
	public static function ever_published( $post_id ) {
		foreach ( self::log_entries( $post_id ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['did'] ) && 'publish' === $entry['did'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a track that was never published.
	 *
	 * Decision 9 keeps every track that was ever published, because it is the record of what was
	 * created in the base. A draft that never was created no column, holds no student's status
	 * and was never compiled, so nothing else has to change: `compile()` reads published posts
	 * only (decision 25).
	 *
	 * **No form option is deleted** (TRACKS-1). `compile()` writes a form for a published track
	 * alone and deletes the form of every track that leaves the index, so a draft never has one of
	 * its own: the option its key names can only be the live form of another track on that key,
	 * which two drafts could share until `check()` counted drafts, and deleting it emptied the
	 * Student Report Card of every student on that track. A track in publish status is refused like
	 * one whose log says it was published, since a status set by hand or a log that lost its line
	 * leaves it live all the same, and deleting it would leave its row and its form in the index
	 * until the next compile.
	 *
	 * @param int $post_id The track.
	 * @return int|WP_Error The post ID, or why it was refused.
	 */
	public static function delete( $post_id ) {
		$post_id = (int) $post_id;
		$post    = self::track_post( $post_id );

		if ( null === $post ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( 'builtin' === self::source( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_builtin', __( 'A built-in track cannot be deleted: it is the record of a form the program runs.', 'wpcredits-program-manager' ) );
		}

		if ( 'publish' === $post->post_status || self::ever_published( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_was_published', __( 'This track has been published, so it is kept as the record of what was created in Airtable. It can be unpublished, not deleted.', 'wpcredits-program-manager' ) );
		}

		if ( ! wp_delete_post( $post_id, true ) ) {
			return new WP_Error( 'wpcpm_track_not_deleted', __( 'WordPress could not delete that track.', 'wpcredits-program-manager' ) );
		}

		return $post_id;
	}

	/**
	 * Every track's post ID, whatever its status, trash included.
	 *
	 * @return int[]
	 */
	public static function all_ids() {
		return get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array_keys( get_post_stati() ),
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);
	}

	/**
	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
	 *
	 * Every status, trash included, which `any` would leave behind. A form option is deleted for
	 * the key of every track post as well as for every key the index names, so a form written by
	 * a compile that never finished goes too.
	 */
	public static function delete_all() {
		foreach ( self::all_ids() as $post_id ) {
			$definition = self::get( $post_id );

			if ( is_array( $definition ) && ! empty( $definition['key'] ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $definition['key'] );
			}

			wp_delete_post( (int) $post_id, true );
		}

		foreach ( (array) get_option( WPCPM_Tracks::OPT_TRACKS, array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['key'] ) ) {
				delete_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $row['key'] );
			}
		}

		delete_option( WPCPM_Tracks::OPT_TRACKS );
		delete_option( self::OPT_SKIPPED );
		delete_option( self::OPT_SEEDED );
		WPCPM_Tracks::flush();
	}
}
