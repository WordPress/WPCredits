<?php
/**
 * The stash-and-redirect machinery the two public application forms share.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A handler that has read a form answers with a redirect back to the page, and what the page
 * must draw travels one of two ways: an outcome the page knows by its slug travels bare in the
 * address, and anything with the sender's own words in it travels behind a random id in a
 * transient, so nothing typed is ever in a URL. `WPCPM_Flash`, the plugin's usual way of
 * carrying a message across a redirect, is no use to either form: it is stored per user and an
 * applicant has no account. The institution form (S1 of the institutions module) and the sponsor
 * form (Sponsors S5) each carried a copy of this; the S5 review asked for one (clean-up 1.98.1).
 * Each form describes itself in an array the caller builds from its own constants: `url`,
 * `outcomes`, `query_outcome`, `query_stash`, `prefix`, `minutes`, `one_shot`. All seven have a
 * default, so a form described in part draws a plain page rather than a notice. Blank detection
 * stays the caller's: the two forms agree on it today and are free not to tomorrow.
 */
final class WPCPM_Form_Stash {

	/**
	 * What a form's description means where the caller left a key out.
	 *
	 * Every public method reads seven keys, and a form is described by a caller's constants
	 * rather than by anything this class can check. A missing key would be a notice on a
	 * public page and, worse, a `null` prefix would send one form's transients somewhere the
	 * other form's reader can reach. The defaults are the harmless reading of each: no
	 * outcome slug is known, no outcome is one-shot, and the prefix is the plugin's own, so a
	 * half-described form draws the plain page instead of somebody else's stash.
	 *
	 * @return array
	 */
	private static function defaults() {
		return array(
			'url'           => '',
			'outcomes'      => array(),
			'query_outcome' => '',
			'query_stash'   => '',
			'prefix'        => 'wpcpm_',
			'minutes'       => 30,
			'one_shot'      => array(),
		);
	}

	/**
	 * Redirect back to the form with the outcome, and the stash behind a transient when it
	 * holds anything worth keeping.
	 *
	 * An outcome with nothing behind it travels bare, and only a slug the form knows may:
	 * anything else goes behind a transient, which is also what keeps `sent` on the stash it
	 * must have. Bare because a transient is two rows in the options table and every handler
	 * that calls this is reachable by anybody with no account: a sentence that is the same
	 * sentence for everybody must not let a stranger make the site write two rows per request.
	 *
	 * @param array    $form     The form's description (see the class docblock).
	 * @param string   $outcome  The outcome slug.
	 * @param array    $stash    What the page needs to draw: values, problems, a reference.
	 * @param callable $is_blank Says whether one value is blank.
	 */
	public static function bounce( array $form, $outcome, array $stash, callable $is_blank ) {
		$form  = wp_parse_args( $form, self::defaults() );
		$url   = '' !== (string) $form['url'] ? (string) $form['url'] : home_url( '/' );
		$stash = self::keep( $stash, $is_blank );

		if ( empty( $stash ) && in_array( (string) $outcome, (array) $form['outcomes'], true ) ) {
			wp_safe_redirect( add_query_arg( array( (string) $form['query_outcome'] => (string) $outcome ), $url ) );

			exit;
		}

		$stash['outcome'] = (string) $outcome;

		wp_safe_redirect( add_query_arg( array( (string) $form['query_stash'] => self::put( $form, $stash ) ), $url ) );

		exit;
	}

	/**
	 * What of a stash is worth a transient, or an empty array when none of it is.
	 *
	 * Blank answers go first, then blank keys: a form redrawn from a stash treats an absent
	 * value and an empty one identically, so the difference between "typed something" and "a
	 * POST with no body" is what decides whether two rows are written at all.
	 *
	 * @param array    $stash    What the handler wants to hand back.
	 * @param callable $is_blank Says whether one value is blank.
	 * @return array The same, minus the blanks.
	 */
	public static function keep( array $stash, callable $is_blank ) {
		if ( isset( $stash['values'] ) && is_array( $stash['values'] ) ) {
			foreach ( $stash['values'] as $column => $value ) {
				if ( call_user_func( $is_blank, $value ) ) {
					unset( $stash['values'][ $column ] );
				}
			}
		}

		foreach ( $stash as $key => $value ) {
			if ( is_array( $value ) ? empty( $value ) : call_user_func( $is_blank, $value ) ) {
				unset( $stash[ $key ] );
			}
		}

		return $stash;
	}

	/**
	 * Put one stash behind a random id and answer with the id.
	 *
	 * The id is derived from nothing (not the address, not the reference, not the session): it
	 * is the only thing that travels. Lowercased because it comes back through
	 * `WPCPM_Request::key()`, which lowercases.
	 *
	 * @param array $form  The form's description.
	 * @param array $stash What to keep.
	 * @return string The id.
	 */
	public static function put( array $form, array $stash ) {
		$form = wp_parse_args( $form, self::defaults() );
		$id   = strtolower( wp_generate_password( 32, false, false ) );

		set_transient( (string) $form['prefix'] . $id, $stash, (int) $form['minutes'] * MINUTE_IN_SECONDS );

		return $id;
	}

	/**
	 * Read the stash this request carries, if it carries one.
	 *
	 * A one-shot outcome (a confirmation that names a reference) is deleted as it is read, so
	 * a reload or a forwarded link shows the plain form; a failed attempt is not, because it
	 * holds only what the sender typed and losing it on a reload is the thing this exists to
	 * avoid. A bare outcome is answered only when the form knows the slug.
	 *
	 * @param array $form The form's description.
	 * @return array The stash, `array( 'outcome' => slug )` for a bare one, or an empty array.
	 */
	public static function read( array $form ) {
		$form = wp_parse_args( $form, self::defaults() );
		$id   = WPCPM_Request::key( (string) $form['query_stash'] );

		if ( '' !== $id ) {
			$stash = get_transient( (string) $form['prefix'] . $id );

			if ( is_array( $stash ) ) {
				$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

				if ( in_array( $outcome, (array) $form['one_shot'], true ) ) {
					delete_transient( (string) $form['prefix'] . $id );
				}

				return $stash;
			}
		}

		$said = WPCPM_Request::key( (string) $form['query_outcome'] );

		return in_array( $said, (array) $form['outcomes'], true ) ? array( 'outcome' => $said ) : array();
	}
}
