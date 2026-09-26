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
	 * The mark, `builtin`, that a seeded track carried while its hand-written form ran it.
	 *
	 * Nothing writes it now, and only the upgrade reads it (`upgrade_seeding()`): a site seeded by
	 * version 1 publishes each seed still marked and still a draft, and then clears the mark from
	 * every track post. Every track runs from its definition.
	 */
	const META_SOURCE = '_wpcpm_track_source';

	/**
	 * `1` once the reports automation item of the publish checklist (T2) is ticked, by a person or
	 * by the site when it published one of the four original tracks (`publish_seed()`).
	 */
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
	 * The saved definition's fingerprint when one of the four original tracks switched from its
	 * hand-written form to its definition (the design's decision 3.5).
	 *
	 * The record of the migration: the live site's four switched on 22 September 2026, and their
	 * logs say so too (`switch_definition`). The switch and its way back are gone with the
	 * hand-written forms, so nothing decides anything by it; `switched()` answers it.
	 */
	const META_SWITCHED = '_wpcpm_track_switched';

	/**
	 * The track a copy was made from.
	 *
	 * Post meta rather than a property, because `TRACK_PROPERTIES` does not know it and
	 * `validate()` refuses a property it does not know (the T2a handoff).
	 */
	const META_DUPLICATED_FROM = '_wpcpm_track_duplicated_from';

	/**
	 * The version of the seeding this site holds: written when it seeds, and moved up by an upgrade.
	 * Autoloaded: read every request.
	 */
	const OPT_SEEDED = 'wpcpm_tracks_seeded';

	/**
	 * The version of the seeding, which `OPT_SEEDED` records.
	 *
	 * 1 made each seed in `includes/tracks/seeds/` a draft marked `builtin`, which its hand-written
	 * form ran; 2 publishes each at seeding, and brings a site stamped 1 up to it once
	 * (`maybe_seed()`). The seeds themselves are the same bytes in both.
	 */
	const SEED_VERSION = 2;

	/**
	 * The claim a request holds on the upgrade from an older seeding, the time it was taken
	 * (`claim_upgrade()`).
	 *
	 * Not autoloaded: only a site whose stamp is older than `SEED_VERSION` reads it, and the claim
	 * is let go as soon as the stamp is written. A request killed inside the upgrade leaves it
	 * behind, which is why `delete_all()` deletes it.
	 */
	const OPT_UPGRADE_LOCK = 'wpcpm_tracks_upgrade_lock';

	/**
	 * How many seconds a claim on the upgrade holds before another request takes it over as a dead
	 * request's: the syncs' own two minutes, for a step that publishes four posts at most and
	 * compiles once.
	 */
	const UPGRADE_LOCK_TIMEOUT = 120;

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
	 * **Nothing half made is left behind.** When WordPress refuses `save()` once the post is
	 * inserted, as it does on a database error between the two writes, the post is deleted again
	 * before the error comes back, as `seed()` deletes a seed WordPress refuses to publish. Left
	 * behind, it would be a draft nobody asked for, holding a status, key and name no other track
	 * could take (TRACKS-1): a New track or a Duplicate typed again would be refused as taken, and a
	 * seed would be passed over by every later seeding as held, so the track would never be
	 * published (the design's decision 39).
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

		$saved = self::save( (int) $post_id, $definition );

		if ( is_wp_error( $saved ) ) {
			wp_delete_post( (int) $post_id, true );
		}

		return $saved;
	}

	/**
	 * Copy a track: a new draft holding the definition it is given, and a note of where it came from.
	 *
	 * The copy is a track of its own, whatever the original was (the design's decision 11): the
	 * caller has asked `check()` of its definition, which keeps a copy of one of the four original
	 * tracks off their statuses and keys.
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
	 * No rule is asked here: the screens ask `check()` first, and `publish()` asks it again. Every
	 * track is saved the same way, the four original tracks included, whose status and key the lock
	 * keeps at every Save and Publish (`locked()`).
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
			$automation = '1' === (string) get_post_meta( $post->ID, self::META_AUTOMATION, true );

			update_option( WPCPM_Tracks::OPT_FIELDS_PREFIX . $definition['key'], WPCPM_Track_Definition::compile_fields( $definition ), false );

			$rows[ (string) $definition['status'] ] = WPCPM_Track_Definition::row( $definition, $post->ID, $automation );
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

		// Every published track's status joins "Currently mentoring", the four original tracks'
		// included: a published track's status is what its students carry, and the students sync
		// reads only the statuses listed there (7.2). The exception the design's decision 13 made
		// for a seed was for a track its hand-written form still ran, and those are gone. A status
		// already listed is left as it is.
		WPCPM_Settings::add_student_status( (string) $definition['status'] );

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
	 * **One of the four original tracks is refused, first** (the design's decision 38), whoever is
	 * on it and whether or not it is published: the 150-hour track's form is the one every student
	 * on no track reads (decision 34), and the other three are the program's base statuses. A change
	 * to one of them is published instead.
	 *
	 * When WordPress refuses the status change, its error comes back and nothing is compiled or
	 * logged.
	 *
	 * @param int $post_id The track.
	 * @param int $user_id Who unpublished it, for the log; 0 for the current user.
	 * @return int|WP_Error The post ID, or why nothing was done.
	 */
	public static function unpublish( $post_id, $user_id = 0 ) {
		if ( self::is_original( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_reserved', __( 'The program\'s original tracks always run: edit the track and publish the change instead.', 'wpcredits-program-manager' ) );
		}

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
	 * Whether a track was switched from its hand-written form to its definition, before the forms
	 * were removed: the migration's record (`META_SWITCHED`).
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
	 * The codes written now are `publish`, `unpublish`, `columns` (with the columns a publish made)
	 * and `tick-<item>` and `untick-<item>` (the checklist). A `publish` the plugin made itself, at
	 * seeding or at the upgrade, and the `tick-<item>` lines after it name nobody and carry the
	 * detail `plugin`, `seed` or `upgrade` (`publish_seed()`). A site's logs also hold
	 * `switch_definition` and `switch_builtin`, written by the switch between a track's hand-written
	 * form and its definition before the forms were removed; History still names both.
	 *
	 * @param int    $post_id The track.
	 * @param string $did     What happened, as a code: `publish`, `unpublish` and the like.
	 * @param int    $user_id Who did it; 0 for the current user.
	 * @param array  $detail  Optional detail stored only when not empty.
	 */
	public static function log( $post_id, $did, $user_id = 0, array $detail = array() ) {
		self::write_log( $post_id, $did, $user_id ? (int) $user_id : get_current_user_id(), $detail );
	}

	/**
	 * Add a line to a track's log in the name of exactly the user given, 0 being nobody.
	 *
	 * `log()` reads 0 as whoever is signed in, who pressed what is being logged. The plugin's own
	 * publishing is pressed by nobody: it runs on whichever request reaches it first, or from the
	 * command line, so its line names nobody, and the screens call that the site itself
	 * (`WPCPM_Track_Builder_Screen::when_and_who()`).
	 *
	 * @param int    $post_id The track.
	 * @param string $did     What happened, as a code.
	 * @param int    $by      Who did it; 0 for nobody.
	 * @param array  $detail  Optional detail stored only when not empty.
	 */
	private static function write_log( $post_id, $did, $by, array $detail ) {
		$entries = self::log_entries( $post_id );
		$entry   = array(
			'at'  => time(),
			'by'  => (int) $by,
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
	 * The four original tracks as `WPCPM_Tracks::RESERVED_PAIRS` holds them, from
	 * `WPCPM_Tracks::validation_context()` with the compiled tracks left out, and then the tracks
	 * this one is checked against: every other one, drafts included, when it is published
	 * (`check()`, since TRACKS-1), the ones already compiled when it is compiled. Its own status
	 * comes off the list only when it is locked to it, so a new track can never take a status that
	 * already names another track. A definition holding one of the four original statuses is always
	 * locked to it, so what keeps a new track off one of those is `status_reserved` under another
	 * key, and the original track's own post, among the others, under the same key.
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
	 * A track published before keeps what it was published with (the design's 4.1). A definition
	 * holding one of the four original tracks' statuses keeps that track's pair from
	 * `WPCPM_Tracks::RESERVED_PAIRS` (the design's decision 36), whatever the post carries: when it
	 * is published and never was, as a fresh site's seeded track is, and at every compile. The pairs
	 * rather than the program map, which a compile fills: a lock read from the map would leave all
	 * four out of every compile the moment they were not in it. So each of the four compiles on its
	 * own key, and a definition holding one of their statuses under another key is refused by name
	 * (`status_reserved`). Every other track has nothing locked, so the four statuses and the
	 * reserved keys are refused to it.
	 *
	 * @param int    $post_id    The track.
	 * @param string $status     Its status.
	 * @param bool   $publishing Whether it is being published, rather than compiled.
	 * @return array|null `status` and `key`, or null.
	 */
	private static function locked( $post_id, $status, $publishing ) {
		if ( $publishing ) {
			$published = self::published( $post_id );

			if ( is_array( $published ) && isset( $published['status'], $published['key'] ) ) {
				return array(
					'status' => (string) $published['status'],
					'key'    => (string) $published['key'],
				);
			}
		}

		$key = WPCPM_Tracks::reserved_key( $status );

		if ( '' === $key ) {
			return null;
		}

		return array(
			'status' => (string) $status,
			'key'    => $key,
		);
	}

	/**
	 * Seed the four original tracks once, the first time a site runs the Track Builder, and bring a
	 * site seeded by an older version up to this one, once.
	 *
	 * The flag is claimed with `add_option()`, which only one request can win, so two requests
	 * arriving together cannot seed twice. The claim holds because the claimed value and its
	 * autoload are constant: core's `add_option()` upserts, and returns false when no row changed,
	 * so the request that arrives second loses. `seed()` ends with a compile, which writes the index
	 * even when nothing was published, so every later request finds `wpcpm_tracks` among the
	 * autoloaded options rather than asking the database for an option that does not exist yet.
	 *
	 * A site stamped with an older version upgrades under a claim of its own (`claim_upgrade()`),
	 * which only such a site asks for, so a site on this version never reads it. A request that
	 * finds the claim held returns having done nothing, and the site runs on the rows it holds
	 * meanwhile; the one that holds it runs `upgrade_seeding()`, writes the stamp, then lets the
	 * claim go. The claim holds a time rather than a constant, so a request killed inside the
	 * upgrade costs one request every `UPGRADE_LOCK_TIMEOUT` seconds, not every request: its claim is
	 * taken over once it is that old. The seeding keeps its constant: a claim holding a time would
	 * let two requests a second apart both seed. A request that read the old stamp before another
	 * finished runs the upgrade again once the claim is let go, and publishes nothing: the marks are
	 * the upgrade's list of what to publish, and they are cleared before the claim goes.
	 */
	public static function maybe_seed() {
		$seeded = get_option( self::OPT_SEEDED, null );

		if ( null !== $seeded ) {
			if ( (int) $seeded < self::SEED_VERSION && self::claim_upgrade() ) {
				self::upgrade_seeding();
				update_option( self::OPT_SEEDED, self::SEED_VERSION, true );
				delete_option( self::OPT_UPGRADE_LOCK );
			}

			return;
		}

		if ( ! add_option( self::OPT_SEEDED, self::SEED_VERSION, '', true ) ) {
			return;
		}

		self::seed();
	}

	/**
	 * Claim the upgrade: `add_option()`'s test-and-set, with a stale takeover.
	 *
	 * The house pattern (`WPCPM_Track_Publish::acquire_lock()`): a fresh claim wins outright, a held
	 * claim younger than `UPGRADE_LOCK_TIMEOUT` refuses, and an older one is taken over rather than
	 * left to stop the upgrade for good. Two requests taking over one dead claim together can both
	 * win, and the upgrade then runs twice, which costs at most a seed's lines logged twice.
	 *
	 * @return bool Whether this request now holds the claim.
	 */
	private static function claim_upgrade() {
		if ( add_option( self::OPT_UPGRADE_LOCK, time(), '', false ) ) {
			return true;
		}

		$held = (int) get_option( self::OPT_UPGRADE_LOCK );

		if ( $held && ( time() - $held ) < self::UPGRADE_LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::OPT_UPGRADE_LOCK, time(), false );

		return true;
	}

	/**
	 * What a site seeded by version 1 runs once: each seed it holds as a draft published, the mark
	 * cleared from every track post, and `seed()` run.
	 *
	 * Version 1 made each seed a draft marked `builtin`, which its hand-written form ran until
	 * somebody published it and switched it to its definition, as the live site's four were on 22
	 * September 2026. The forms are gone, so a track still marked and still a draft is published as
	 * `seed()` publishes a seed, from the definition it holds: that is the seed it was made from,
	 * since the store refused to save a marked track. It is asked by its state, not by its copy, so
	 * a request that died after writing a seed's copy and before publishing its post is finished by
	 * the next one; a request that died after publishing it leaves the track live without its flag,
	 * its ticks and its lines, which the next passes over as published. No rule is asked here, as
	 * `seed()` asks none: `compile()` leaves out whatever copy the rules refuse, and records why, for
	 * the track list to show.
	 *
	 * A seed somebody put in the trash stays there, its mark cleared: nothing in the plugin trashes
	 * a track, and `publish()` refuses to publish one out of the trash. The track then leaves the
	 * site's program map at the upgrade, and for the 150-hour track that is also the form every
	 * student on no track reads (decision 34). The Track Builder has no Restore, so the way back is
	 * `wp post update <ID> --post_status=draft`, then Publish, which asks the preflight. A draft
	 * whose definition cannot be read has nothing to publish, and one WordPress refuses to publish is
	 * left as it was, holding the copy it held, as `publish()` leaves one. A track marked and
	 * published keeps the copy it was published with, which its hand-written form matched when it
	 * went live.
	 *
	 * `seed()` then creates and publishes any of the four no track holds the status or key of, which
	 * the hand-written forms ran until now, and compiles once: on a site whose four were published
	 * and switched, into the rows it already holds, now without the `source` 1.110.4 wrote into each.
	 */
	private static function upgrade_seeding() {
		foreach ( self::all_ids() as $post_id ) {
			$post_id = (int) $post_id;

			if ( 'builtin' !== (string) get_post_meta( $post_id, self::META_SOURCE, true ) || 'draft' !== self::state( $post_id ) ) {
				continue;
			}

			$definition = self::get( $post_id );

			if ( is_array( $definition ) ) {
				self::publish_seed( $post_id, $definition, 'upgrade' );
			}
		}

		foreach ( self::all_ids() as $post_id ) {
			delete_post_meta( (int) $post_id, self::META_SOURCE );
		}

		self::seed();
	}

	/**
	 * Create the four original tracks from the seeds the plugin ships, publish them, and compile.
	 *
	 * A fresh site runs its four tracks at once, as it ran their hand-written forms before those were
	 * removed (the design's decision 39): each seed becomes a track whose definition and published
	 * copy are both the seed, published by nobody with its checklist ticked (`publish_seed()`), and
	 * one compile puts the four live, the lock keeping each on its status and key (`locked()`).
	 * Neither the preflight nor Airtable is asked: the seeds are the record of the forms the
	 * program's base already holds the columns for. No status joins "Currently mentoring", as it
	 * does when `publish()` publishes a track: the four are in its default list, and a site that took
	 * one out did so on purpose.
	 *
	 * A seed whose status or key a track already holds is passed over, so seeding twice creates
	 * nothing the second time; published beside that track, one of the two would be left out of the
	 * compile. A seed WordPress refuses to publish is deleted again, since the next run would pass
	 * over a draft holding its status (the design's decision 33), and one whose creation it cuts
	 * short leaves no post (`create()`). The compile runs whatever was created, so the index is
	 * written even when nothing is published.
	 *
	 * @return array Track key => the new post's ID, 0 when a track already holds its status or key,
	 *               or a WP_Error when WordPress refused to create or publish it.
	 */
	public static function seed() {
		$statuses = array();
		$keys     = array();

		foreach ( self::all_ids() as $post_id ) {
			$definition = self::get( $post_id );

			if ( is_array( $definition ) && isset( $definition['status'] ) ) {
				$statuses[] = (string) $definition['status'];
			}

			if ( is_array( $definition ) && isset( $definition['key'] ) ) {
				$keys[] = (string) $definition['key'];
			}
		}

		$created = array();

		foreach ( self::seeds() as $key => $definition ) {
			if ( in_array( (string) $definition['status'], $statuses, true ) || in_array( (string) $definition['key'], $keys, true ) ) {
				$created[ $key ] = 0;
				continue;
			}

			$post_id = self::create( $definition );

			// Normalized, as `save()` stored it, so the copy is the saved definition to the byte.
			if ( ! is_wp_error( $post_id ) ) {
				$published = self::publish_seed( $post_id, WPCPM_Track_Definition::normalize( $definition ), 'seed' );

				if ( is_wp_error( $published ) ) {
					wp_delete_post( $post_id, true );
					$post_id = $published;
				}
			}

			$created[ $key ] = $post_id;
		}

		self::compile();

		return $created;
	}

	/**
	 * Publish one of the four original tracks in nobody's name, from the definition it holds.
	 *
	 * What `seed()` does with each seed it creates, and the upgrade with each seed version 1 left a
	 * draft. The definition becomes the published copy and the post is published, as `publish()`
	 * does; when WordPress refuses the status change, the copy the post held before, or none, is put
	 * back, and nothing else is written.
	 *
	 * Then the track is left as the live site's four are: the three items of the publish checklist
	 * ticked, the reports automation's flag with them, since the automation's condition names the
	 * four statuses already (`WPCPM_Institutions::AUTOMATION_STATUSES`), and the log reading
	 * `publish`, then a `tick-<item>` line for each item ticked here. The ticks and the lines name
	 * nobody, and each line carries what published the track as its detail. They are written here
	 * rather than through `WPCPM_Track_Publish::tick()`, which names whoever is signed in and
	 * compiles on every tick of the automation item. An item somebody ticked already keeps its tick,
	 * who made it and when. Nothing is compiled here: the caller compiles once for all four, and
	 * `compile()` checks every copy it reads.
	 *
	 * @param int    $post_id    The track.
	 * @param array  $definition Its definition, the seed it was made from.
	 * @param string $step       What published it, for the log: `seed` or `upgrade`.
	 * @return int|WP_Error The post ID, or WordPress's refusal.
	 */
	private static function publish_seed( $post_id, array $definition, $step ) {
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
			if ( '' === $previous ) {
				delete_post_meta( $post_id, self::META_PUBLISHED );
			} else {
				update_post_meta( $post_id, self::META_PUBLISHED, wp_slash( $previous ) );
			}

			return $updated;
		}

		$ticks  = get_post_meta( $post_id, WPCPM_Track_Publish::META_CHECKLIST, true );
		$ticks  = is_array( $ticks ) ? $ticks : array();
		$ticked = array();

		foreach ( WPCPM_Track_Publish::CHECKLIST as $item ) {
			if ( ! isset( $ticks[ $item ] ) ) {
				$ticks[ $item ] = array(
					'by' => 0,
					'at' => time(),
				);
				$ticked[]       = $item;
			}
		}

		if ( array() !== $ticked ) {
			update_post_meta( $post_id, WPCPM_Track_Publish::META_CHECKLIST, $ticks );
		}

		update_post_meta( $post_id, self::META_AUTOMATION, '1' );
		self::write_log( $post_id, 'publish', 0, array( 'plugin' => $step ) );

		foreach ( $ticked as $item ) {
			self::write_log( $post_id, 'tick-' . $item, 0, array( 'plugin' => $step ) );
		}

		return $post_id;
	}

	/**
	 * The seed definitions the plugin ships, by track key.
	 *
	 * One file for each of the four original tracks, named by its key, in the program's order
	 * (`WPCPM_Tracks::RESERVED_PAIRS`). The files are the source of the four since the hand-written
	 * forms they were built from were removed: bin/test-track-definitions.php pins each byte for byte.
	 *
	 * @return array<string, array>
	 */
	public static function seeds() {
		$seeds = array();

		foreach ( WPCPM_Tracks::RESERVED_PAIRS as $key ) {
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
	 * A column is shared when another track holds the same name, verbatim (the design's 5). A
	 * published track writes its columns; a draft is named too, marked as one, because it does not
	 * write the column yet but will.
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
				'published' => 'published' === $state || 'changed' === $state,
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
	 * **One of the four original tracks, published or once published, is refused with a sentence
	 * of its own** (the design's decision 38), in place of decision 25's, which offers an unpublish
	 * these tracks do not have. A draft holding one of their statuses that was never published is
	 * deleted like any draft, one still carrying the old mark included: it created no column and
	 * holds no student. Beside a published original such a draft is a stray, which would hold up
	 * that track's Save and Publish, since a draft holds its status, key and name (TRACKS-1), and
	 * the track list offers Delete on it, as on every track never published.
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

		$published = 'publish' === $post->post_status || self::ever_published( $post_id );

		if ( $published && self::is_original( $post_id ) ) {
			return new WP_Error( 'wpcpm_track_reserved', __( 'The program\'s original tracks are kept: this one is the record of a form the program runs.', 'wpcredits-program-manager' ) );
		}

		if ( $published ) {
			return new WP_Error( 'wpcpm_track_was_published', __( 'This track has been published, so it is kept as the record of what was created in Airtable. It can be unpublished, not deleted.', 'wpcredits-program-manager' ) );
		}

		if ( ! wp_delete_post( $post_id, true ) ) {
			return new WP_Error( 'wpcpm_track_not_deleted', __( 'WordPress could not delete that track.', 'wpcredits-program-manager' ) );
		}

		return $post_id;
	}

	/**
	 * Whether a track holds the status of one of the four original tracks.
	 *
	 * Those in `WPCPM_Tracks::RESERVED_PAIRS`, read from its saved definition: the status the track
	 * list shows, the only one a seed never published holds, and the one a published track keeps,
	 * since every Save and Publish refuses a published track another status (`status_locked`).
	 *
	 * @param int $post_id The track.
	 * @return bool
	 */
	private static function is_original( $post_id ) {
		$definition = self::get( $post_id );

		return is_array( $definition ) && isset( $definition['status'] ) && WPCPM_Tracks::is_reserved( (string) $definition['status'] );
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
	 * Delete every track, its revisions and the options compiled from it, with the seeding's stamp
	 * and the claim on its upgrade, which a request killed inside the upgrade leaves behind. Called
	 * on uninstall.
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
		delete_option( self::OPT_UPGRADE_LOCK );
		WPCPM_Tracks::flush();
	}
}
