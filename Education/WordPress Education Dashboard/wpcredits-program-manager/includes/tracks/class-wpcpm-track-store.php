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
 * screen, with the definition in revisioned meta so every saved change is kept. `compile()` is
 * the only writer of what the site runs on (`WPCPM_Tracks`), and nothing calls it yet.
 *
 * `compile()` builds every published track from its latest saved definition, so a change saved
 * to a published track would reach students at the next compile of any track, not only its own.
 * Keeping an edit from students until it is published needs the definition that was published
 * recorded at publish time and read here instead (the design's open item 5). T2 adds that before
 * anything calls `compile()`, since publishing, unpublishing, the switch and the automation tick
 * all recompile.
 */
final class WPCPM_Track_Store {

	/** Eleven characters; `register_post_type()` refuses a name over twenty. */
	const POST_TYPE = 'wpcpm_track';

	/** The definition, as `WPCPM_Track_Definition::encode()` writes it. Revisioned. */
	const META_DEFINITION = '_wpcpm_track_definition';

	/**
	 * `builtin` for a migrated track its PHP still runs; absent for every other track.
	 *
	 * Written by the migration of phase T2 and cleared by the switch; read here only to carry it
	 * into the compiled row, where `WPCPM_Tracks` leaves such a track to its PHP.
	 */
	const META_SOURCE = '_wpcpm_track_source';

	/** `1` once somebody has ticked the reports automation item of the publish checklist (T2). */
	const META_AUTOMATION = '_wpcpm_track_automation';

	/**
	 * Register the type and its meta.
	 *
	 * The type before the meta, and that order is load-bearing: WordPress refuses
	 * `revisions_enabled`, with a notice, for a type that does not support revisions yet, and
	 * the definition would then be the one thing a revision does not keep.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
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
	 * Store a definition on its track.
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
		$post    = get_post( $post_id );

		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wpcpm_track_missing', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		$definition = WPCPM_Track_Definition::normalize( $definition );

		update_post_meta( $post_id, self::META_DEFINITION, wp_slash( WPCPM_Track_Definition::encode( $definition ) ) );

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
		$post = get_post( (int) $post_id );

		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return WPCPM_Track_Definition::decode( get_post_meta( (int) $post_id, self::META_DEFINITION, true ) );
	}

	/**
	 * Compile every published track into the options the live site runs on.
	 *
	 * Each form is written before the index that names it, so a request never finds a track in
	 * the index without its form. A published track whose definition cannot be read is left out
	 * rather than stopping the others, and the form of a track that has left the index is
	 * deleted with it.
	 *
	 * It trusts every definition it reads: nothing here runs `WPCPM_Track_Definition::validate()`.
	 * Checking each one here, and leaving out any that fails or that claims a status or key already
	 * compiled (the first by post ID keeps it), is T2's first task (the design's open item 6).
	 *
	 * @return array The index written: status => row.
	 */
	public static function compile() {
		$previous = get_option( WPCPM_Tracks::OPT_TRACKS, array() );
		$rows     = array();

		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$definition = self::get( $post->ID );

			if ( ! is_array( $definition ) || empty( $definition['status'] ) || empty( $definition['key'] ) ) {
				continue;
			}

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
		WPCPM_Tracks::flush();

		return $rows;
	}

	/**
	 * Delete every track, its revisions and the options compiled from it. Called on uninstall.
	 *
	 * Every status, trash included, which `any` would leave behind. A form option is deleted for
	 * the key of every track post as well as for every key the index names, so a form written by
	 * a compile that never finished goes too.
	 */
	public static function delete_all() {
		$ids = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array_keys( get_post_stati() ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $ids as $post_id ) {
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
		WPCPM_Tracks::flush();
	}
}
