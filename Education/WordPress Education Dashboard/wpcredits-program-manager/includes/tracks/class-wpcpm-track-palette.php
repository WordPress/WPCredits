<?php
/**
 * The hues a Track Builder track's chip can be painted in.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A fixed set of named hues, and the one chip rule each paints.
 *
 * **A hue is a name chosen from this list, never a color typed on a screen.** The rule it
 * prints goes into the stylesheet of every dashboard page, so a free color would be a way to
 * put arbitrary text into CSS; and the chips a mentor scans down a list are only useful while
 * no two read alike. The values follow `dashboard.css`, and the four built-in tracks' hues are
 * in the list under their own names, so their definitions can name them and a new track's
 * default can pass them over.
 *
 * Slate and amber are missing on purpose. They paint Paused and Pending graduation, the two
 * states on no track, and a track painted in either would read as a student who has stopped.
 */
final class WPCPM_Track_Palette {

	/**
	 * Hue name => red, green, blue.
	 *
	 * Blue, purple, teal and pink are the chips of the 150-hour track, the 50-hour track, the
	 * Developer Track and the Designer Track; cyan, green and red are the hues no chip uses
	 * yet, none of which reads as either state.
	 */
	const HUES = array(
		'blue'   => array( 56, 88, 233 ),
		'cyan'   => array( 8, 145, 178 ),
		'teal'   => array( 13, 148, 136 ),
		'green'  => array( 22, 163, 74 ),
		'red'    => array( 220, 38, 38 ),
		'pink'   => array( 219, 39, 119 ),
		'purple' => array( 124, 58, 237 ),
	);

	/**
	 * The shape of a track key, which is also a class name: `.wpcpm-badge--<key>`.
	 *
	 * One pattern for the two places that check a key - the definition's rule and this palette's
	 * guard - so they can never disagree about what reaches a stylesheet.
	 */
	const KEY_PATTERN = '/^[a-z0-9-]{2,20}$/';

	/**
	 * Whether a value is one of the hues.
	 *
	 * @param mixed $hue Anything.
	 * @return bool
	 */
	public static function is_hue( $hue ) {
		return is_string( $hue ) && array_key_exists( $hue, self::HUES );
	}

	/**
	 * The chip rule for one track, in the shape `dashboard.css` gives its own chips.
	 *
	 * The hue at 0.12 behind the row's own ink, and at 0.35 for the border the theme then
	 * removes: the Developer Track and Designer Track chips are drawn exactly so, which is why
	 * an authored chip needs no theme release to sit beside them.
	 *
	 * @param string $key Track key.
	 * @param string $hue Hue name.
	 * @return string One CSS rule, or an empty string for a key or a hue this cannot vouch for.
	 */
	public static function badge_rule( $key, $hue ) {
		if ( ! self::is_hue( $hue ) || 1 !== preg_match( self::KEY_PATTERN, (string) $key ) ) {
			return '';
		}

		list( $red, $green, $blue ) = self::HUES[ $hue ];

		return sprintf(
			'.wpcpm-badge--%1$s{background:rgba(%2$d,%3$d,%4$d,0.12);border-color:rgba(%2$d,%3$d,%4$d,0.35);}',
			$key,
			$red,
			$green,
			$blue
		);
	}
}
