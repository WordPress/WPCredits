<?php
/**
 * The small line icons the contact rows carry.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline SVG icons for the contact fields on both dashboards.
 *
 * Drawn from primitives - circles, rectangles and short paths on a 24×24 box, stroked
 * in `currentColor` - rather than copied from a brand's own artwork. Two reasons. A
 * stroked line icon inherits the text colour it sits beside, so it cannot end up the
 * wrong colour in a theme nobody has seen; and a plugin that ships Slack's or GitHub's
 * logo is redistributing a trademark, which is a licensing question this feature does
 * not need to raise. Nothing here is a brand mark: the Slack row gets a channel hash,
 * the GitHub row gets code brackets, the profile row gets a person.
 *
 * `aria-hidden` on every one. The label is right beside it and already says what the
 * row is, so announcing the icon as well would just read the same thing twice.
 *
 * **One exception, added deliberately: `slack_logo()`.** A link whose only job is "this goes to
 * Slack" needs to be recognisable at a glance, and a channel hash is not. It is Slack's own
 * published asset rather than a redrawing of it, and it is kept out of the icon set above so it
 * cannot be pressed into service as a line icon.
 */
class WPCPM_Icons {

	/**
	 * The icon set, as the inner markup of a 24×24 `<svg>`.
	 *
	 * @return array<string, string>
	 */
	private static function shapes() {
		return array(
			// An envelope: the body, then the flap as a single fold.
			'email'    => '<rect x="3" y="5.25" width="18" height="13.5" rx="2"/><path d="M3.6 7.2 12 13l8.4-5.8"/>',
			// A channel hash. Recognisable for Slack without being Slack's mark.
			'slack'    => '<path d="M9.3 4.5 7.8 19.5M16.2 4.5l-1.5 15M4.5 9.3h15M4.5 15h15"/>',
			// A person, because the row links to somebody's profile page.
			'profile'  => '<circle cx="12" cy="8.5" r="3.75"/><path d="M5.25 19.5c0-3.2 3-5.75 6.75-5.75s6.75 2.55 6.75 5.75"/>',
			// A globe.
			'website'  => '<circle cx="12" cy="12" r="8.25"/><path d="M3.75 12h16.5M12 3.75c2.4 2.5 2.4 14 0 16.5M12 3.75c-2.4 2.5-2.4 14 0 16.5"/>',
			// Code brackets.
			'code'     => '<path d="M9.6 8.4 6 12l3.6 3.6M14.4 8.4 18 12l-3.6 3.6"/>',
			// A calendar, for the internship dates.
			'calendar' => '<rect x="3.75" y="5.25" width="16.5" height="14.25" rx="2"/><path d="M3.75 9.75h16.5M8.25 3.75v3M15.75 3.75v3"/>',
		);
	}

