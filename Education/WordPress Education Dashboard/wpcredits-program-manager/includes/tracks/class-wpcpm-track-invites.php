<?php
/**
 * Each track's course invitation link: the private join link of the track's group on Learn
 * WordPress, which the student welcome prints so a new student can open their course (#298).
 *
 * Kept apart from the track's definition on purpose. A definition is published, diffed in History
 * and seeded from the repository, and this link is private: whoever holds it joins the course's
 * group. So it lives in one option of its own, by track post, typed on the track's page by an
 * Administrator and never written to a definition, a seed, History or the repository.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The course invitation links, by track.
 */
class WPCPM_Track_Invites {

	/**
	 * The option: track post ID => the link. Not autoloaded: only the student welcome and the track's
	 * page read it.
	 */
	const OPTION = 'wpcpm_track_invite_links';

	/**
	 * The host every link must be on: the group belongs to the track's course on Learn WordPress.
	 */
	const HOST = 'learn.wordpress.org';

	/**
	 * The box on the track's page.
	 */
	const FIELD = 'wpcpm_course_invite';

	/**
	 * Every link, by track post ID; links that are no longer valid are left out.
	 *
	 * @return array<int, string>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$out    = array();

		if ( ! is_array( $stored ) ) {
			return $out;
		}

		foreach ( $stored as $post_id => $link ) {
			if ( (int) $post_id > 0 && is_string( $link ) && self::is_link( $link ) ) {
				$out[ (int) $post_id ] = $link;
			}
		}

		return $out;
	}

	/**
	 * One track's link.
	 *
	 * @param int $post_id A track's post ID.
	 * @return string The link, or '' for a track with none.
	 */
	public static function for_post( $post_id ) {
		$all = self::all();

		return isset( $all[ (int) $post_id ] ) ? $all[ (int) $post_id ] : '';
	}

	/**
	 * The link of the live track a status names.
	 *
	 * @param string $status A track's status, as a student's program row holds it.
	 * @return string The link, or '' when the status is no live track or its track has none.
	 */
	public static function for_status( $status ) {
		$status = trim( (string) $status );

		if ( '' === $status ) {
			return '';
		}

		$live = WPCPM_Tracks::live();

		if ( ! isset( $live[ $status ]['post'] ) ) {
			return '';
		}

		return self::for_post( (int) $live[ $status ]['post'] );
	}

	/**
	 * The link for a student's welcome: the track they are on, read from their program row as the
	 * Student Report Card reads it (`WPCPM_Program::course_status()`).
	 *
	 * @param int $user_id The student's account.
	 * @return string The link, or '' for a student on no track or on a track without one.
	 */
	public static function for_student( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || ! class_exists( 'WPCPM_Students_Sync' ) || ! class_exists( 'WPCPM_Program' ) ) {
			return '';
		}

		$program = get_user_meta( $user_id, WPCPM_Students_Sync::META_PROGRAM, true );
		$held    = is_array( $program ) && isset( $program['program'] ) && is_scalar( $program['program'] ) ? (string) $program['program'] : '';

		return self::for_status( WPCPM_Program::course_status( $held, $user_id ) );
	}

	/**
	 * Whether a link is one this option keeps: an https address on Learn WordPress, with no
	 * credentials, port or fragment, and no whitespace.
	 *
	 * @param string $link The link.
	 * @return bool
	 */
	public static function is_link( $link ) {
		$link = (string) $link;

		if ( '' === $link || strlen( $link ) > 500 || preg_match( '/\s/', $link ) ) {
			return false;
		}

		$parts = wp_parse_url( $link );

		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( $parts['scheme'] ) || self::HOST !== strtolower( $parts['host'] ) ) {
			return false;
		}

		return ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['port'] ) && ! isset( $parts['fragment'] );
	}

	/**
	 * Set or clear one track's link.
	 *
	 * @param int    $post_id A track's post ID.
	 * @param string $link    The link as typed; '' clears it.
	 * @return true|WP_Error True when stored or cleared; an error, with nothing written, for a link
	 *                       that is not on Learn WordPress.
	 */
	public static function save( $post_id, $link ) {
		$post_id = (int) $post_id;
		$link    = trim( (string) $link );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'wpcpm_invite_track', __( 'That track does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== $link && ! self::is_link( $link ) ) {
			return new WP_Error(
				'wpcpm_invite_link',
				__( 'The course invitation link must be an https address on learn.wordpress.org, as the course group gives it.', 'wpcredits-program-manager' )
			);
		}

		$all = self::all();

		if ( '' === $link ) {
			unset( $all[ $post_id ] );
		} else {
			$all[ $post_id ] = $link;
		}

		if ( array() === $all ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $all, false );
		}

		return true;
	}
}
