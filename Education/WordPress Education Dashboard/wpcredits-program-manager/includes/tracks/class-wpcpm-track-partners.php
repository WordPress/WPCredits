<?php
/**
 * The Track Builder's tracks - the sponsors a track names as its partners.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a track's partners: which sponsors a track may name, what the Track Builder's picker
 * offers, and the partnership line a student on the track sees.
 *
 * A track stores sponsor keys and nothing else (`WPCPM_Track_Definition`'s `sponsors`, which checks
 * their shape and never asks the sponsors store). Whether a key still names a partner is asked here,
 * at every read: an Approved sponsor whose effective tier is Partner. A sponsor whose term ends or
 * who is paused therefore stops showing on the site the day it stops qualifying, with nothing
 * written to the track, and shows again once renewed; the track keeps the key until a manager
 * unticks it (spec section 4).
 *
 * What students see is read from the compiled track, through `WPCPM_Tracks`, because the program
 * map and the forms never read a `wpcpm_track` post; a sponsor report's draft reads each partner
 * track's published copy once (`WPCPM_Sponsor_Report::pull_narratives()`). The Track Builder is a
 * manager's screen: its picker reads the sponsors store whatever the tiers' switch says, and its
 * preview hands `render_line()` the partners its draft names. The partnership line names a tier's
 * benefit, so it is drawn only while that switch is on, in the preview as on the Student Report
 * Card.
 *
 * The sponsors store and the tier ladder are called when a method runs, never while this file
 * loads, so it can be required before the sponsor modules are.
 */
final class WPCPM_Track_Partners {

	/** The picker's field on the Track Builder's properties form, posted as a list. */
	const FIELD = 'wpcpm_sponsors';

	/**
	 * The sponsors a track may name today: Approved, effective tier Partner. By name, case aside,
	 * the order the picker lists them in.
	 *
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return array<string, string> Key => name.
	 */
	public static function eligible( $today = '' ) {
		$out = array();

		foreach ( WPCPM_Sponsors_Index::approved() as $record => $row ) {
			if ( self::qualifies( $row, $today ) ) {
				$out[ (string) $record ] = (string) $row['name'];
			}
		}

		asort( $out, SORT_FLAG_CASE | SORT_STRING );

		return $out;
	}

	/**
	 * Whether a sponsor's row qualifies as a partner today: Approved, and the effective tier is
	 * Partner. Asked at every render, so a lapsed or paused partner stops showing without any write
	 * to the track (spec section 4).
	 *
	 * @param array  $row   A sponsor row, read for `status`, `tier` and `tier_until`.
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return bool
	 */
	public static function qualifies( array $row, $today = '' ) {
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';

		return WPCPM_Sponsors_Index::STATUS_APPROVED === $status
			&& WPCPM_Sponsor_Tiers::PARTNER === WPCPM_Sponsor_Tiers::effective( $row, $today );
	}

	/**
	 * The picker's choices: the eligible sponsors, plus every stored key, so a save can never drop
	 * one silently. A stored key that no longer qualifies carries a note; an unknown one another.
	 *
	 * @param string[] $stored The keys the track names.
	 * @param string   $today  A `Y-m-d` date, or '' for today.
	 * @return array<string, array{label:string, note:string}> Key => its name and its note, the
	 *                                                          eligible ones first.
	 */
	public static function choices( array $stored, $today = '' ) {
		$choices = array();

		foreach ( self::eligible( $today ) as $record => $name ) {
			$choices[ $record ] = array(
				'label' => $name,
				'note'  => '',
			);
		}

		foreach ( $stored as $record ) {
			$record = (string) $record;

			if ( isset( $choices[ $record ] ) ) {
				continue;
			}

			$row = WPCPM_Sponsors_Index::row( $record );

			$choices[ $record ] = array(
				'label' => $row ? (string) $row['name'] : $record,
				'note'  => $row ? __( 'no longer a Partner', 'wpcredits-program-manager' ) : __( 'unknown sponsor', 'wpcredits-program-manager' ),
			);
		}

		return $choices;
	}

	/**
	 * The keys the compiled row of a live track holds, in the track's order.
	 *
	 * @param string $key A track key.
	 * @return string[] None for a track that names none or a key no live track has.
	 */
	public static function keys_of( $key ) {
		foreach ( WPCPM_Tracks::live() as $row ) {
			if ( (string) $row['key'] === (string) $key ) {
				return isset( $row['sponsors'] ) && is_array( $row['sponsors'] ) ? array_values( array_map( 'strval', $row['sponsors'] ) ) : array();
			}
		}

		return array();
	}