	/**
	 * Slack's logo, from Slack's own brand assets.
	 *
	 * This is `Slack_RGB.svg` as published in Slack's Brandfolder - the four-colour mark
	 * followed by the wordmark - with two mechanical changes and no visual ones. The class-based
	 * `<style>` block is flattened into a `fill` on each shape, and the wordmark's shapes are
	 * given an explicit black rather than relying on the default. Both are necessary for an
	 * inline SVG: class names like `.st0` inside a page would collide with whatever else uses
	 * them, and an unspecified fill inherits the surrounding text colour - recolouring the
	 * logo, which is the one thing the licence does not allow.
	 *
	 * Every drawable element is kept, and not only the eleven `<path>`s: the wordmark's "l" is
	 * a `<rect>` and its "k" a `<polygon>`, so a paths-only copy of this file renders as
	 * "s ac" - wrong, but close enough to right to go unnoticed.
	 *
	 * Wide, not square: the asset is a 622.3 × 254.4 lockup, so this is a logo rather than an
	 * icon. Sized by height, with the width following from the artwork's own proportions.
	 *
	 * Returned rather than echoed, and callers must not pass it through `wp_kses_post()` -
	 * that strips `<svg>` outright and lowercases `viewBox` into meaninglessness.
	 *
	 * @param int $height Rendered height in pixels.
	 * @return string
	 */
	public static function slack_logo( $height = 24 ) {
		$height = (int) $height;
		// The lockup's own proportions, so it is never stretched.
		$width = (int) round( $height * ( 622.3 / 254.4 ) );

		return sprintf(
			'<svg width="%1$d" height="%2$d" viewBox="0 0 622.3 254.4" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
				. '<path fill="#000000" d="M221.5,161.5l6.2-14.4c6.7,5,15.6,7.6,24.4,7.6c6.5,0,10.6-2.5,10.6-6.3c-0.1-10.6-38.9-2.3-39.2-28.9 c-0.1-13.5,11.9-23.9,28.9-23.9c10.1,0,20.2,2.5,27.4,8.2l-5.8,14.7c-6.6-4.2-14.8-7.2-22.6-7.2c-5.3,0-8.8,2.5-8.8,5.7 c0.1,10.4,39.2,4.7,39.6,30.1c0,13.8-11.7,23.5-28.5,23.5C241.4,170.6,230.1,167.7,221.5,161.5"/>'
				. '<path fill="#000000" d="M459.4,141.9c-3.1,5.4-8.9,9.1-15.6,9.1c-9.9,0-17.9-8-17.9-17.9s8-17.9,17.9-17.9c6.7,0,12.5,3.7,15.6,9.1l17.1-9.5 c-6.4-11.4-18.7-19.2-32.7-19.2c-20.7,0-37.5,16.8-37.5,37.5c0,20.7,16.8,37.5,37.5,37.5c14.1,0,26.3-7.7,32.7-19.2L459.4,141.9z"/>'
				. '<rect fill="#000000" x="290.8" y="64.5" width="21.4" height="104.7"/>'
				. '<polygon fill="#000000" points="484.9,64.5 484.9,169.2 506.3,169.2 506.3,137.8 531.7,169.2 559.1,169.2 526.8,131.9 556.7,97.1 530.5,97.1 506.3,126 506.3,64.5 "/>'
				. '<path fill="#000000" d="M375.8,142.1c-3.1,5.1-9.5,8.9-16.7,8.9c-9.9,0-17.9-8-17.9-17.9s8-17.9,17.9-17.9c7.2,0,13.6,4,16.7,9.2V142.1z M375.8,97.1v8.5c-3.5-5.9-12.2-10-21.3-10c-18.8,0-33.6,16.6-33.6,37.4c0,20.8,14.8,37.6,33.6,37.6c9.1,0,17.8-4.1,21.3-10v8.5 h21.4v-72H375.8z"/>'
				. '<path fill="#E01E5A" d="M89.2,142c0,7.3-5.9,13.2-13.2,13.2s-13.2-5.9-13.2-13.2s5.9-13.2,13.2-13.2h13.2V142z"/>'
				. '<path fill="#E01E5A" d="M95.8,142c0-7.3,5.9-13.2,13.2-13.2s13.2,5.9,13.2,13.2V175c0,7.3-5.9,13.2-13.2,13.2s-13.2-5.9-13.2-13.2 V142z"/>'
				. '<path fill="#36C5F0" d="M109,89c-7.3,0-13.2-5.9-13.2-13.2c0-7.3,5.9-13.2,13.2-13.2s13.2,5.9,13.2,13.2V89H109z"/>'
				. '<path fill="#36C5F0" d="M109,95.7c7.3,0,13.2,5.9,13.2,13.2c0,7.3-5.9,13.2-13.2,13.2H75.9c-7.3,0-13.2-5.9-13.2-13.2 c0-7.3,5.9-13.2,13.2-13.2H109z"/>'
				. '<path fill="#2EB67D" d="M161.9,108.9c0-7.3,5.9-13.2,13.2-13.2s13.2,5.9,13.2,13.2c0,7.3-5.9,13.2-13.2,13.2h-13.2V108.9z"/>'
				. '<path fill="#2EB67D" d="M155.3,108.9c0,7.3-5.9,13.2-13.2,13.2s-13.2-5.9-13.2-13.2V75.8c0-7.3,5.9-13.2,13.2-13.2 s13.2,5.9,13.2,13.2V108.9z"/>'
				. '<path fill="#ECB22E" d="M142.1,161.8c7.3,0,13.2,5.9,13.2,13.2c0,7.3-5.9,13.2-13.2,13.2s-13.2-5.9-13.2-13.2v-13.2H142.1z"/>'
				. '<path fill="#ECB22E" d="M142.1,155.2c-7.3,0-13.2-5.9-13.2-13.2s5.9-13.2,13.2-13.2h33.1c7.3,0,13.2,5.9,13.2,13.2 s-5.9,13.2-13.2,13.2H142.1z"/>'
				. '</svg>',
			$width,
			$height
		);
	}

	/**
	 * One icon, as inline SVG.
	 *
	 * @param string $name Icon key.
	 * @param int    $size Pixel size.
	 * @return string HTML, or an empty string for an unknown key.
	 */
	public static function svg( $name, $size = 16 ) {
		$shapes = self::shapes();
		$name   = (string) $name;

		if ( ! isset( $shapes[ $name ] ) ) {
			return '';
		}

		$size = max( 8, min( 64, (int) $size ) );

		return sprintf(
			'<svg class="wpcpm-icon wpcpm-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $name ),
			$size,
			$shapes[ $name ]
		);
	}

	/**
	 * The three flat glyphs the Mentor Report Card's triage needs.
	 *
	 * Filled rather than stroked, so they are kept apart from `shapes()` above: a magnifying
	 * glass and a cross at 15-16px read better as solid shapes than as 1.6px strokes, and
	 * mixing the two rendering styles in one set would mean every caller having to know which
	 * kind it was asking for.
	 *
	 * They came from wpcredits-theme with the triage script in 1.64.0. Same paths, so nothing
	 * about the card changed the day it stopped being the theme's job.
	 *
	 * @param string $name One of `search`, `close`, `people`.
	 * @param int    $size Pixel size.
	 * @return string HTML, or an empty string for an unknown key.
	 */
	public static function ui( $name, $size = 16 ) {
		$paths = array(
			'search' => 'M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z',
			'close'  => 'm13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z',
			'people' => 'M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
		);

		$name = (string) $name;

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$size = max( 8, min( 64, (int) $size ) );

		// `people` is drawn as overlapping shapes and needs the even-odd rule to keep its
		// holes; the other two are single outlines and would be unchanged by it.
		$rule = 'people' === $name ? ' fill-rule="evenodd" clip-rule="evenodd"' : '';

		return sprintf(
			'<svg class="wpcpm-icon wpcpm-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="%3$s"%4$s /></svg>',
			esc_attr( $name ),
			$size,
			$paths[ $name ],
			$rule
		);
	}

	/**
	 * Why callers echo these directly instead of filtering them.
	 *
	 * There is nothing to sanitize: every byte comes from `shapes()`, which is a static
	 * array in this file, and no part of it is built from input. And filtering would do
	 * harm - `wp_kses()` lowercases attribute names, SVG is case-sensitive, so `viewBox`
	 * would come back as `viewbox` and every icon would lose its scaling. If a caller
	 * ever needs to pass one through `wp_kses_post()` for an unrelated reason, the SVG
	 * has to be added back afterwards, not whitelisted.
	 */
}
