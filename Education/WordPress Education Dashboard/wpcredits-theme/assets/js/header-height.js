/**
 * The sticky header's real height, for everything that sits below it, and whether its row has
 * wrapped.
 *
 * `--wpc-header-height` is a constant 76px, and the bar is only that tall on a wide window: its
 * links wrap to a second row on a mid-width one, the whole row wraps under the brand where the
 * viewer chip will not fit beside it (or, for a chip with no link to the Student Report Card or the
 * Mentor Report Card, where the menu, the help button and the chip will not fit beside it on one line), and it is two to four
 * rows tall on a phone. So the pinned group heading, the open menu panel and the landing offset,
 * which all place something below the bar, read `--wpc-header-live` first, and this writes it:
 * the height of the header part, rounded up to a whole pixel, on the root element, kept current
 * as the bar changes height (a window resized, a font loaded, a row wrapped).
 *
 * The constant stays the fallback before this runs and where it cannot, and it stays the only
 * input to the bar's own `min-height`: a minimum fed by its own measurement could only grow.
 *
 * Whether the row has wrapped is the browser's decision, made from the widths of the brand and
 * the chip, and nothing in CSS can read it. So this marks the header `wpc-header--stacked` while
 * the group holding the navigation and the chip sits below the brand rather than beside it, and
 * the stylesheet spaces the two rows. The mark changes the bar's height but never its widths, so
 * it cannot change whether the row wraps. It is set in the next frame rather than inside the
 * observer's callback: a change to the observed element's own size from inside that callback is
 * what the browser reports as a resize loop.
 *
 * Without a header part on the page, or in a browser without `ResizeObserver`, it does nothing
 * and the stylesheet's fallbacks apply.
 */
( function () {
	'use strict';

	var part = document.querySelector( '.wpc-header-part' );

	if ( ! part || 'function' !== typeof window.ResizeObserver ) {
		return;
	}

	var root = document.documentElement;
	var header = part.querySelector( '.wpc-header' );
	var brand = part.querySelector( '.wpc-brand' );
	var group = part.querySelector( '.wpc-nav__center' );
	var frame = 0;

	function measure() {
		root.style.setProperty( '--wpc-header-live', Math.ceil( part.getBoundingClientRect().height ) + 'px' );
	}

	function arrange() {
		frame = 0;

		if ( ! header || ! brand || ! group ) {
			return;
		}

		header.classList.toggle(
			'wpc-header--stacked',
			group.getBoundingClientRect().top >= brand.getBoundingClientRect().bottom
		);
	}

	new window.ResizeObserver( function () {
		measure();

		if ( ! frame ) {
			frame = window.requestAnimationFrame( arrange );
		}
	} ).observe( part );

	arrange();
	measure();
}() );