	/**
	 * The partners of a live track that qualify today, in the track's order.
	 *
	 * @param string $key   A track key.
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return array[] Each `record`, `name`, `website` and `logo` (the site's own copy, or '').
	 */
	public static function for_track( $key, $today = '' ) {
		return self::qualifying( self::keys_of( $key ), $today );
	}

	/**
	 * The sponsors of a list of keys that qualify today, in the list's order, a test sponsor left out
	 * (1.123.18).
	 *
	 * @param string[] $keys  Sponsor keys.
	 * @param string   $today A `Y-m-d` date, or '' for today.
	 * @return array[] Each `record`, `name`, `website` and `logo` (the site's own copy, or '').
	 */
	private static function qualifying( array $keys, $today = '' ) {
		$out = array();

		foreach ( $keys as $record ) {
			$row = WPCPM_Sponsors_Index::row( $record );

			// A test sponsor is never listed publicly (1.123.18): its students are not shown it, nor
			// bound to its offers. The picker, a manager's, still offers it (`eligible()`).
			if ( ! $row || ! self::qualifies( $row, $today ) || WPCPM_Sponsors_Index::is_test( $row ) ) {
				continue;
			}

			$logo  = WPCPM_Sponsors_Index::display_logo( $record );
			$out[] = array(
				'record'  => $record,
				'name'    => (string) $row['name'],
				'website' => (string) $row['website'],
				'logo'    => is_array( $logo ) && ! empty( $logo['url'] ) ? (string) $logo['url'] : '',
			);
		}

		return $out;
	}

	/**
	 * The partnership line a student sees on a track with partners, and the Track Builder's preview
	 * mirrors. Nothing while the tiers' switch is off: the line names a tier's benefit.
	 *
	 * The partners are the live track's, unless a list of keys is handed: the preview hands the ones
	 * its draft names, so a partner ticked in a draft previews as it will render once published. A
	 * list handed empty draws nothing, whatever the live track names.
	 *
	 * `rel="sponsored"`, as the Partners page's links carry, since these are sponsors' links. The
	 * logo's `alt` is empty: the name sits beside it in its own span, and a name in `alt` too would
	 * be read twice.
	 *
	 * @param string        $key   A track key.
	 * @param string        $today A `Y-m-d` date, or '' for today.
	 * @param string[]|null $keys  The sponsor keys to draw, in their order, in place of the live
	 *                             track's; null for the live track's.
	 * @return string The line's HTML, every value escaped; '' when there is nothing to show.
	 */
	public static function render_line( $key, $today = '', $keys = null ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return '';
		}

		$keys     = null === $keys ? self::keys_of( $key ) : array_values( array_unique( array_map( 'strval', (array) $keys ) ) );
		$partners = self::qualifying( $keys, $today );

		if ( empty( $partners ) ) {
			return '';
		}

		$out = '<div class="wpcpm-student__partners"><p class="wpcpm-student__partners-lead">' . esc_html__( 'In partnership with', 'wpcredits-program-manager' ) . '</p><ul class="wpcpm-student__partners-list">';

		foreach ( $partners as $partner ) {
			$inner = ( '' !== $partner['logo'] ? sprintf( '<img class="wpcpm-student__partner-logo" src="%s" alt="" />', esc_url( $partner['logo'] ) ) : '' )
				. '<span class="wpcpm-student__partner-name">' . esc_html( $partner['name'] ) . '</span>';
			$href  = self::href( $partner['website'] );

			$out .= '<li class="wpcpm-student__partner">' . ( '' !== $href ? sprintf( '<a class="wpcpm-student__partner-link" href="%1$s" rel="sponsored noopener">%2$s</a>', $href, $inner ) : $inner ) . '</li>';
		}

		return $out . '</ul></div>';
	}

	/**
	 * A website as a link target, escaped: with a scheme, as the Partners page gives one, or
	 * nothing.
	 *
	 * @param string $website What the sponsor's row holds.
	 * @return string
	 */
	private static function href( $website ) {
		$website = trim( (string) $website );

		if ( '' === $website ) {
			return '';
		}

		return esc_url( preg_match( '#^https?://#i', $website ) ? $website : 'https://' . $website );
	}
}
